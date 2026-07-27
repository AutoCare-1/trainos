<?php

namespace Tests\Feature;

use App\Models\Checkin;
use App\Models\Professional;
use App\Models\Student;
use App\Models\TrainingSession;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * GET /negocio nunca tinha teste — o cálculo de "alunos em risco" usava
 * datediff() (só MySQL) em SQL bruto, nunca coberto por CI (SQLite). Corrigido
 * pra calcular a diferença de dias em PHP; este teste garante que continua
 * classificando certo.
 */
class NegocioControllerTest extends TestCase
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

    // Student não tem created_at no $fillable (nem timestamps automáticos) —
    // backdata direto na coluna via query builder, só pra montar o cenário do teste.
    private function backdatarCadastro(string $studentId, Carbon $data): void
    {
        DB::table('students')->where('id', $studentId)->update(['created_at' => $data]);
    }

    public function test_negocio_classifica_aluno_novo_sem_treinos_como_risco_alto(): void
    {
        [$professional, $headers] = $this->autenticar();

        $student = Student::create([
            'professional_id' => $professional->id,
            'name' => 'Aluno Novo',
            'invite_token' => uniqid('token'),
        ]);
        $this->backdatarCadastro($student->id, Carbon::now()->subDays(5));

        $response = $this->getJson('/negocio', $headers)->assertOk();

        $response->assertJsonPath('kpis.total_alunos', 1);
        $response->assertJsonPath('alunos_em_risco.0.name', 'Aluno Novo');
        $response->assertJsonPath('alunos_em_risco.0.prioridade', 'alta');
        $this->assertStringContainsString('Cadastrado há 5d', $response->json('alunos_em_risco.0.motivo'));
    }

    public function test_negocio_nao_lista_aluno_ativo_como_risco(): void
    {
        [$professional, $headers] = $this->autenticar();

        $student = Student::create([
            'professional_id' => $professional->id,
            'name' => 'Aluno Ativo',
            'invite_token' => uniqid('token'),
        ]);
        // Cadastrado há tempo (fora da janela de "aluno novo"), mas treinou e
        // fez check-in recentemente — não deveria entrar em nenhuma categoria de risco.
        $this->backdatarCadastro($student->id, Carbon::now()->subDays(30));

        $workout = Workout::create([
            'professional_id' => $professional->id,
            'student_id' => $student->id,
            'name' => 'Treino A',
            'status' => 'sent',
            'sent_at' => Carbon::now()->subDays(2),
        ]);
        TrainingSession::create([
            'workout_id' => $workout->id,
            'student_id' => $student->id,
            'status' => 'completed',
            'started_at' => Carbon::now()->subHours(2),
            'finished_at' => Carbon::now()->subHour(),
        ]);
        Checkin::create([
            'student_id' => $student->id,
            'checkin_date' => Carbon::now()->toDateString(),
            'file_path' => 'checkins/teste/foto.jpg',
        ]);

        $response = $this->getJson('/negocio', $headers)->assertOk();

        $response->assertJsonCount(0, 'alunos_em_risco');
    }
}
