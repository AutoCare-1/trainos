<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Professional;
use App\Models\Student;
use App\Models\Workout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prazo de validade opcional (duration_weeks -> expires_at) + arquivamento manual
 * (archived_at) — juntos, resolvem o pedido de deixar o aluno escolher entre
 * treinos vigentes (só os não-arquivados aparecem pra ele escolher).
 */
class TreinoValidadeArquivamentoTest extends TestCase
{
    use RefreshDatabase;

    private function criarTreinoComAluno(): array
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $token = auth('api')->login($professional);
        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);
        $exercise = Exercise::firstOrCreate(['name' => uniqid('Supino')], ['muscle_group' => 'peito']);

        $resp = $this->postJson('/treinos', [
            'student_id' => $student->id,
            'name' => 'Treino de Peito',
            'items' => [['exercise_id' => $exercise->id, 'sets' => 3, 'reps' => '10']],
        ], ['Authorization' => "Bearer {$token}"]);

        return [$professional, $token, $student, $resp->json('workout.id')];
    }

    public function test_enviar_com_duration_weeks_calcula_expires_at(): void
    {
        [, $token, , $workoutId] = $this->criarTreinoComAluno();

        $response = $this->postJson("/treinos/{$workoutId}/enviar", [
            'duration_weeks' => 6,
        ], ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $workout = Workout::find($workoutId);
        $this->assertSame(6, $workout->duration_weeks);
        $this->assertSame(now()->addWeeks(6)->toDateString(), $workout->expires_at->toDateString());
    }

    public function test_enviar_sem_duration_weeks_nao_define_validade(): void
    {
        [, $token, , $workoutId] = $this->criarTreinoComAluno();

        $this->postJson("/treinos/{$workoutId}/enviar", [], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200);

        $workout = Workout::find($workoutId);
        $this->assertNull($workout->duration_weeks);
        $this->assertNull($workout->expires_at);
    }

    public function test_arquivar_e_desarquivar_treino(): void
    {
        [, $token, , $workoutId] = $this->criarTreinoComAluno();
        $this->postJson("/treinos/{$workoutId}/enviar", [], ['Authorization' => "Bearer {$token}"]);

        $this->patchJson("/treinos/{$workoutId}/arquivar", ['arquivado' => true], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200);
        $this->assertNotNull(Workout::find($workoutId)->archived_at);

        $this->patchJson("/treinos/{$workoutId}/arquivar", ['arquivado' => false], ['Authorization' => "Bearer {$token}"])
            ->assertStatus(200);
        $this->assertNull(Workout::find($workoutId)->archived_at);
    }

    public function test_arquivar_treino_de_outro_personal_devolve_404(): void
    {
        [, , , $workoutId] = $this->criarTreinoComAluno();

        $outro = Professional::create([
            'name' => 'Outro Personal',
            'email' => uniqid('outro').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $outroToken = auth('api')->login($outro);

        $this->patchJson("/treinos/{$workoutId}/arquivar", ['arquivado' => true], ['Authorization' => "Bearer {$outroToken}"])
            ->assertStatus(404);
    }

    public function test_portal_so_lista_treinos_enviados_e_nao_arquivados(): void
    {
        [$professional, $token, $student] = $this->criarTreinoComAluno();
        $exercise = Exercise::firstOrCreate(['name' => uniqid('Agachamento')], ['muscle_group' => 'pernas']);

        // Segundo treino, enviado e depois arquivado — não deve aparecer na lista.
        $resp2 = $this->postJson('/treinos', [
            'student_id' => $student->id,
            'name' => 'Treino de Perna (antigo)',
            'items' => [['exercise_id' => $exercise->id, 'sets' => 3, 'reps' => '10']],
        ], ['Authorization' => "Bearer {$token}"]);
        $workout2Id = $resp2->json('workout.id');
        $this->postJson("/treinos/{$workout2Id}/enviar", [], ['Authorization' => "Bearer {$token}"]);
        $this->patchJson("/treinos/{$workout2Id}/arquivar", ['arquivado' => true], ['Authorization' => "Bearer {$token}"]);

        // Terceiro treino: rascunho, nunca enviado — também não deve aparecer.
        $exercise2 = Exercise::firstOrCreate(['name' => uniqid('Rosca')], ['muscle_group' => 'biceps']);
        $this->postJson('/treinos', [
            'student_id' => $student->id,
            'name' => 'Rascunho não enviado',
            'items' => [['exercise_id' => $exercise2->id, 'sets' => 3, 'reps' => '10']],
        ], ['Authorization' => "Bearer {$token}"]);

        // Primeiro treino da fixture, ainda em rascunho — envia agora.
        $workout1Id = Workout::where('student_id', $student->id)->where('name', 'Treino de Peito')->first()->id;
        $this->postJson("/treinos/{$workout1Id}/enviar", [], ['Authorization' => "Bearer {$token}"]);

        $response = $this->getJson("/portal/{$student->invite_token}");

        $response->assertStatus(200);
        $nomes = collect($response->json('workouts'))->pluck('name')->all();
        $this->assertSame(['Treino de Peito'], $nomes);
    }

    public function test_aluno_pode_escolher_qual_treino_ver_via_workout_id(): void
    {
        [$professional, $token, $student, $workout1Id] = $this->criarTreinoComAluno();

        // Envia o primeiro treino (Treino de Peito) e força um sent_at no passado —
        // os dois envios acontecem rápido demais no teste pra confiar na ordem real
        // do relógio (sent_at tem precisão de 1s), então garante a ordem explicitamente.
        $this->postJson("/treinos/{$workout1Id}/enviar", [], ['Authorization' => "Bearer {$token}"]);
        Workout::find($workout1Id)->update(['sent_at' => now()->subHour()]);

        $exercise = Exercise::firstOrCreate(['name' => uniqid('Agachamento')], ['muscle_group' => 'pernas']);
        $resp2 = $this->postJson('/treinos', [
            'student_id' => $student->id,
            'name' => 'Treino de Perna',
            'items' => [['exercise_id' => $exercise->id, 'sets' => 3, 'reps' => '10']],
        ], ['Authorization' => "Bearer {$token}"]);
        $workout2Id = $resp2->json('workout.id');
        $this->postJson("/treinos/{$workout2Id}/enviar", [], ['Authorization' => "Bearer {$token}"]);

        // Sem workout_id -> o mais recente enviado (Treino de Perna).
        $this->getJson("/portal/{$student->invite_token}")
            ->assertJsonPath('workout.name', 'Treino de Perna');

        // Com workout_id explícito -> o escolhido (Treino de Peito), mesmo não sendo o mais recente.
        $this->getJson("/portal/{$student->invite_token}?workout_id={$workout1Id}")
            ->assertJsonPath('workout.name', 'Treino de Peito');
    }
}
