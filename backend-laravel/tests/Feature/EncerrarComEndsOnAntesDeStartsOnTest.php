<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Models\ProfessionalExpense;
use App\Models\Student;
use App\Models\StudentBillingPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GastoController::encerrar e AlunoController::encerrarCobranca aceitavam
 * qualquer ends_on, inclusive antes do starts_on do registro — encerrar uma
 * despesa/cobrança "no passado" antes dela sequer começar não fazia sentido
 * e distorcia relatórios de receita/despesa.
 */
class EncerrarComEndsOnAntesDeStartsOnTest extends TestCase
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

    public function test_encerrar_despesa_com_ends_on_antes_de_starts_on_devolve_422(): void
    {
        [$professional, $headers] = $this->autenticar();

        $expense = ProfessionalExpense::create([
            'professional_id' => $professional->id,
            'description' => 'Aluguel da sala',
            'amount' => 500,
            'is_recurring' => true,
            'starts_on' => now()->toDateString(),
        ])->refresh();

        $this->patchJson("/gastos/{$expense->id}/encerrar", [
            'ends_on' => now()->subDay()->toDateString(),
        ], $headers)->assertStatus(422);

        $this->assertNull($expense->fresh()->ends_on);
    }

    public function test_encerrar_cobranca_com_ends_on_antes_de_starts_on_devolve_422(): void
    {
        [$professional, $headers] = $this->autenticar();

        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);
        $plano = StudentBillingPlan::create([
            'student_id' => $student->id,
            'professional_id' => $professional->id,
            'billing_type' => 'monthly',
            'monthly_value' => 200,
            'starts_on' => now()->toDateString(),
        ])->refresh();

        $this->patchJson("/alunos/{$student->id}/cobranca/encerrar", [
            'ends_on' => now()->subDay()->toDateString(),
        ], $headers)->assertStatus(422);

        $this->assertNull($plano->fresh()->ends_on);
    }
}
