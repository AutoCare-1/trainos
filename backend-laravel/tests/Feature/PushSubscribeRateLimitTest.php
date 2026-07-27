<?php

namespace Tests\Feature;

use App\Models\Professional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Item 8 da revisão: POST /push/subscribe e /portal/{token}/push/subscribe agora têm throttle. */
class PushSubscribeRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_bloqueia_apos_10_tentativas_por_minuto_no_lado_do_personal(): void
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $token = auth('api')->login($professional);
        $headers = ['Authorization' => "Bearer {$token}"];

        $payload = ['endpoint' => 'https://exemplo.com/x', 'keys' => ['p256dh' => 'a', 'auth' => 'b']];

        for ($i = 0; $i < 10; $i++) {
            $status = $this->postJson('/push/subscribe', $payload, $headers)->getStatusCode();
            $this->assertNotSame(429, $status, "requisição {$i} não deveria ter sido bloqueada ainda");
        }

        $this->postJson('/push/subscribe', $payload, $headers)->assertStatus(429);
    }

    public function test_bloqueia_apos_10_tentativas_por_minuto_no_lado_do_aluno(): void
    {
        $payload = ['endpoint' => 'https://exemplo.com/y', 'keys' => ['p256dh' => 'a', 'auth' => 'b']];

        for ($i = 0; $i < 10; $i++) {
            $status = $this->postJson('/portal/token-invalido/push/subscribe', $payload)->getStatusCode();
            $this->assertNotSame(429, $status, "requisição {$i} não deveria ter sido bloqueada ainda");
        }

        $this->postJson('/portal/token-invalido/push/subscribe', $payload)->assertStatus(429);
    }

    /**
     * Item 8 de uma segunda revisão: o throttle do portal é chaveado pelo
     * token (App\Providers\AppServiceProvider), não por IP — dois alunos
     * diferentes (mesmo IP de teste) não compartilham o mesmo limite, e um
     * token vazado sendo abusado não consome a cota de outros alunos legítimos.
     */
    public function test_tokens_diferentes_tem_limites_independentes_mesmo_do_mesmo_ip(): void
    {
        $payload = ['endpoint' => 'https://exemplo.com/z', 'keys' => ['p256dh' => 'a', 'auth' => 'b']];

        // Esgota o limite do token-a.
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/portal/token-a/push/subscribe', $payload);
        }
        $this->postJson('/portal/token-a/push/subscribe', $payload)->assertStatus(429);

        // token-b não deveria ser afetado, mesmo vindo do mesmo IP de teste.
        $status = $this->postJson('/portal/token-b/push/subscribe', $payload)->getStatusCode();
        $this->assertNotSame(429, $status, 'token diferente não deveria compartilhar o limite de outro token');
    }
}
