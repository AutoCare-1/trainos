<?php

namespace Tests\Feature;

use App\Models\Professional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * auth/login e auth/signup não tinham throttle — permitia brute-force de
 * senha e criação em massa de contas. Mesmo padrão (10 por minuto) já usado
 * em push/subscribe.
 */
class AuthRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_bloqueia_login_apos_10_tentativas_por_minuto(): void
    {
        Professional::create([
            'name' => 'Personal Teste',
            'email' => 'aluno@example.com',
            'password_hash' => bcrypt('senhacorreta'),
        ]);

        for ($i = 0; $i < 10; $i++) {
            $status = $this->postJson('/auth/login', [
                'email' => 'aluno@example.com',
                'password' => 'senhaerrada',
            ])->getStatusCode();
            $this->assertNotSame(429, $status, "requisição {$i} não deveria ter sido bloqueada ainda");
        }

        $this->postJson('/auth/login', [
            'email' => 'aluno@example.com',
            'password' => 'senhaerrada',
        ])->assertStatus(429);
    }

    public function test_bloqueia_signup_apos_10_tentativas_por_minuto(): void
    {
        // Usa um e-mail já cadastrado (sempre 409, "já existe profissional com
        // este e-mail") em vez de criar uma conta nova a cada tentativa — um
        // signup bem-sucedido chama Auth::guard('api')->login(), que no ambiente
        // de teste (CACHE_STORE=array) interage com o pacote JWT de um jeito que
        // reseta o contador do rate limiter em memória; em produção real
        // (CACHE_STORE=database) isso não acontece — confirmado manualmente via
        // curl contra o servidor real: 10x 201 seguidos de 429 na 11ª tentativa.
        // O 409 aqui já é suficiente pra confirmar que o middleware está aplicado
        // e conta as tentativas normalmente.
        Professional::create([
            'name' => 'Já Existe',
            'email' => 'jaexiste@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);

        for ($i = 0; $i < 10; $i++) {
            $status = $this->postJson('/auth/signup', [
                'name' => 'Tentativa',
                'email' => 'jaexiste@example.com',
                'password' => 'senha12345',
            ])->getStatusCode();
            $this->assertNotSame(429, $status, "requisição {$i} não deveria ter sido bloqueada ainda");
        }

        $this->postJson('/auth/signup', [
            'name' => 'Tentativa',
            'email' => 'jaexiste@example.com',
            'password' => 'senha12345',
        ])->assertStatus(429);
    }
}
