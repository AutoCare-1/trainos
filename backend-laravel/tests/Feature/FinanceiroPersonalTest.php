<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Models\ProfessionalExpense;
use App\Models\Student;
use App\Models\StudentBillingPlan;
use App\Support\FinanceiroPersonal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FinanceiroPersonalTest extends TestCase
{
    use RefreshDatabase;

    private function criarProfissional(): Professional
    {
        return Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
    }

    private function criarAluno(Professional $professional, string $nome): Student
    {
        return Student::create([
            'professional_id' => $professional->id,
            'name' => $nome,
            'invite_token' => uniqid('token'),
        ]);
    }

    public function test_receita_do_mes_soma_por_tipo_de_cobranca(): void
    {
        $professional = $this->criarProfissional();
        $ana = $this->criarAluno($professional, 'Ana');
        $bruno = $this->criarAluno($professional, 'Bruno');
        $carla = $this->criarAluno($professional, 'Carla');

        StudentBillingPlan::create([
            'student_id' => $ana->id, 'professional_id' => $professional->id,
            'billing_type' => 'consultoria', 'monthly_value' => 150, 'starts_on' => '2026-07-01',
        ]);
        StudentBillingPlan::create([
            'student_id' => $bruno->id, 'professional_id' => $professional->id,
            'billing_type' => 'consultoria', 'monthly_value' => 200, 'starts_on' => '2026-07-01',
        ]);
        StudentBillingPlan::create([
            'student_id' => $carla->id, 'professional_id' => $professional->id,
            'billing_type' => 'presencial', 'monthly_value' => 300, 'starts_on' => '2026-07-01',
        ]);

        $receita = FinanceiroPersonal::calcularReceitaMes($professional->id, Carbon::parse('2026-07-26'));

        $this->assertSame(650.0, $receita['total']);
        $this->assertSame(350.0, $receita['por_tipo']['consultoria']);
        $this->assertSame(300.0, $receita['por_tipo']['presencial']);
    }

    /**
     * O cenário que mexe com dinheiro de verdade: quando o personal edita a
     * cobrança de um aluno, AlunoController::update() fecha o plano vigente com
     * ends_on = hoje e cria um novo com starts_on = hoje — as duas linhas ficam
     * com o MESMO starts_on. Ordenar só por starts_on não resolve esse empate
     * (foi um bug real, encontrado testando manualmente); o desempate correto
     * usa o id (UUID ordenado por tempo).
     */
    public function test_receita_do_mes_so_conta_valor_mais_recente_na_transicao(): void
    {
        $professional = $this->criarProfissional();
        $ana = $this->criarAluno($professional, 'Ana');

        $planoAntigo = StudentBillingPlan::create([
            'student_id' => $ana->id, 'professional_id' => $professional->id,
            'billing_type' => 'consultoria', 'monthly_value' => 150, 'starts_on' => '2026-07-15',
        ]);
        $planoAntigo->update(['ends_on' => '2026-07-15']);

        // Criado DEPOIS do antigo (id maior, mesmo starts_on) — simula exatamente
        // o fechar/criar do AlunoController::update() no mesmo dia.
        StudentBillingPlan::create([
            'student_id' => $ana->id, 'professional_id' => $professional->id,
            'billing_type' => 'consultoria', 'monthly_value' => 250, 'starts_on' => '2026-07-15',
        ]);

        $receita = FinanceiroPersonal::calcularReceitaMes($professional->id, Carbon::parse('2026-07-26'));

        $this->assertSame(250.0, $receita['total'], 'só o valor novo (250) deveria contar, não 150+250=400');
    }

    public function test_despesas_do_mes_soma_recorrentes_ativas_e_avulsas_do_mes(): void
    {
        $professional = $this->criarProfissional();

        // recorrente ativa (começou antes do mês, sem fim)
        ProfessionalExpense::create([
            'professional_id' => $professional->id, 'description' => 'Aluguel',
            'amount' => 500, 'is_recurring' => true, 'starts_on' => '2026-06-01',
        ]);

        // avulsa dentro do mês de referência — conta
        ProfessionalExpense::create([
            'professional_id' => $professional->id, 'description' => 'Equipamento',
            'amount' => 100, 'is_recurring' => false, 'starts_on' => '2026-07-10',
        ]);

        // avulsa em outro mês — não conta
        ProfessionalExpense::create([
            'professional_id' => $professional->id, 'description' => 'Compra antiga',
            'amount' => 999, 'is_recurring' => false, 'starts_on' => '2026-05-05',
        ]);

        // recorrente já encerrada antes do mês de referência — não conta
        ProfessionalExpense::create([
            'professional_id' => $professional->id, 'description' => 'Assinatura cancelada',
            'amount' => 77, 'is_recurring' => true, 'starts_on' => '2026-01-01', 'ends_on' => '2026-06-30',
        ]);

        $despesas = FinanceiroPersonal::calcularDespesasMes($professional->id, Carbon::parse('2026-07-26'));

        $this->assertSame(600.0, $despesas['total']);
    }

    public function test_despesas_do_mes_recorrente_editada_so_conta_a_nova(): void
    {
        $professional = $this->criarProfissional();

        $antiga = ProfessionalExpense::create([
            'professional_id' => $professional->id, 'description' => 'Aluguel',
            'amount' => 500, 'is_recurring' => true, 'starts_on' => '2026-07-01', 'ends_on' => '2026-07-15',
        ]);

        ProfessionalExpense::create([
            'professional_id' => $professional->id, 'description' => 'Aluguel',
            'amount' => 550, 'is_recurring' => true, 'starts_on' => '2026-07-15',
            'previous_expense_id' => $antiga->id,
        ]);

        $despesas = FinanceiroPersonal::calcularDespesasMes($professional->id, Carbon::parse('2026-07-26'));

        $this->assertSame(550.0, $despesas['total'], 'só a versão nova (550) deveria contar, não 500+550=1050');
    }
}
