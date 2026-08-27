<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Professional;
use App\Models\Student;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validações de faixa nos endpoints do portal (item 17 da revisão externa).
 *
 * São campos que ninguém digita fora da faixa pela interface, mas que chegam
 * do cliente e vão parar em lugares que assumem a faixa: o RPE entra no
 * system prompt do coach IA como "RPE 0-10" e nos gráficos de evolução, e
 * set_number vira chave de idempotência da fila offline.
 */
class PortalValidacoesFaixaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Student, 1: array, 2: string, 3: string} */
    private function cenario(): array
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $headers = ['Authorization' => 'Bearer '.auth('api')->login($professional)];

        $studentId = $this->postJson('/alunos', ['name' => 'Aluno Teste'], $headers)
            ->assertCreated()->json('student.id');
        $student = Student::find($studentId);

        $exercise = Exercise::create(['name' => 'Supino '.uniqid(), 'muscle_group' => 'peito']);
        $workoutId = $this->postJson('/treinos', [
            'student_id' => $studentId,
            'name' => 'Treino A',
            'items' => [['exercise_id' => $exercise->id, 'sets' => 3, 'reps' => '10']],
        ], $headers)->assertCreated()->json('workout.id');
        $this->postJson("/treinos/{$workoutId}/enviar", [], $headers)->assertOk();

        $workoutExerciseId = $this->getJson("/treinos/{$workoutId}", $headers)->json('exercises.0.id');

        return [$student, $headers, $workoutId, $workoutExerciseId];
    }

    public function test_set_number_zero_ou_negativo_e_rejeitado(): void
    {
        [$student, , $workoutId, $workoutExerciseId] = $this->cenario();
        $sessionId = $this->postJson("/portal/{$student->invite_token}/sessoes", ['workout_id' => $workoutId])
            ->assertCreated()->json('session.id');

        $this->postJson("/portal/{$student->invite_token}/sessoes/{$sessionId}/registros", [
            'workout_exercise_id' => $workoutExerciseId,
            'set_number' => 0,
        ])->assertStatus(422);
    }

    public function test_rpe_fora_de_zero_a_dez_e_rejeitado(): void
    {
        [$student, , $workoutId] = $this->cenario();
        $sessionId = $this->postJson("/portal/{$student->invite_token}/sessoes", ['workout_id' => $workoutId])
            ->assertCreated()->json('session.id');
        $url = "/portal/{$student->invite_token}/sessoes/{$sessionId}/concluir";

        $this->postJson($url, ['effort_rpe' => 11])->assertStatus(422);
        $this->postJson($url, ['satisfaction' => -1])->assertStatus(422);
        // A faixa válida continua passando.
        $this->postJson($url, ['effort_rpe' => 10, 'satisfaction' => 0])->assertOk();
    }

    public function test_avaliacao_nao_pode_ser_reenviada_depois_do_onboarding(): void
    {
        [$student] = $this->cenario();
        $url = "/portal/{$student->invite_token}/avaliacao";

        $this->postJson($url, ['par_q_answers' => ['cardiaco' => true]])->assertCreated();

        // Reenviar sobrescrevia a anamnese inteira, inclusive o PAR-Q — e o
        // link do portal circula por WhatsApp.
        $this->postJson($url, ['par_q_answers' => ['cardiaco' => false]])->assertStatus(409);

        $this->assertTrue($student->fresh()->par_q_answers['cardiaco']);
    }

    public function test_treino_em_rascunho_nao_vira_sessao(): void
    {
        [$student, $headers] = $this->cenario();
        $exercise = Exercise::create(['name' => 'Remada '.uniqid(), 'muscle_group' => 'costas']);
        $rascunhoId = $this->postJson('/treinos', [
            'student_id' => $student->id,
            'name' => 'Ainda montando',
            'items' => [['exercise_id' => $exercise->id, 'sets' => 3, 'reps' => '10']],
        ], $headers)->assertCreated()->json('workout.id');

        $this->postJson("/portal/{$student->invite_token}/sessoes", ['workout_id' => $rascunhoId])
            ->assertStatus(409);
    }

    public function test_sessao_em_andamento_pode_ser_retomada_mesmo_apos_arquivar(): void
    {
        [$student, , $workoutId] = $this->cenario();
        $sessionId = $this->postJson("/portal/{$student->invite_token}/sessoes", ['workout_id' => $workoutId])
            ->assertCreated()->json('session.id');

        // Personal arquiva com o aluno no meio do treino: deixá-lo preso na
        // academia sem conseguir continuar seria pior que a trava do rascunho.
        Workout::where('id', $workoutId)->update(['archived_at' => now()]);

        $this->postJson("/portal/{$student->invite_token}/sessoes", ['workout_id' => $workoutId])
            ->assertOk()
            ->assertJsonPath('session.id', $sessionId);
    }
}
