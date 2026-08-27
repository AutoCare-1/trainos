<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Models\ProfessionalSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Assinatura do personal com o TrainOS (cobrança recorrente, plano por limite
 * de alunos) — teste grátis, limite de plano, carência e bloqueio. Não
 * confundir com StudentBillingPlan (cobrança do aluno pelo personal).
 */
class AssinaturaTest extends TestCase
{
    use RefreshDatabase;

    private function criarPersonal(int $diasAtras = 0): array
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        if ($diasAtras > 0) {
            $professional->created_at = now()->subDays($diasAtras);
            $professional->save();
        }
        $professional->refresh();

        $token = auth('api')->login($professional);

        return [$professional, ['Authorization' => "Bearer {$token}"]];
    }

    private function cadastrarAlunos(array $headers, int $quantidade): void
    {
        for ($i = 0; $i < $quantidade; $i++) {
            $this->postJson('/alunos', ['name' => "Aluno {$i}"], $headers)->assertCreated();
        }
    }

    public function test_personal_em_teste_gratis_cadastra_aluno_sem_limite(): void
    {
        [, $headers] = $this->criarPersonal();

        $this->postJson('/alunos', ['name' => 'Aluno Teste'], $headers)->assertCreated();
    }

    public function test_personal_com_teste_gratis_expirado_e_sem_assinatura_e_bloqueado(): void
    {
        [, $headers] = $this->criarPersonal(diasAtras: 10);

        $this->postJson('/alunos', ['name' => 'Aluno Teste'], $headers)
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'Seu teste grátis acabou. Escolha um plano em "Meu Plano" pra continuar cadastrando alunos.']);
    }

    public function test_personal_com_assinatura_ativa_respeita_limite_do_plano(): void
    {
        [$professional, $headers] = $this->criarPersonal(diasAtras: 10);
        ProfessionalSubscription::create([
            'professional_id' => $professional->id,
            'plano_chave' => 'custom',
            'status' => ProfessionalSubscription::STATUS_ATIVA,
        ]);

        // "custom" no config de teste tem limite baixo (ver phpunit config override abaixo
        // se necessário) — usa o valor real do config, criando alunos até o limite.
        $limite = config('planos_assinatura.planos.custom.limite_alunos');
        $this->cadastrarAlunos($headers, $limite);

        $this->postJson('/alunos', ['name' => 'Aluno Extra'], $headers)
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'Você atingiu o limite de alunos do seu plano atual. Faça upgrade em "Meu Plano" pra cadastrar mais.']);
    }

    public function test_personal_atrasado_dentro_da_carencia_ainda_cadastra_aluno(): void
    {
        [$professional, $headers] = $this->criarPersonal(diasAtras: 10);
        ProfessionalSubscription::create([
            'professional_id' => $professional->id,
            'plano_chave' => 'custom',
            'status' => ProfessionalSubscription::STATUS_ATRASADA,
            'atraso_desde' => now()->subDay()->toDateString(),
        ]);

        $this->postJson('/alunos', ['name' => 'Aluno Teste'], $headers)->assertCreated();
    }

    public function test_personal_com_assinatura_bloqueada_nao_cadastra_aluno(): void
    {
        [$professional, $headers] = $this->criarPersonal(diasAtras: 10);
        ProfessionalSubscription::create([
            'professional_id' => $professional->id,
            'plano_chave' => 'custom',
            'status' => ProfessionalSubscription::STATUS_BLOQUEADA,
            'atraso_desde' => now()->subDays(10)->toDateString(),
        ]);

        $this->postJson('/alunos', ['name' => 'Aluno Teste'], $headers)
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'Sua assinatura está com pagamento pendente além do prazo de carência. Regularize em "Meu Plano" pra cadastrar novos alunos.']);
    }

    public function test_get_assinatura_devolve_status_em_teste(): void
    {
        [, $headers] = $this->criarPersonal();

        $this->getJson('/assinatura', $headers)
            ->assertOk()
            ->assertJsonPath('em_teste', true)
            ->assertJsonPath('limite_alunos', null);
    }

    public function test_comando_bloqueia_assinatura_atrasada_alem_da_carencia(): void
    {
        [$professional] = $this->criarPersonal(diasAtras: 10);
        $subscription = ProfessionalSubscription::create([
            'professional_id' => $professional->id,
            'plano_chave' => 'custom',
            'status' => ProfessionalSubscription::STATUS_ATRASADA,
            'atraso_desde' => now()->subDays(5)->toDateString(),
        ]);

        $this->artisan('assinatura:verificar-carencia');

        $this->assertSame(ProfessionalSubscription::STATUS_BLOQUEADA, $subscription->fresh()->status);
    }

    public function test_comando_nao_bloqueia_assinatura_atrasada_dentro_da_carencia(): void
    {
        [$professional] = $this->criarPersonal(diasAtras: 10);
        $subscription = ProfessionalSubscription::create([
            'professional_id' => $professional->id,
            'plano_chave' => 'custom',
            'status' => ProfessionalSubscription::STATUS_ATRASADA,
            'atraso_desde' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('assinatura:verificar-carencia');

        $this->assertSame(ProfessionalSubscription::STATUS_ATRASADA, $subscription->fresh()->status);
    }

    public function test_checkout_guarda_preapproval_id_devolvido_pelo_mercado_pago(): void
    {
        config(['services.mercado_pago.access_token' => 'token-teste']);
        Http::fake([
            'api.mercadopago.com/preapproval' => Http::response([
                'id' => 'mp-preapproval-123',
                'init_point' => 'https://mercadopago.com/checkout/mp-preapproval-123',
            ], 201),
        ]);
        [, $headers] = $this->criarPersonal();

        $this->postJson('/assinatura/checkout', ['plano_chave' => 'custom'], $headers)
            ->assertOk()
            ->assertJsonPath('checkout_url', 'https://mercadopago.com/checkout/mp-preapproval-123');

        $this->assertSame('mp-preapproval-123', ProfessionalSubscription::first()->mp_preapproval_id);
    }

    public function test_cancelar_assinatura_ativa_avisa_o_mercado_pago_e_marca_cancelada(): void
    {
        config(['services.mercado_pago.access_token' => 'token-teste']);
        Http::fake([
            'api.mercadopago.com/preapproval/mp-preapproval-123' => Http::response(['status' => 'cancelled'], 200),
        ]);
        [$professional, $headers] = $this->criarPersonal(diasAtras: 10);
        $subscription = ProfessionalSubscription::create([
            'professional_id' => $professional->id,
            'plano_chave' => 'custom',
            'status' => ProfessionalSubscription::STATUS_ATIVA,
            'mp_preapproval_id' => 'mp-preapproval-123',
            'proxima_cobranca_em' => now()->addMonth()->toDateString(),
        ]);

        $this->postJson('/assinatura/cancelar', [], $headers)->assertOk()->assertJsonPath('ok', true);

        $subscription->refresh();
        $this->assertSame(ProfessionalSubscription::STATUS_CANCELADA, $subscription->status);
        $this->assertNull($subscription->proxima_cobranca_em);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.mercadopago.com/preapproval/mp-preapproval-123'
            && $request['status'] === 'cancelled');
    }

    public function test_cancelar_sem_assinatura_devolve_erro(): void
    {
        [, $headers] = $this->criarPersonal();

        $this->postJson('/assinatura/cancelar', [], $headers)
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'Você não tem uma assinatura ativa pra cancelar.']);
    }

    public function test_cancelar_assinatura_ja_cancelada_devolve_erro(): void
    {
        [$professional, $headers] = $this->criarPersonal(diasAtras: 10);
        ProfessionalSubscription::create([
            'professional_id' => $professional->id,
            'plano_chave' => 'custom',
            'status' => ProfessionalSubscription::STATUS_CANCELADA,
        ]);

        $this->postJson('/assinatura/cancelar', [], $headers)
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'Você não tem uma assinatura ativa pra cancelar.']);
    }

    public function test_cancelar_assinatura_pendente_sem_preapproval_nao_chama_mercado_pago(): void
    {
        [$professional, $headers] = $this->criarPersonal();
        ProfessionalSubscription::create([
            'professional_id' => $professional->id,
            'plano_chave' => 'custom',
            'status' => ProfessionalSubscription::STATUS_PENDENTE,
        ]);

        Http::fake();

        $this->postJson('/assinatura/cancelar', [], $headers)->assertOk()->assertJsonPath('ok', true);

        Http::assertNothingSent();
        $this->assertSame(ProfessionalSubscription::STATUS_CANCELADA, ProfessionalSubscription::first()->status);
    }

    public function test_cancelar_devolve_erro_quando_mercado_pago_falha_e_nao_marca_local_como_cancelada(): void
    {
        config(['services.mercado_pago.access_token' => 'token-teste']);
        Http::fake([
            'api.mercadopago.com/preapproval/mp-preapproval-123' => Http::response(['error' => 'not_found'], 404),
        ]);
        [$professional, $headers] = $this->criarPersonal(diasAtras: 10);
        $subscription = ProfessionalSubscription::create([
            'professional_id' => $professional->id,
            'plano_chave' => 'custom',
            'status' => ProfessionalSubscription::STATUS_ATIVA,
            'mp_preapproval_id' => 'mp-preapproval-123',
        ]);

        $this->postJson('/assinatura/cancelar', [], $headers)->assertStatus(502);

        $this->assertSame(ProfessionalSubscription::STATUS_ATIVA, $subscription->fresh()->status);
    }

    public function test_webhook_rejeita_assinatura_invalida(): void
    {
        config(['services.mercado_pago.webhook_secret' => 'segredo-de-teste']);

        $this->postJson('/assinatura/webhook', ['type' => 'payment', 'data' => ['id' => '123']], [
            'x-signature' => 'ts=1,v1=assinatura-forjada',
            'x-request-id' => 'req-1',
        ])->assertStatus(401);
    }

    public function test_webhook_sem_data_id_nao_quebra(): void
    {
        $this->postJson('/assinatura/webhook', ['type' => 'payment'])->assertOk();
    }

    /**
     * Monta o header x-signature como o Mercado Pago monta, pra exercitar o
     * caminho de assinatura VÁLIDA (os testes acima só cobriam a rejeição).
     */
    private function assinarWebhook(string $dataId, string $requestId = 'req-1', ?int $ts = null): array
    {
        $ts ??= now()->timestamp;
        $secret = 'segredo-de-teste';
        config(['services.mercado_pago.webhook_secret' => $secret]);

        $v1 = hash_hmac('sha256', "id:{$dataId};request-id:{$requestId};ts:{$ts};", $secret);

        return ['x-signature' => "ts={$ts},v1={$v1}", 'x-request-id' => $requestId];
    }

    private function assinaturaAtivaComCobranca(string $planoChave = 'custom'): ProfessionalSubscription
    {
        [$professional] = $this->criarPersonal(diasAtras: 10);

        return ProfessionalSubscription::create([
            'professional_id' => $professional->id,
            'plano_chave' => $planoChave,
            'status' => ProfessionalSubscription::STATUS_ATIVA,
            'mp_preapproval_id' => 'mp-preapproval-123',
            'proxima_cobranca_em' => now()->toDateString(),
        ]);
    }

    public function test_webhook_de_pagamento_repetido_nao_estende_a_assinatura_duas_vezes(): void
    {
        config(['services.mercado_pago.access_token' => 'token-teste']);
        $subscription = $this->assinaturaAtivaComCobranca();
        Http::fake([
            'api.mercadopago.com/v1/payments/pay-1' => Http::response([
                'status' => 'approved',
                'transaction_amount' => 79.90,
                'preapproval_id' => 'mp-preapproval-123',
            ], 200),
        ]);

        $this->postJson('/assinatura/webhook', ['type' => 'payment', 'data' => ['id' => 'pay-1']], $this->assinarWebhook('pay-1'))
            ->assertOk();

        $cobrancaAposPrimeiro = $subscription->fresh()->proxima_cobranca_em->toDateString();

        // Reentrega do MESMO pagamento: é retry normal do Mercado Pago, não
        // pagamento novo. Não pode gravar de novo nem empurrar mais um mês.
        $this->postJson('/assinatura/webhook', ['type' => 'payment', 'data' => ['id' => 'pay-1']], $this->assinarWebhook('pay-1', 'req-2'))
            ->assertOk();

        $this->assertSame(1, $subscription->payments()->count());
        $this->assertSame($cobrancaAposPrimeiro, $subscription->fresh()->proxima_cobranca_em->toDateString());
    }

    public function test_webhook_rejeita_assinatura_valida_mas_antiga(): void
    {
        // Assinatura correta, ts de uma hora atrás: requisição capturada e
        // reenviada não pode continuar valendo pra sempre.
        $headers = $this->assinarWebhook('pay-1', 'req-1', ts: now()->subHour()->timestamp);

        $this->postJson('/assinatura/webhook', ['type' => 'payment', 'data' => ['id' => 'pay-1']], $headers)
            ->assertStatus(401);
    }

    public function test_pagamento_aprovado_com_valor_divergente_nao_ativa_assinatura(): void
    {
        config(['services.mercado_pago.access_token' => 'token-teste']);
        $subscription = $this->assinaturaAtivaComCobranca(planoChave: 'custom'); // 79.90
        $subscription->update(['status' => ProfessionalSubscription::STATUS_ATRASADA]);
        Http::fake([
            'api.mercadopago.com/v1/payments/pay-2' => Http::response([
                'status' => 'approved',
                'transaction_amount' => 1.00,
                'preapproval_id' => 'mp-preapproval-123',
            ], 200),
        ]);

        $this->postJson('/assinatura/webhook', ['type' => 'payment', 'data' => ['id' => 'pay-2']], $this->assinarWebhook('pay-2'))
            ->assertOk();

        // O dinheiro que entrou fica registrado, mas quem libera mês de
        // assinatura por valor fora do plano é uma pessoa, não o webhook.
        $this->assertSame(1, $subscription->payments()->count());
        $this->assertSame(ProfessionalSubscription::STATUS_ATRASADA, $subscription->fresh()->status);
    }
}
