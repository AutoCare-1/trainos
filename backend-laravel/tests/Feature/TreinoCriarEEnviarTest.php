<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Professional;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Salvar e enviar ao aluno" era POST /treinos seguido de
 * POST /treinos/{id}/enviar. Uma falha de rede entre as duas deixava um
 * rascunho órfão que o personal não via em lugar nenhum — e ele clicava de
 * novo, criando outro treino.
 */
class TreinoCriarEEnviarTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: array, 1: string, 2: string} */
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
        $exercise = Exercise::create(['name' => 'Supino '.uniqid(), 'muscle_group' => 'peito']);

        return [$headers, $studentId, $exercise->id];
    }

    private function itens(string $exerciseId): array
    {
        return [['exercise_id' => $exerciseId, 'sets' => 3, 'reps' => '10']];
    }

    public function test_cria_ja_enviado_numa_chamada_so(): void
    {
        [$headers, $studentId, $exerciseId] = $this->cenario();

        $workoutId = $this->postJson('/treinos', [
            'student_id' => $studentId,
            'name' => 'Treino A',
            'items' => $this->itens($exerciseId),
            'enviar' => true,
            'duration_weeks' => 4,
        ], $headers)->assertCreated()->json('workout.id');

        $workout = Workout::find($workoutId);
        $this->assertSame('sent', $workout->status);
        $this->assertNotNull($workout->sent_at);
        $this->assertSame(4, $workout->duration_weeks);
        $this->assertSame(now()->addWeeks(4)->toDateString(), $workout->expires_at->toDateString());
    }

    public function test_sem_enviar_continua_nascendo_rascunho(): void
    {
        [$headers, $studentId, $exerciseId] = $this->cenario();

        $workoutId = $this->postJson('/treinos', [
            'student_id' => $studentId,
            'name' => 'Treino A',
            'items' => $this->itens($exerciseId),
        ], $headers)->assertCreated()->json('workout.id');

        $workout = Workout::find($workoutId);
        $this->assertNotSame('sent', $workout->status);
        $this->assertNull($workout->sent_at);
    }

    public function test_enviar_em_duas_etapas_continua_funcionando(): void
    {
        [$headers, $studentId, $exerciseId] = $this->cenario();

        $workoutId = $this->postJson('/treinos', [
            'student_id' => $studentId,
            'name' => 'Treino A',
            'items' => $this->itens($exerciseId),
        ], $headers)->assertCreated()->json('workout.id');

        $this->postJson("/treinos/{$workoutId}/enviar", ['duration_weeks' => 2], $headers)->assertOk();

        $this->assertSame('sent', Workout::find($workoutId)->status);
    }
}
