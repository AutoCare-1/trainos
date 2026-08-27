<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Professional;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cobre a idempotência do registro de série pelo client_entry_id — o ID que a
 * fila offline do portal gera no cliente. Sem isso, um despacho repetido da
 * fila (timeout onde a requisição na verdade completou, app reaberto no meio
 * da sincronização) duplicaria a série.
 *
 * O caminho online continua coberto por PortalSessaoTreinoTest (double-tap
 * protegido pela constraint de sessão+exercício+série); aqui é só o caminho
 * novo, que não pode depender do set_number.
 */
class PortalSerieOfflineIdempotenteTest extends TestCase
{
    use RefreshDatabase;

    private function criarCenario(): array
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

        $exercise = Exercise::create([
            'name' => 'Agachamento '.uniqid(),
            'muscle_group' => 'Pernas',
            'equipment' => 'Barra',
        ]);

        $workoutId = $this->postJson('/treinos', [
            'student_id' => $student->id,
            'name' => 'Treino A',
            'items' => [['exercise_id' => $exercise->id, 'sets' => 3, 'reps' => '10-12']],
        ], $headers)->assertCreated()->json('workout.id');
        $this->postJson("/treinos/{$workoutId}/enviar", [], $headers)->assertOk();

        $workoutExerciseId = $this->getJson("/treinos/{$workoutId}", $headers)->json('exercises.0.id');

        $sessionId = $this->postJson("/portal/{$student->invite_token}/sessoes", ['workout_id' => $workoutId])
            ->assertCreated()
            ->json('session.id');

        return [$student, $workoutId, $workoutExerciseId, $sessionId];
    }

    public function test_mesmo_client_entry_id_enviado_duas_vezes_nao_duplica(): void
    {
        [$student, , $workoutExerciseId, $sessionId] = $this->criarCenario();

        $payload = [
            'client_entry_id' => (string) Str::uuid(),
            'workout_exercise_id' => $workoutExerciseId,
            'set_number' => 1,
            'reps_done' => 12,
            'load_kg_done' => 60,
        ];

        $rota = "/portal/{$student->invite_token}/sessoes/{$sessionId}/registros";
        $primeira = $this->postJson($rota, $payload)->assertCreated();
        $segunda = $this->postJson($rota, $payload)->assertOk();

        $this->assertSame($primeira->json('entry.id'), $segunda->json('entry.id'));
        $this->assertDatabaseCount('session_entries', 1);
    }

    public function test_reenvio_com_set_number_diferente_ainda_e_o_mesmo_registro(): void
    {
        // Cenário real da fila offline: o aluno registrou a série sem rede, e
        // ao sincronizar o app recalculou o número da série (ex: já tinha uma
        // série gravada por outro dispositivo). O client_entry_id é o que
        // identifica o registro — o set_number reenviado não pode criar outro.
        [$student, , $workoutExerciseId, $sessionId] = $this->criarCenario();

        $clientEntryId = (string) Str::uuid();
        $rota = "/portal/{$student->invite_token}/sessoes/{$sessionId}/registros";

        $primeira = $this->postJson($rota, [
            'client_entry_id' => $clientEntryId,
            'workout_exercise_id' => $workoutExerciseId,
            'set_number' => 1,
            'reps_done' => 12,
            'load_kg_done' => 60,
        ])->assertCreated();

        $segunda = $this->postJson($rota, [
            'client_entry_id' => $clientEntryId,
            'workout_exercise_id' => $workoutExerciseId,
            'set_number' => 2,
            'reps_done' => 12,
            'load_kg_done' => 60,
        ])->assertOk();

        $this->assertSame($primeira->json('entry.id'), $segunda->json('entry.id'));
        $this->assertSame(1, $segunda->json('entry.set_number'));
        $this->assertDatabaseCount('session_entries', 1);
    }

    public function test_client_entry_id_de_outra_sessao_e_rejeitado(): void
    {
        [$student, $workoutId, $workoutExerciseId, $sessionId] = $this->criarCenario();

        $clientEntryId = (string) Str::uuid();
        $this->postJson("/portal/{$student->invite_token}/sessoes/{$sessionId}/registros", [
            'client_entry_id' => $clientEntryId,
            'workout_exercise_id' => $workoutExerciseId,
            'set_number' => 1,
            'reps_done' => 12,
            'load_kg_done' => 60,
        ])->assertCreated();

        // Encerra a sessão e começa outra no mesmo treino.
        $this->postJson("/portal/{$student->invite_token}/sessoes/{$sessionId}/concluir", [])->assertOk();
        $novaSessao = $this->postJson("/portal/{$student->invite_token}/sessoes", ['workout_id' => $workoutId])
            ->assertCreated()
            ->json('session.id');

        $this->postJson("/portal/{$student->invite_token}/sessoes/{$novaSessao}/registros", [
            'client_entry_id' => $clientEntryId,
            'workout_exercise_id' => $workoutExerciseId,
            'set_number' => 1,
            'reps_done' => 12,
            'load_kg_done' => 60,
        ])->assertStatus(409);

        $this->assertDatabaseCount('session_entries', 1);
    }

    public function test_registro_sem_client_entry_id_continua_funcionando(): void
    {
        [$student, , $workoutExerciseId, $sessionId] = $this->criarCenario();

        $this->postJson("/portal/{$student->invite_token}/sessoes/{$sessionId}/registros", [
            'workout_exercise_id' => $workoutExerciseId,
            'set_number' => 1,
            'reps_done' => 12,
            'load_kg_done' => 60,
        ])->assertCreated();

        $this->assertDatabaseCount('session_entries', 1);
    }

    public function test_concluir_reenviado_pela_fila_nao_move_a_data_da_sessao(): void
    {
        [$student, , , $sessionId] = $this->criarCenario();

        $rota = "/portal/{$student->invite_token}/sessoes/{$sessionId}/concluir";
        $primeira = $this->postJson($rota, ['effort_rpe' => 7, 'satisfaction' => 4])->assertOk();

        $this->travel(2)->days();
        $segunda = $this->postJson($rota, ['effort_rpe' => 7, 'satisfaction' => 4])->assertOk();

        $this->assertSame($primeira->json('session.finished_at'), $segunda->json('session.finished_at'));
        $this->assertDatabaseCount('feedbacks', 1);
    }
}
