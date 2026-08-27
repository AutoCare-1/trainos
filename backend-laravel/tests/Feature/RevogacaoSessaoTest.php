<?php

namespace Tests\Feature;

use App\Models\Professional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Revogação de sessão do personal. Antes disso não existia: com JWT_TTL de 7
 * dias e blacklist desligada, "Sair" era só apagar o localStorage do navegador
 * — quem tivesse copiado o token seguia com acesso pela semana inteira, e nem
 * trocar a senha adiantava.
 *
 * A marca d'água é professionals.tokens_valid_after: todo token emitido antes
 * dela para de valer.
 */
class RevogacaoSessaoTest extends TestCase
{
    use RefreshDatabase;

    private function criarPersonalLogado(): array
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $token = auth('api')->login($professional);

        return [$professional, ['Authorization' => "Bearer {$token}"]];
    }

    public function test_token_continua_valendo_enquanto_ninguem_revoga(): void
    {
        [, $headers] = $this->criarPersonalLogado();

        $this->getJson('/auth/me', $headers)->assertOk();
    }

    public function test_logout_derruba_o_proprio_token(): void
    {
        [, $headers] = $this->criarPersonalLogado();

        $this->postJson('/auth/logout', [], $headers)->assertOk()->assertJsonPath('ok', true);

        $this->getJson('/auth/me', $headers)->assertStatus(401);
    }

    public function test_logout_derruba_tambem_a_sessao_de_outro_aparelho(): void
    {
        [$professional, $headersCelular] = $this->criarPersonalLogado();
        // Mesma conta logada em outro lugar — é justamente o token que se quer
        // matar quando se desconfia de acesso indevido.
        $outro = auth('api')->login($professional);
        $headersComputador = ['Authorization' => "Bearer {$outro}"];

        $this->postJson('/auth/logout', [], $headersCelular)->assertOk();

        $this->getJson('/auth/me', $headersComputador)->assertStatus(401);
    }

    public function test_revogacao_de_um_personal_nao_derruba_o_outro(): void
    {
        [, $headersA] = $this->criarPersonalLogado();
        [, $headersB] = $this->criarPersonalLogado();

        $this->postJson('/auth/logout', [], $headersA)->assertOk();

        $this->getJson('/auth/me', $headersB)->assertOk();
    }

    public function test_login_novo_depois_do_logout_volta_a_funcionar(): void
    {
        [$professional, $headers] = $this->criarPersonalLogado();
        $this->postJson('/auth/logout', [], $headers)->assertOk();

        // Um segundo à frente porque a revogação fecha o segundo inteiro (ver
        // JwtAuthenticate::foiRevogado). No uso real esse tempo passa sozinho:
        // entre o logout e o login novo tem redirect e senha sendo digitada.
        $this->travel(1)->second();

        $novo = $this->postJson('/auth/login', [
            'email' => $professional->email,
            'password' => 'senha12345',
        ])->assertOk()->json('token');

        $this->getJson('/auth/me', ['Authorization' => "Bearer {$novo}"])->assertOk();
    }

    public function test_banco_fora_do_ar_na_checagem_nao_derruba_quem_ja_esta_logado(): void
    {
        [, $headers] = $this->criarPersonalLogado();

        // O middleware faz UMA consulta (a marca d'água). Se ela explodir, a
        // decisão tem que ser "nada revogado" — derrubar todo mundo porque o
        // banco soluçou seria pior que o problema que a revogação resolve.
        Cache::shouldReceive('remember')->andThrow(new \RuntimeException('banco fora do ar'));

        $this->getJson('/auth/me', $headers)->assertOk();
    }
}
