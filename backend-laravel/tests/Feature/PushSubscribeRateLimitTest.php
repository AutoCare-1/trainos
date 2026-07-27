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
}
