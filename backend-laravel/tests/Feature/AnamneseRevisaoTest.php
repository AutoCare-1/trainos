<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Models\Student;
use App\Models\Workout;
use App\Models\WorkoutReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Anamnese de revisão — dispara (revisaoPendente em GET /portal/:token) quando
 * um treino com prazo definido vence, e some assim que o aluno responde.
 */
class AnamneseRevisaoTest extends TestCase
{
    use RefreshDatabase;

    private function criarAlunoComTreinoVencido(): array
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $student = Student::create([
            'professional_id' => $professional->id,
            'name' => 'Aluno',
            'invite_token' => uniqid(),
            'onboarding_completed_at' => now(),
        ]);
        // created_at não é mass-assignable (preenchido pelo useCurrent() do banco) —
        // seta direto pra simular um aluno com 8 semanas de acompanhamento.
        $student->created_at = now()->subWeeks(8);
        $student->save();
        $workout = Workout::create([
            'professional_id' => $professional->id,
            'student_id' => $student->id,
            'name' => 'Treino de Peito',
            'status' => 'sent',
            'sent_at' => now()->subWeeks(6),
            'duration_weeks' => 6,
            'expires_at' => now()->subDay()->toDateString(),
        ]);

        return [$professional, $student, $workout];
    }

    public function test_portal_sinaliza_revisao_pendente_quando_treino_vence(): void
    {
        [, $student, $workout] = $this->criarAlunoComTreinoVencido();

        $response = $this->getJson("/portal/{$student->invite_token}");

        $response->assertStatus(200);
        $response->assertJsonPath('revisaoPendente.workout_id', $workout->id);
        $response->assertJsonPath('revisaoPendente.workout_name', 'Treino de Peito');
    }

    public function test_nao_sinaliza_revisao_pendente_pra_treino_sem_prazo(): void
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $student = Student::create([
            'professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid(),
            'onboarding_completed_at' => now(),
        ]);
        Workout::create([
            'professional_id' => $professional->id, 'student_id' => $student->id, 'name' => 'Treino Livre',
            'status' => 'sent', 'sent_at' => now(),
        ]);

        $response = $this->getJson("/portal/{$student->invite_token}");

        $response->assertJsonPath('revisaoPendente', null);
    }

    public function test_nao_sinaliza_revisao_pendente_pra_treino_arquivado(): void
    {
        [, $student, $workout] = $this->criarAlunoComTreinoVencido();
        $workout->update(['archived_at' => now()]);

        $this->getJson("/portal/{$student->invite_token}")
            ->assertJsonPath('revisaoPendente', null);
    }

    public function test_aluno_responde_a_revisao(): void
    {
        [, $student, $workout] = $this->criarAlunoComTreinoVencido();

        $respostas = [
            'avaliacao_treino' => 'boa',
            'gostou_mais' => 'Supino',
            'nao_gostou' => '',
            'percebeu_evolucao' => 'sim_bastante',
            'aspectos_progresso' => ['forca', 'disposicao'],
            'aspectos_progresso_outro' => '',
            'manteve_frequencia' => 'sim',
            'treinos_por_semana' => 4,
            'dificuldade_rotina' => '',
            'sugestao_melhoria' => 'Mais peito',
            'sugestao_modalidade' => '',
            'sugestao_geral' => '',
        ];

        $response = $this->postJson("/portal/{$student->invite_token}/revisao", [
            'workout_id' => $workout->id,
            'respostas' => $respostas,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('workout_reviews', 1);
        $revisao = WorkoutReview::first();
        $this->assertSame($workout->id, $revisao->workout_id);
        $this->assertSame(8, $revisao->tempo_acompanhamento_semanas);
        $this->assertSame('boa', $revisao->respostas['avaliacao_treino']);

        // Depois de responder, some do "pendente".
        $this->getJson("/portal/{$student->invite_token}")
            ->assertJsonPath('revisaoPendente', null);
    }

    public function test_nao_deixa_responder_a_mesma_revisao_duas_vezes(): void
    {
        [, $student, $workout] = $this->criarAlunoComTreinoVencido();
        $payload = ['workout_id' => $workout->id, 'respostas' => ['avaliacao_treino' => 'boa']];

        $this->postJson("/portal/{$student->invite_token}/revisao", $payload)->assertStatus(201);
        $this->postJson("/portal/{$student->invite_token}/revisao", $payload)->assertStatus(409);

        $this->assertDatabaseCount('workout_reviews', 1);
    }

    public function test_personal_ve_o_historico_de_revisoes(): void
    {
        [$professional, $student, $workout] = $this->criarAlunoComTreinoVencido();
        $token = auth('api')->login($professional);

        $this->postJson("/portal/{$student->invite_token}/revisao", [
            'workout_id' => $workout->id,
            'respostas' => ['avaliacao_treino' => 'excelente'],
        ])->assertStatus(201);

        $response = $this->getJson("/alunos/{$student->id}/revisoes", ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'reviews');
        $response->assertJsonPath('reviews.0.workout_name', 'Treino de Peito');
    }
}
