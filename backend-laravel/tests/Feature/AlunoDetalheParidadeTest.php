<?php

namespace Tests\Feature;

use App\Models\Professional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre a "ficha do aluno" do lado do profissional (chat, medições, avaliação
 * PAR-Q, fotos de evolução, check-ins) — endpoints que existiam no backend
 * Node e ficaram de fora da primeira portagem pro Laravel.
 */
class AlunoDetalheParidadeTest extends TestCase
{
    use RefreshDatabase;

    private function autenticar(): array
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $token = auth('api')->login($professional);

        return [$professional, ['Authorization' => "Bearer {$token}"]];
    }

    private function criarAluno(array $headers): string
    {
        return $this->postJson('/alunos', ['name' => 'Aluno Teste'], $headers)
            ->assertCreated()
            ->json('student.id');
    }

    public function test_profissional_registra_medicao_do_aluno(): void
    {
        [, $headers] = $this->autenticar();
        $studentId = $this->criarAluno($headers);

        $this->postJson("/alunos/{$studentId}/medicoes", [
            'weight_kg' => 82.5,
            'waist_cm' => 90,
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('measurement.student_id', $studentId);

        $this->getJson("/alunos/{$studentId}", $headers)
            ->assertOk()
            ->assertJsonCount(1, 'measurements');
    }

    public function test_profissional_salva_avaliacao_par_q(): void
    {
        [, $headers] = $this->autenticar();
        $studentId = $this->criarAluno($headers);

        $this->patchJson("/alunos/{$studentId}/avaliacao", [
            'par_q_answers' => ['cardiaco' => true, 'tontura' => false, 'articular' => false, 'pressao_medicacao' => false],
            'health_notes' => 'Hipertenso controlado',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('student.health_notes', 'Hipertenso controlado')
            ->assertJsonPath('student.par_q_answers.cardiaco', true);
    }

    public function test_avaliacao_parcial_nao_apaga_o_par_q_ja_salvo(): void
    {
        [, $headers] = $this->autenticar();
        $studentId = $this->criarAluno($headers);

        $this->patchJson("/alunos/{$studentId}/avaliacao", [
            'par_q_answers' => ['cardiaco' => true],
            'anamnese' => ['lesoes' => 'ombro direito'],
            'birth_date' => '1990-05-20',
        ], $headers)->assertOk();

        // PATCH só com health_notes não pode zerar o resto — é a rota que
        // guarda o PAR-Q, que tem peso legal pro profissional.
        $this->patchJson("/alunos/{$studentId}/avaliacao", ['health_notes' => 'Hipertenso controlado'], $headers)
            ->assertOk()
            ->assertJsonPath('student.health_notes', 'Hipertenso controlado')
            ->assertJsonPath('student.par_q_answers.cardiaco', true)
            ->assertJsonPath('student.anamnese.lesoes', 'ombro direito')
            ->assertJsonPath('student.birth_date', '1990-05-20');
    }

    public function test_chat_do_lado_do_profissional_e_autopilot(): void
    {
        [, $headers] = $this->autenticar();
        $studentId = $this->criarAluno($headers);

        $this->getJson("/alunos/{$studentId}/mensagens", $headers)
            ->assertOk()
            ->assertJson(['messages' => [], 'ai_autopilot' => true]);

        $this->postJson("/alunos/{$studentId}/mensagens", ['content' => 'Como foi o treino?'], $headers)
            ->assertCreated()
            ->assertJsonPath('message.sender', 'professional');

        $this->getJson("/alunos/{$studentId}/mensagens", $headers)
            ->assertOk()
            ->assertJsonCount(1, 'messages');

        $this->patchJson("/alunos/{$studentId}/autopilot", ['enabled' => false], $headers)
            ->assertOk()
            ->assertJsonPath('student.ai_autopilot', false);
    }

    public function test_profissional_ve_fotos_de_evolucao_e_resumo_de_checkins_vazios(): void
    {
        [, $headers] = $this->autenticar();
        $studentId = $this->criarAluno($headers);

        $this->getJson("/alunos/{$studentId}/body-photos", $headers)
            ->assertOk()
            ->assertJson(['photos' => []]);

        $this->getJson("/alunos/{$studentId}/checkins/summary", $headers)
            ->assertOk()
            ->assertJsonStructure(['semana', 'mes', 'ano', 'checkinHoje']);

        $this->getJson("/alunos/{$studentId}/checkins?period=month", $headers)
            ->assertOk()
            ->assertJsonPath('period', 'month');
    }

    public function test_profissional_nao_acessa_ficha_de_aluno_de_outro_profissional(): void
    {
        [, $headersDono] = $this->autenticar();
        $studentId = $this->criarAluno($headersDono);

        [, $headersOutro] = $this->autenticar();

        $this->getJson("/alunos/{$studentId}/mensagens", $headersOutro)->assertNotFound();
        $this->postJson("/alunos/{$studentId}/mensagens", ['content' => 'oi'], $headersOutro)->assertNotFound();
        $this->patchJson("/alunos/{$studentId}/autopilot", ['enabled' => false], $headersOutro)->assertNotFound();
        $this->postJson("/alunos/{$studentId}/medicoes", ['weight_kg' => 70], $headersOutro)->assertNotFound();
        $this->patchJson("/alunos/{$studentId}/avaliacao", ['health_notes' => 'x'], $headersOutro)->assertNotFound();
        $this->getJson("/alunos/{$studentId}/body-photos", $headersOutro)->assertNotFound();
        $this->getJson("/alunos/{$studentId}/checkins/summary", $headersOutro)->assertNotFound();
    }
}
