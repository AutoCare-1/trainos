<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** end_date era validado isoladamente, sem comparar com start_date — um desafio
 * criado com fim antes do início não dava erro nenhum. */
class DesafioValidacaoTest extends TestCase
{
    use RefreshDatabase;

    public function test_criar_desafio_com_end_date_antes_de_start_date_devolve_422(): void
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $token = auth('api')->login($professional);
        $headers = ['Authorization' => "Bearer {$token}"];

        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);

        $this->postJson('/desafios', [
            'name' => 'Desafio Invertido',
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->toDateString(),
            'student_ids' => [$student->id],
        ], $headers)->assertStatus(422);
    }
}
