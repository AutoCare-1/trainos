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
}
