<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * weight_kg/height_cm (cadastro/edição de aluno) e weight_kg/waist_cm/hip_cm/
 * body_fat_pct (registro de medição) só validavam "numeric" — zero ou negativo
 * passava, gerando gráficos de evolução com valores sem sentido físico.
 */
class MedidasFisicasNaoNegativasTest extends TestCase
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

    public function test_cadastrar_aluno_com_peso_negativo_devolve_422(): void
    {
        [, $headers] = $this->autenticar();

        $this->postJson('/alunos', [
            'name' => 'Aluno Teste',
            'weight_kg' => -70,
        ], $headers)->assertStatus(422);
    }

    public function test_cadastrar_aluno_com_altura_zero_devolve_422(): void
    {
        [, $headers] = $this->autenticar();

        $this->postJson('/alunos', [
            'name' => 'Aluno Teste',
            'height_cm' => 0,
        ], $headers)->assertStatus(422);
    }

    public function test_registrar_medicao_com_peso_zero_devolve_422(): void
    {
        [$professional, $headers] = $this->autenticar();
        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);

        $this->postJson("/alunos/{$student->id}/medicoes", [
            'weight_kg' => 0,
        ], $headers)->assertStatus(422);

        $this->assertDatabaseCount('body_measurements', 0);
    }

    public function test_registrar_medicao_com_percentual_de_gordura_negativo_devolve_422(): void
    {
        [$professional, $headers] = $this->autenticar();
        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);

        $this->postJson("/alunos/{$student->id}/medicoes", [
            'weight_kg' => 70,
            'body_fat_pct' => -5,
        ], $headers)->assertStatus(422);

        $this->assertDatabaseCount('body_measurements', 0);
    }
}
