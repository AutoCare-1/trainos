<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Professional;
use App\Models\Student;
use App\Models\TrainingSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cobre PortalController::iniciarSessao / registrarSerie — endpoints do
 * portal do aluno (autenticados só pelo token da URL, sem JWT) que aceitam
 * workout_id / workout_exercise_id vindos do body: precisam confirmar que
 * pertencem ao aluno dono do token antes de agir, e o double-tap no botão
 * de registrar série não pode duplicar a linha.
 */
class PortalSessaoTreinoTest extends TestCase
{
    use RefreshDatabase;

    private function criarProfissionalComAluno(): array
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $token = auth('api')->login($professional);
        $headers = ['Authorization' => "Bearer {$token}"];

        $studentId = $this->postJson('/alunos', ['name' => 'Aluno Teste'], $headers)
            ->assertCreated()
            ->json('student.id');

        return [$professional, $headers, Student::find($studentId)];
    }

    private function criarTreinoComExercicio(array $headers, string $studentId): array
    {
        $exercise = Exercise::create(['name' => 'Supino reto '.uniqid(), 'muscle_group' => 'peito']);

        $workoutId = $this->postJson('/treinos', [
            'student_id' => $studentId,
            'name' => 'Treino A',
            'items' => [
                ['exercise_id' => $exercise->id, 'sets' => 3, 'reps' => '10'],
            ],
        ], $headers)
            ->assertCreated()
            ->json('workout.id');

        // Enviar de verdade: o aluno só enxerga (e só pode iniciar) treino com
        // status 'sent' — rascunho é o personal ainda montando.
        $this->postJson("/treinos/{$workoutId}/enviar", [], $headers)->assertOk();

        $workoutExerciseId = $this->getJson("/treinos/{$workoutId}", $headers)
            ->json('exercises.0.id');

        return [$workoutId, $workoutExerciseId];
    }

    public function test_double_tap_ao_registrar_serie_nao_duplica_a_linha(): void
    {
        [, $headers, $student] = $this->criarProfissionalComAluno();
        [$workoutId, $workoutExerciseId] = $this->criarTreinoComExercicio($headers, $student->id);

        $sessionId = $this->postJson("/portal/{$student->invite_token}/sessoes", ['workout_id' => $workoutId])
            ->assertCreated()
            ->json('session.id');

        $payload = [
            'workout_exercise_id' => $workoutExerciseId,
            'set_number' => 1,
            'reps_done' => 10,
            'load_kg_done' => 40,
        ];

        // Duas chamadas idênticas seguidas — simula o double-tap.
        $primeira = $this->postJson("/portal/{$student->invite_token}/sessoes/{$sessionId}/registros", $payload);
        $segunda = $this->postJson("/portal/{$student->invite_token}/sessoes/{$sessionId}/registros", $payload);

        $primeira->assertCreated();
        $segunda->assertOk(); // idempotente: devolve a entry existente, não cria outra
        $this->assertSame($primeira->json('entry.id'), $segunda->json('entry.id'));

        $this->assertDatabaseCount('session_entries', 1);
    }

    public function test_iniciar_sessao_rejeita_workout_id_de_outro_aluno(): void
    {
        [, $headersA, $alunoA] = $this->criarProfissionalComAluno();
        [$workoutIdA] = $this->criarTreinoComExercicio($headersA, $alunoA->id);

        [, , $alunoB] = $this->criarProfissionalComAluno();

        $this->postJson("/portal/{$alunoB->invite_token}/sessoes", ['workout_id' => $workoutIdA])
            ->assertNotFound();

        $this->assertDatabaseCount('training_sessions', 0);
    }

    public function test_registrar_serie_rejeita_workout_exercise_id_de_outro_treino(): void
    {
        [, $headersA, $alunoA] = $this->criarProfissionalComAluno();
        [, $workoutExerciseIdA] = $this->criarTreinoComExercicio($headersA, $alunoA->id);

        [, $headersB, $alunoB] = $this->criarProfissionalComAluno();
        [$workoutIdB] = $this->criarTreinoComExercicio($headersB, $alunoB->id);

        $sessionId = $this->postJson("/portal/{$alunoB->invite_token}/sessoes", ['workout_id' => $workoutIdB])
            ->assertCreated()
            ->json('session.id');

        // Aluno B tenta registrar série referenciando o workout_exercise_id do
        // treino do aluno A — deve ser rejeitado, não gravar cruzado.
        $this->postJson("/portal/{$alunoB->invite_token}/sessoes/{$sessionId}/registros", [
            'workout_exercise_id' => $workoutExerciseIdA,
            'set_number' => 1,
        ])->assertNotFound();

        $this->assertDatabaseCount('session_entries', 0);
    }

    public function test_duas_chamadas_simultaneas_de_iniciar_sessao_nao_criam_duas_sessoes_em_andamento(): void
    {
        [, $headers, $student] = $this->criarProfissionalComAluno();
        [$workoutId] = $this->criarTreinoComExercicio($headers, $student->id);

        $r1 = $this->postJson("/portal/{$student->invite_token}/sessoes", ['workout_id' => $workoutId]);
        $r2 = $this->postJson("/portal/{$student->invite_token}/sessoes", ['workout_id' => $workoutId]);

        $r1->assertCreated();
        $r2->assertOk();
        $this->assertSame($r1->json('session.id'), $r2->json('session.id'));

        $this->assertSame(
            1,
            TrainingSession::where('workout_id', $workoutId)->where('status', 'in_progress')->count()
        );
    }
}
