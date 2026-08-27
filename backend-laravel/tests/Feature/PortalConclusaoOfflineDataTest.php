<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Professional;
use App\Models\Student;
use App\Models\TrainingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A fila offline pode despachar horas depois do treino (o aluno treina de manhã
 * sem sinal na academia e o celular só sincroniza à noite). Enquanto
 * concluirSessao gravava now(), a sessão entrava no dia da SINCRONIZAÇÃO —
 * treino de domingo despachado na segunda quebrava o streak, que é a métrica
 * da gamificação e alimenta a ordenação de "última sessão" em
 * Estagnacao/Progressao.
 *
 * O horário do cliente não é confiável, então só vale dentro de uma janela.
 */
class PortalConclusaoOfflineDataTest extends TestCase
{
    use RefreshDatabase;

    private function criarSessaoEmAndamento(): array
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $headers = ['Authorization' => 'Bearer '.auth('api')->login($professional)];

        $studentId = $this->postJson('/alunos', ['name' => 'Aluno Teste'], $headers)
            ->assertCreated()
            ->json('student.id');
        $student = Student::find($studentId);

        $exercise = Exercise::create(['name' => 'Supino reto '.uniqid(), 'muscle_group' => 'peito']);
        $workoutId = $this->postJson('/treinos', [
            'student_id' => $studentId,
            'name' => 'Treino A',
            'items' => [['exercise_id' => $exercise->id, 'sets' => 3, 'reps' => '10']],
        ], $headers)->assertCreated()->json('workout.id');

        $sessionId = $this->postJson("/portal/{$student->invite_token}/sessoes", ['workout_id' => $workoutId])
            ->assertCreated()
            ->json('session.id');

        return [$student, $sessionId];
    }

    public function test_conclusao_atrasada_fica_no_dia_em_que_o_treino_aconteceu(): void
    {
        [$student, $sessionId] = $this->criarSessaoEmAndamento();
        $quandoTreinou = now()->subDay()->setTime(7, 30);

        $this->postJson("/portal/{$student->invite_token}/sessoes/{$sessionId}/concluir", [
            'effort_rpe' => 7,
            'satisfaction' => 9,
            'finished_at' => $quandoTreinou->toIso8601String(),
        ])->assertOk();

        $this->assertSame(
            $quandoTreinou->toDateTimeString(),
            TrainingSession::find($sessionId)->finished_at->toDateTimeString()
        );
    }

    public function test_sem_finished_at_continua_usando_a_hora_do_servidor(): void
    {
        [$student, $sessionId] = $this->criarSessaoEmAndamento();

        $this->postJson("/portal/{$student->invite_token}/sessoes/{$sessionId}/concluir", ['effort_rpe' => 7])
            ->assertOk();

        $this->assertSame(
            now()->toDateString(),
            TrainingSession::find($sessionId)->finished_at->toDateString()
        );
    }

    public function test_data_no_futuro_e_ignorada(): void
    {
        [$student, $sessionId] = $this->criarSessaoEmAndamento();

        // Relógio adiantado (por engano ou pra forjar streak).
        $this->postJson("/portal/{$student->invite_token}/sessoes/{$sessionId}/concluir", [
            'finished_at' => now()->addDays(3)->toIso8601String(),
        ])->assertOk();

        $this->assertSame(
            now()->toDateString(),
            TrainingSession::find($sessionId)->finished_at->toDateString()
        );
    }

    public function test_data_velha_demais_e_ignorada(): void
    {
        [$student, $sessionId] = $this->criarSessaoEmAndamento();

        $this->postJson("/portal/{$student->invite_token}/sessoes/{$sessionId}/concluir", [
            'finished_at' => now()->subDays(30)->toIso8601String(),
        ])->assertOk();

        $this->assertSame(
            now()->toDateString(),
            TrainingSession::find($sessionId)->finished_at->toDateString()
        );
    }

    public function test_reenvio_da_fila_nao_move_a_sessao_ja_concluida(): void
    {
        [$student, $sessionId] = $this->criarSessaoEmAndamento();
        $quandoTreinou = now()->subDay()->setTime(7, 30);
        $url = "/portal/{$student->invite_token}/sessoes/{$sessionId}/concluir";

        $this->postJson($url, ['finished_at' => $quandoTreinou->toIso8601String()])->assertOk();
        // Mesmo item chegando de novo (fila offline reenvia) com outra data.
        $this->postJson($url, ['finished_at' => now()->toIso8601String()])->assertOk();

        $this->assertSame(
            $quandoTreinou->toDateTimeString(),
            TrainingSession::find($sessionId)->finished_at->toDateTimeString()
        );
    }
}
