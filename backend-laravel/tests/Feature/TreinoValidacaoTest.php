<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * exercise_id era validado só como string — um id inexistente não falhava
 * numa validação limpa (422), estourava QueryException por violação de FK
 * dentro da transação (500 cru). sets sem min:1 aceitava 0 séries, quebrando
 * a barra de progresso no portal do aluno.
 */
class TreinoValidacaoTest extends TestCase
{
    use RefreshDatabase;

    private function autenticarComAluno(): array
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

        return [$headers, $studentId];
    }

    public function test_criar_treino_com_exercise_id_inexistente_devolve_422_nao_500(): void
    {
        [$headers, $studentId] = $this->autenticarComAluno();

        $this->postJson('/treinos', [
            'student_id' => $studentId,
            'name' => 'Treino A',
            'items' => [
                ['exercise_id' => (string) \Illuminate\Support\Str::orderedUuid(), 'sets' => 3, 'reps' => '10'],
            ],
        ], $headers)->assertStatus(422);
    }

    public function test_criar_treino_com_zero_series_devolve_422(): void
    {
        [$headers, $studentId] = $this->autenticarComAluno();
        $exercise = \App\Models\Exercise::firstOrCreate(['name' => 'Agachamento'], ['muscle_group' => 'pernas']);

        $this->postJson('/treinos', [
            'student_id' => $studentId,
            'name' => 'Treino A',
            'items' => [
                ['exercise_id' => $exercise->id, 'sets' => 0, 'reps' => '10'],
            ],
        ], $headers)->assertStatus(422);
    }

    public function test_criar_modelo_com_exercise_id_inexistente_devolve_422_nao_500(): void
    {
        [$headers] = $this->autenticarComAluno();

        $this->postJson('/modelos', [
            'name' => 'Modelo A',
            'items' => [
                ['exercise_id' => (string) \Illuminate\Support\Str::orderedUuid(), 'sets' => 3, 'reps' => '10'],
            ],
        ], $headers)->assertStatus(422);
    }
}
