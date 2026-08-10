<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Models\ProfessionalSubscription;
use App\Support\IaUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CRM interno (/admin) — acesso restrito aos donos, e as contas de faturamento,
 * custo de IA, lucro e rateio entre sócios.
 */
class AdminCrmTest extends TestCase
{
    use RefreshDatabase;

    private function criarPersonal(bool $admin = false, int $diasAtras = 0): array
    {
        $professional = Professional::create([
            'name' => 'Dono Teste',
            'email' => uniqid('dono').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        if ($diasAtras > 0) {
            $professional->created_at = now()->subDays($diasAtras);
            $professional->save();
        }
        if ($admin) {
            $professional->forceFill(['is_admin' => true])->save();
        }
        $professional->refresh();

        $token = auth('api')->login($professional);

        return [$professional, ['Authorization' => "Bearer {$token}"]];
    }

    // ---- Acesso ---------------------------------------------------------------

    public function test_personal_comum_recebe_404_no_crm(): void
    {
        [, $headers] = $this->criarPersonal(admin: false);

        // 404 e não 403: quem não é admin não deve descobrir que o CRM existe.
        $this->getJson('/admin/dashboard', $headers)->assertStatus(404);
    }

    public function test_sem_token_o_crm_responde_401(): void
    {
        $this->getJson('/admin/dashboard')->assertStatus(401);
    }

    public function test_personal_com_teste_expirado_e_sem_assinatura_entra_no_balde_proprio(): void
    {
        [, $headers] = $this->criarPersonal(admin: true);
        // Segundo personal: teste grátis (7 dias por padrão) já passou e nunca
        // chegou a existir uma linha em professional_subscriptions pra ele —
        // esse é o "abandonou antes de assinar", o público mais importante
        // pra um follow-up comercial.
        $this->criarPersonal(admin: false, diasAtras: 30);

        $resp = $this->getJson('/admin/dashboard', $headers)->assertOk()->json();

        $this->assertSame(1, $resp['assinantes']['teste_expirado_sem_assinar']);
        // O próprio admin de teste também não tem assinatura e foi criado
        // "agora" — cai no balde de teste grátis em vigor, não no expirado.
        $this->assertSame(1, $resp['assinantes']['em_teste_gratis']);

        // A soma de todos os baldes precisa bater com o total de contas —
        // ninguém pode ficar invisível no resumo.
        $soma = $resp['assinantes']['ativas'] + $resp['assinantes']['atrasadas']
            + $resp['assinantes']['bloqueadas'] + $resp['assinantes']['canceladas']
            + $resp['assinantes']['pendentes'] + $resp['assinantes']['em_teste_gratis']
            + $resp['assinantes']['teste_expirado_sem_assinar'];
        $this->assertSame($resp['assinantes']['total_personais'], $soma);
    }

    public function test_admin_acessa_o_dashboard(): void
    {
        [, $headers] = $this->criarPersonal(admin: true);

        $this->getJson('/admin/dashboard', $headers)
            ->assertOk()
            ->assertJsonStructure(['resumo' => ['faturamento', 'custo_ia', 'lucro', 'mrr'], 'assinantes', 'serie_mensal']);
    }

    // ---- Faturamento e lucro --------------------------------------------------

    public function test_faturamento_soma_apenas_pagamentos_aprovados(): void
    {
        [$professional, $headers] = $this->criarPersonal(admin: true);
        $sub = ProfessionalSubscription::create([
            'professional_id' => $professional->id,
            'plano_chave' => 'custom',
            'status' => ProfessionalSubscription::STATUS_ATIVA,
        ]);

        foreach ([['aprovado', 79.90], ['aprovado', 149.90], ['recusado', 999.00]] as [$status, $valor]) {
            DB::table('professional_subscription_payments')->insert([
                'id' => (string) Str::uuid(),
                'subscription_id' => $sub->id,
                'mp_payment_id' => uniqid('mp'),
                'valor' => $valor,
                'status' => $status,
                'pago_em' => now()->toDateString(),
                'created_at' => now(),
            ]);
        }

        $this->getJson('/admin/dashboard', $headers)
            ->assertOk()
            // 79,90 + 149,90 — o recusado de 999 fica de fora.
            ->assertJsonPath('resumo.faturamento', 229.8);
    }

    public function test_lucro_desconta_custo_de_ia_e_de_plataforma(): void
    {
        [$professional, $headers] = $this->criarPersonal(admin: true);
        config(['ia_precos.usd_brl' => 5.0]);

        $sub = ProfessionalSubscription::create([
            'professional_id' => $professional->id,
            'plano_chave' => 'custom',
            'status' => ProfessionalSubscription::STATUS_ATIVA,
        ]);
        DB::table('professional_subscription_payments')->insert([
            'id' => (string) Str::uuid(),
            'subscription_id' => $sub->id,
            'mp_payment_id' => uniqid('mp'),
            'valor' => 100.00,
            'status' => 'aprovado',
            'pago_em' => now()->toDateString(),
            'created_at' => now(),
        ]);

        // 2 USD de IA -> 10 BRL na cotação de teste.
        DB::table('ia_usage_logs')->insert([
            'id' => (string) Str::uuid(),
            'pipeline' => 'chat_autopilot',
            'model' => 'claude-haiku-4-5-20251001',
            'custo_usd' => 2.0,
            'created_at' => now(),
        ]);

        DB::table('platform_costs')->insert([
            'id' => (string) Str::uuid(),
            'description' => 'Hospedagem',
            'amount' => 30.00,
            'is_recurring' => true,
            'starts_on' => now()->startOfMonth()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson('/admin/dashboard', $headers)
            ->assertOk()
            ->assertJsonPath('resumo.faturamento', 100)
            ->assertJsonPath('resumo.custo_ia', 10)
            ->assertJsonPath('resumo.custo_plataforma', 30)
            ->assertJsonPath('resumo.lucro', 60);
    }

    // ---- Custo de IA ----------------------------------------------------------

    public function test_calculo_de_custo_usa_a_tabela_de_precos(): void
    {
        // Haiku 4.5: $1/milhão de entrada, $5/milhão de saída.
        // 1.000.000 entrada + 200.000 saída = 1,00 + 1,00 = 2,00 USD.
        $custo = IaUsage::calcularCustoUsd('claude-haiku-4-5-20251001', 1_000_000, 200_000);

        $this->assertSame(2.0, $custo);
    }

    public function test_cache_e_web_search_entram_no_custo(): void
    {
        // Leitura de cache custa 0,1x a entrada: 1M * $1 * 0,1 = $0,10.
        // Duas buscas web a $0,01 = $0,02.
        $custo = IaUsage::calcularCustoUsd(
            'claude-haiku-4-5-20251001',
            inputTokens: 0, outputTokens: 0,
            cacheWriteTokens: 0, cacheReadTokens: 1_000_000, webSearches: 2,
        );

        $this->assertSame(0.12, $custo);
    }

    public function test_modelo_sem_preco_nao_estima_custo_e_e_sinalizado(): void
    {
        [, $headers] = $this->criarPersonal(admin: true);

        $this->assertSame(0.0, IaUsage::calcularCustoUsd('modelo-inexistente', 1_000_000, 1_000_000));

        DB::table('ia_usage_logs')->insert([
            'id' => (string) Str::uuid(),
            'pipeline' => 'chat_autopilot',
            'model' => 'modelo-inexistente',
            'custo_usd' => 0,
            'created_at' => now(),
        ]);

        $this->getJson('/admin/dashboard', $headers)
            ->assertOk()
            ->assertJsonPath('modelos_sem_preco', ['modelo-inexistente']);
    }

    public function test_registrar_nunca_propaga_erro(): void
    {
        // Resposta sem ->usage: não deve lançar nada (a resposta da IA já foi
        // entregue ao usuário; contabilidade não pode derrubar o request).
        IaUsage::registrar('chat_autopilot', (object) ['model' => 'x']);

        $this->assertSame(0, DB::table('ia_usage_logs')->count());
    }

    public function test_registrar_usa_professional_id_explicito_sem_request_autenticado(): void
    {
        // Os pipelines chamados a partir do portal do aluno (chat, evolução física,
        // academia, forma, avaliação postural) não passam pelo middleware JWT —
        // não há request()->user() pra resolver o dono. Por isso esses métodos
        // recebem o professional_id explicitamente (via $student->professional_id
        // no controller) em vez de depender do fallback de IaUsage::registrar.
        [$professional] = $this->criarPersonal(admin: false);

        $usage = new \Anthropic\Messages\Usage;
        $usage->inputTokens = 100;
        $usage->outputTokens = 50;
        $usage->cacheCreationInputTokens = null;
        $usage->cacheReadInputTokens = null;

        $response = (object) ['model' => 'claude-haiku-4-5-20251001', 'usage' => $usage];

        // Sem request HTTP no contexto do teste — o fallback profissionalDoRequest()
        // devolveria null. O professional_id só chega porque foi passado explícito.
        IaUsage::registrar('evolucao_fisica', $response, $professional->id);

        $this->assertSame($professional->id, DB::table('ia_usage_logs')->value('professional_id'));
    }

    // ---- Divisão de lucro -----------------------------------------------------

    public function test_rateio_divide_o_lucro_pelos_percentuais(): void
    {
        [$professional, $headers] = $this->criarPersonal(admin: true);

        $sub = ProfessionalSubscription::create([
            'professional_id' => $professional->id,
            'plano_chave' => 'custom',
            'status' => ProfessionalSubscription::STATUS_ATIVA,
        ]);
        DB::table('professional_subscription_payments')->insert([
            'id' => (string) Str::uuid(),
            'subscription_id' => $sub->id,
            'mp_payment_id' => uniqid('mp'),
            'valor' => 200.00,
            'status' => 'aprovado',
            'pago_em' => now()->toDateString(),
            'created_at' => now(),
        ]);

        $this->postJson('/admin/socios', ['nome' => 'Filipe', 'percentual' => 60], $headers)->assertCreated();
        $this->postJson('/admin/socios', ['nome' => 'Carol', 'percentual' => 40], $headers)->assertCreated();

        $this->getJson('/admin/dashboard', $headers)
            ->assertOk()
            ->assertJsonPath('rateio_lucro.0.nome', 'Filipe')
            ->assertJsonPath('rateio_lucro.0.valor', 120)
            ->assertJsonPath('rateio_lucro.1.nome', 'Carol')
            ->assertJsonPath('rateio_lucro.1.valor', 80);
    }

    public function test_soma_de_percentuais_nao_passa_de_100(): void
    {
        [, $headers] = $this->criarPersonal(admin: true);

        $this->postJson('/admin/socios', ['nome' => 'Filipe', 'percentual' => 70], $headers)->assertCreated();

        $this->postJson('/admin/socios', ['nome' => 'Carol', 'percentual' => 40], $headers)
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'A soma dos percentuais passaria de 100% (hoje está em 70%).']);
    }

    public function test_editar_percentual_fecha_a_linha_antiga_em_vez_de_sobrescrever(): void
    {
        [, $headers] = $this->criarPersonal(admin: true);

        $id = $this->postJson('/admin/socios', ['nome' => 'Filipe', 'percentual' => 50], $headers)
            ->assertCreated()->json('id');

        $this->patchJson("/admin/socios/{$id}", ['percentual' => 70], $headers)->assertOk();

        // Duas linhas: a antiga encerrada (o rateio de meses passados não muda) e a nova vigente.
        $this->assertSame(2, DB::table('profit_shares')->count());
        $this->assertNotNull(DB::table('profit_shares')->where('id', $id)->value('ends_on'));
        $this->assertEquals(70, DB::table('profit_shares')->whereNull('ends_on')->value('percentual'));
    }

    // ---- Custos da plataforma -------------------------------------------------

    public function test_encerrar_custo_recorrente_ainda_conta_no_mes_atual(): void
    {
        [, $headers] = $this->criarPersonal(admin: true);
        config(['ia_precos.usd_brl' => 5.0]);

        $id = $this->postJson('/admin/custos', [
            'description' => 'Servidor',
            'amount' => 50.00,
            'is_recurring' => true,
            'starts_on' => now()->startOfMonth()->toDateString(),
        ], $headers)->assertCreated()->json('id');

        $this->patchJson("/admin/custos/{$id}/encerrar", [], $headers)->assertOk();

        // Encerrado hoje, mas o mês corrente ainda conta (mesma regra de GastoController).
        $this->getJson('/admin/dashboard', $headers)
            ->assertOk()
            ->assertJsonPath('resumo.custo_plataforma', 50);
    }

    // ---- Administradores ------------------------------------------------------

    public function test_promove_outro_personal_a_admin_por_email(): void
    {
        [, $headers] = $this->criarPersonal(admin: true);
        [$outro] = $this->criarPersonal(admin: false);

        $this->postJson('/admin/admins', ['email' => $outro->email], $headers)->assertOk();

        $this->assertTrue($outro->fresh()->is_admin);
    }

    public function test_nao_remove_o_ultimo_admin(): void
    {
        [$eu, $headers] = $this->criarPersonal(admin: true);

        $this->deleteJson("/admin/admins/{$eu->id}", [], $headers)
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'Não dá pra remover o último administrador.']);

        $this->assertTrue($eu->fresh()->is_admin);
    }

    public function test_admin_nao_remove_o_proprio_acesso(): void
    {
        [$eu, $headers] = $this->criarPersonal(admin: true);
        [$outro] = $this->criarPersonal(admin: true);

        $this->deleteJson("/admin/admins/{$eu->id}", [], $headers)
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'Você não pode remover seu próprio acesso.']);

        $this->assertTrue($eu->fresh()->is_admin);
        $this->assertTrue($outro->fresh()->is_admin);
    }

    public function test_is_admin_nao_e_atribuivel_em_massa(): void
    {
        // Uma tentativa de virar admin por payload de cadastro precisa ser ignorada.
        $professional = Professional::create([
            'name' => 'Esperto',
            'email' => uniqid('esperto').'@example.com',
            'password_hash' => bcrypt('senha12345'),
            'is_admin' => true,
        ]);

        $this->assertFalse((bool) $professional->fresh()->is_admin);
    }
}
