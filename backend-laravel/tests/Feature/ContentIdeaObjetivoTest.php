<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\Professional;
use App\Support\ConteudoIdeias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * O parâmetro `objetivo` da geração de ideias de conteúdo: os dois botões da
 * tela ("Ideias de conteúdo" e "Post pra atrair aluno") mandam valores
 * diferentes, e cada um troca o system prompt.
 *
 * A chamada à IA em si não é exercitada aqui (precisaria de chave real / rede);
 * o que se testa é a validação do enum, a seleção de prompt (função pura) e o
 * fato de o objetivo ser gravado no histórico.
 */
class ContentIdeaObjetivoTest extends TestCase
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

    public function test_objetivo_invalido_e_recusado_com_422(): void
    {
        [, $headers] = $this->autenticar();

        $this->postJson('/conteudo', ['objetivo' => 'virar_influencer'], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('objetivo');

        $this->assertSame(0, ContentIdea::count());
    }

    public function test_objetivo_valido_passa_pela_validacao(): void
    {
        // Kill-switch ligado: o controller retorna 503 ANTES de tocar na IA, então
        // um 503 aqui (e não um 422) prova que 'atrair_alunos' é aceito.
        config(['ia_pipelines.ideias_conteudo' => false]);
        [, $headers] = $this->autenticar();

        $this->postJson('/conteudo', ['objetivo' => 'atrair_alunos'], $headers)
            ->assertStatus(503);
    }

    public function test_sem_objetivo_continua_valido_e_cai_no_padrao(): void
    {
        config(['ia_pipelines.ideias_conteudo' => false]);
        [, $headers] = $this->autenticar();

        $this->postJson('/conteudo', [], $headers)->assertStatus(503);
    }

    public function test_os_dois_modos_geram_system_prompts_diferentes(): void
    {
        $engajar = ConteudoIdeias::sistemaPara(ConteudoIdeias::OBJETIVO_ENGAJAR);
        $atrair = ConteudoIdeias::sistemaPara(ConteudoIdeias::OBJETIVO_ATRAIR_ALUNOS);

        $this->assertNotSame($engajar, $atrair);
        $this->assertStringContainsStringIgnoringCase('atrair aluno novo', $atrair);
        $this->assertStringContainsStringIgnoringCase('quem já acompanha', $engajar);

        // As regras comuns (formato JSON, anonimato) entram nos dois.
        foreach ([$engajar, $atrair] as $prompt) {
            $this->assertStringContainsString('array JSON válido', $prompt);
            $this->assertStringContainsString('caption_suggestion', $prompt);
        }
    }

    public function test_valor_desconhecido_cai_no_prompt_de_engajar(): void
    {
        // sistemaPara é tolerante: qualquer coisa != 'atrair_alunos' é engajar.
        // A validação do controller é quem barra valor inválido; aqui a função
        // pura só não pode quebrar.
        $this->assertSame(
            ConteudoIdeias::sistemaPara(ConteudoIdeias::OBJETIVO_ENGAJAR),
            ConteudoIdeias::sistemaPara('qualquer_coisa'),
        );
    }

    public function test_objetivo_e_gravavel_no_historico(): void
    {
        [$professional] = $this->autenticar();

        $ideia = ContentIdea::create([
            'professional_id' => $professional->id,
            'batch_id' => (string) Str::orderedUuid(),
            'objetivo' => 'atrair_alunos',
            'format' => 'reels',
            'title' => 'Teste',
            'description' => 'Teste',
            'caption_suggestion' => 'Teste',
        ]);

        $this->assertSame('atrair_alunos', $ideia->fresh()->objetivo);
    }
}
