<?php

namespace Tests\Feature;

use App\Models\ConsultorIaMessage;
use App\Models\Professional;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Consultor IA e Ideias de Conteúdo não podem derrubar o fluxo com 500 puro
 * se a API da Anthropic falhar (chave ausente/inválida, serviço fora do ar,
 * JSON malformado) — regra geral do projeto: toda chamada de IA tem fallback
 * gracioso. Força a falha zerando a api_key (RuntimeException síncrona, sem
 * precisar de chamada de rede real).
 */
class FallbackIaTest extends TestCase
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

    public function test_consultor_ia_devolve_erro_amigavel_sem_perder_a_pergunta_do_personal(): void
    {
        [$professional, $headers] = $this->autenticar();
        config(['services.anthropic.api_key' => null]);

        $response = $this->postJson('/consultor-ia/chat', ['content' => 'quantos alunos ativos eu tenho?'], $headers);

        $response->assertStatus(503);
        $this->assertSame('quantos alunos ativos eu tenho?', $response->json('message.content'));

        // A pergunta do personal foi persistida mesmo com a IA fora do ar.
        $this->assertDatabaseCount('consultor_ia_messages', 1);
        $this->assertDatabaseHas('consultor_ia_messages', [
            'professional_id' => $professional->id,
            'role' => 'personal',
        ]);
    }

    public function test_gerar_ideias_de_conteudo_devolve_erro_amigavel_em_vez_de_500(): void
    {
        [, $headers] = $this->autenticar();
        config(['services.anthropic.api_key' => null]);

        $response = $this->postJson('/conteudo', ['direcionamento' => 'foco em emagrecimento'], $headers);

        $response->assertStatus(503);
        $this->assertDatabaseCount('content_ideas', 0);
    }
}
