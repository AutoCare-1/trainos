<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Models\StudentBillingPlan;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlunoControllerBillingTest extends TestCase
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

    public function test_editar_cobranca_fecha_plano_vigente_e_cria_novo(): void
    {
        [, $headers] = $this->autenticar();

        $studentId = $this->postJson('/alunos', [
            'name' => 'Aluno Teste',
            'billing_type' => 'consultoria',
            'monthly_value' => 150,
        ], $headers)->assertCreated()->json('student.id');

        $this->assertDatabaseCount('student_billing_plans', 1);
        $planoInicial = StudentBillingPlan::where('student_id', $studentId)->first();
        $this->assertSame(15000, Money::toCents($planoInicial->monthly_value));
        $this->assertNull($planoInicial->ends_on);

        $this->patchJson("/alunos/{$studentId}", [
            'billing_type' => 'presencial',
            'monthly_value' => 250,
        ], $headers)->assertOk();

        $this->assertDatabaseCount('student_billing_plans', 2);

        $planoFechado = StudentBillingPlan::where('id', $planoInicial->id)->first();
        $this->assertNotNull($planoFechado->ends_on, 'o plano antigo deveria estar fechado (ends_on preenchido)');

        $planoVigente = StudentBillingPlan::where('student_id', $studentId)->whereNull('ends_on')->first();
        $this->assertSame('presencial', $planoVigente->billing_type);
        $this->assertSame(25000, Money::toCents($planoVigente->monthly_value));
    }

    public function test_reenviar_mesmo_valor_nao_cria_linha_de_historico_redundante(): void
    {
        [, $headers] = $this->autenticar();

        $studentId = $this->postJson('/alunos', [
            'name' => 'Aluno Teste',
            'billing_type' => 'consultoria',
            'monthly_value' => 150,
        ], $headers)->assertCreated()->json('student.id');

        $this->patchJson("/alunos/{$studentId}", [
            'billing_type' => 'consultoria',
            'monthly_value' => 150,
        ], $headers)->assertOk();

        $this->assertDatabaseCount('student_billing_plans', 1);
    }

    public function test_cadastro_sem_cobranca_nao_cria_plano(): void
    {
        [, $headers] = $this->autenticar();

        $this->postJson('/alunos', ['name' => 'Aluno Sem Cobrança'], $headers)->assertCreated();

        $this->assertDatabaseCount('student_billing_plans', 0);
    }
}
