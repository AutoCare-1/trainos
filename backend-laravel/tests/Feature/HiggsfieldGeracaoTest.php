<?php

namespace Tests\Feature;

use App\Console\Commands\GerarDemonstracaoExercicio;
use App\Models\Exercise;
use App\Support\Higgsfield;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Geração de demonstração de exercício via Higgsfield.
 *
 * Tudo com Http::fake: nenhum teste toca a API real nem gasta crédito — a
 * geração é paga por chamada.
 */
class HiggsfieldGeracaoTest extends TestCase
{
    use RefreshDatabase;

    /** Diretório de referências só deste teste — nunca o real. */
    private string $dirReferencias;

    protected function setUp(): void
    {
        parent::setUp();

        // Aponta pra um diretório temporário vazio de propósito: se o teste
        // lesse o diretório real, passaria por causa das fotos que existem na
        // máquina de quem roda e quebraria no CI (foi exatamente o que
        // aconteceu na primeira versão deste arquivo).
        $this->dirReferencias = sys_get_temp_dir().'/higgsfield-teste-'.uniqid();
        mkdir($this->dirReferencias, 0777, true);

        config([
            'higgsfield.key_id' => 'id-de-teste',
            'higgsfield.key_secret' => 'segredo-de-teste',
            'higgsfield.habilitado' => true,
            'higgsfield.soul_id' => null,
            'higgsfield.poll.intervalo_segundos' => 0,
            'higgsfield.dir_referencias' => $this->dirReferencias,
        ]);
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dirReferencias.'/*') ?: [] as $f) {
            unlink($f);
        }
        @rmdir($this->dirReferencias);

        parent::tearDown();
    }

    private function criarFotoDeReferencia(string $nome = 'ref_01.jpg'): void
    {
        file_put_contents($this->dirReferencias.'/'.$nome, 'bytes-de-foto');
    }

    private function exercicioSemImagem(array $attrs = []): Exercise
    {
        return Exercise::create(array_merge([
            'name' => 'Rosca spider',
            'muscle_group' => 'Bíceps',
            'equipment' => 'Barra W',
            'instructions' => 'Peito apoiado no banco inclinado, braços na vertical.',
        ], $attrs));
    }

    // ---- Cliente ---------------------------------------------------------

    public function test_upload_usa_url_pre_assinada_e_nao_vaza_a_credencial(): void
    {
        // A doc do Higgsfield é explícita: não mandar a credencial deles pro
        // storage pré-assinado. Vazar a chave num host de terceiro daria acesso
        // à conta inteira, então isso é travado por teste.
        Http::fake([
            '*/files/generate-upload-url' => Http::response([
                'upload_url' => 'https://storage.exemplo/put/abc?assinatura=xyz',
                'public_url' => 'https://cdn.exemplo/abc.jpg',
            ]),
            'storage.exemplo/*' => Http::response('', 200),
        ]);

        $arquivo = tempnam(sys_get_temp_dir(), 'ref').'.jpg';
        file_put_contents($arquivo, 'conteudo-fake');

        $url = Higgsfield::enviarArquivo($arquivo);

        $this->assertSame('https://cdn.exemplo/abc.jpg', $url);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'generate-upload-url')
            && $r->hasHeader('Authorization', 'Key id-de-teste:segredo-de-teste'));

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'storage.exemplo')
            && ! $r->hasHeader('Authorization'));

        unlink($arquivo);
    }

    public function test_sem_soul_id_usa_o_endpoint_de_referencia_por_foto(): void
    {
        Http::fake(['*' => Http::response(['request_id' => 'req-1', 'status' => 'queued'])]);

        Higgsfield::gerarDemonstracao('prompt qualquer', ['https://cdn.exemplo/a.jpg']);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/higgsfield-ai/soul/reference')
            && $r['image_reference_url'] === 'https://cdn.exemplo/a.jpg');
    }

    public function test_com_soul_id_configurado_troca_para_o_endpoint_de_personagem(): void
    {
        // Treinar um Soul ID só dá pra fazer pelo site (não há endpoint público).
        // Quando alguém treinar e colar o id no .env, o código tem que passar a
        // usar sozinho o endpoint de identidade consistente.
        config(['higgsfield.soul_id' => 'soul-abc']);
        Http::fake(['*' => Http::response(['request_id' => 'req-2', 'status' => 'queued'])]);

        Higgsfield::gerarDemonstracao('prompt qualquer', ['https://cdn.exemplo/a.jpg']);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/higgsfield-ai/soul/character')
            && $r['custom_reference_id'] === 'soul-abc');
    }

    public function test_video_manda_todas_as_referencias_de_uma_vez(): void
    {
        Http::fake(['*' => Http::response(['request_id' => 'req-3', 'status' => 'queued'])]);

        Higgsfield::gerarDemonstracao('prompt', ['https://cdn/a.jpg', 'https://cdn/b.jpg'], video: true);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/veo3.1/reference-to-video')
            && $r['image_urls'] === ['https://cdn/a.jpg', 'https://cdn/b.jpg']);
    }

    public function test_kill_switch_impede_a_geracao(): void
    {
        config(['higgsfield.habilitado' => false]);
        Http::fake();

        $this->expectException(\RuntimeException::class);
        Higgsfield::gerarDemonstracao('prompt', ['https://cdn/a.jpg']);
    }

    public function test_aguardar_consulta_ate_o_estado_terminal(): void
    {
        Http::fakeSequence()
            ->push(['status' => 'queued', 'request_id' => 'r'])
            ->push(['status' => 'processing', 'request_id' => 'r'])
            ->push(['status' => 'completed', 'request_id' => 'r', 'images' => [['url' => 'https://cdn/final.jpg']]]);

        $resposta = Higgsfield::aguardar('r');

        $this->assertSame('completed', $resposta['status']);
        $this->assertSame('https://cdn/final.jpg', Higgsfield::urlDoProduto($resposta));
    }

    public function test_url_do_produto_le_imagem_ou_video(): void
    {
        $this->assertSame('https://cdn/v.mp4', Higgsfield::urlDoProduto(['video' => ['url' => 'https://cdn/v.mp4']]));
        $this->assertNull(Higgsfield::urlDoProduto(['status' => 'failed']));
    }

    // ---- Comando ---------------------------------------------------------

    public function test_dry_run_nao_chama_a_api_nem_sem_credencial(): void
    {
        // Custa dinheiro por chamada: precisa dar pra revisar a seleção e os
        // prompts de graça, inclusive antes de ter credencial.
        config(['higgsfield.key_id' => null, 'higgsfield.key_secret' => null]);
        Http::fake();
        $this->exercicioSemImagem();

        $this->artisan('exercicios:gerar-demonstracao', ['--dry-run' => true])
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_sem_credencial_falha_com_mensagem_em_vez_de_estourar(): void
    {
        config(['higgsfield.key_id' => null, 'higgsfield.key_secret' => null]);
        $this->exercicioSemImagem();

        $this->artisan('exercicios:gerar-demonstracao')->assertFailed();
    }

    public function test_gera_baixa_e_grava_o_caminho_local_no_exercicio(): void
    {
        // A URL do CDN deles expira em ~7 dias: guardar a URL remota daria
        // biblioteca com link quebrado. Tem que baixar e servir do nosso lado.
        $ex = $this->exercicioSemImagem();
        $this->criarFotoDeReferencia();

        Http::fake([
            '*/files/generate-upload-url' => Http::response([
                'upload_url' => 'https://storage.exemplo/put/1',
                'public_url' => 'https://cdn.exemplo/ref1.jpg',
            ]),
            'storage.exemplo/*' => Http::response('', 200),
            '*/higgsfield-ai/soul/reference' => Http::response(['request_id' => 'req-9', 'status' => 'queued']),
            '*/requests/req-9/status' => Http::response([
                'status' => 'completed',
                'request_id' => 'req-9',
                'images' => [['url' => 'https://cdn.exemplo/gerada.jpg']],
            ]),
            'cdn.exemplo/gerada.jpg' => Http::response('bytes-da-imagem', 200),
        ]);

        $this->artisan('exercicios:gerar-demonstracao', ['--limite' => 1])->assertSuccessful();

        $ex->refresh();
        $this->assertSame('/storage/exercise-demos/rosca-spider.jpg', $ex->image_url);
        Storage::disk('public')->assertExists('exercise-demos/rosca-spider.jpg');
        // Precisa ficar claro que é imagem sintética, não foto real de acervo.
        $this->assertStringContainsString('gerada por IA', $ex->image_credit);
    }

    public function test_uma_falha_nao_derruba_o_lote_inteiro(): void
    {
        $a = $this->exercicioSemImagem(['name' => 'Exercicio A']);
        $b = $this->exercicioSemImagem(['name' => 'Exercicio B']);
        $this->criarFotoDeReferencia();

        Http::fake([
            '*/files/generate-upload-url' => Http::response([
                'upload_url' => 'https://storage.exemplo/put/1',
                'public_url' => 'https://cdn.exemplo/ref1.jpg',
            ]),
            'storage.exemplo/*' => Http::response('', 200),
            '*/higgsfield-ai/soul/reference' => Http::sequence()
                ->push(['request_id' => 'req-a', 'status' => 'queued'])
                ->push(['request_id' => 'req-b', 'status' => 'queued']),
            '*/requests/req-a/status' => Http::response(['status' => 'nsfw', 'request_id' => 'req-a']),
            '*/requests/req-b/status' => Http::response([
                'status' => 'completed', 'request_id' => 'req-b',
                'images' => [['url' => 'https://cdn.exemplo/b.jpg']],
            ]),
            'cdn.exemplo/b.jpg' => Http::response('bytes', 200),
        ]);

        $this->artisan('exercicios:gerar-demonstracao', ['--limite' => 2])->assertFailed();

        $this->assertNull($a->fresh()->image_url, 'o que falhou não pode ficar com imagem');
        $this->assertNotNull($b->fresh()->image_url, 'o seguinte precisa ter sido processado mesmo assim');
    }

    public function test_nao_reprocessa_exercicio_que_ja_tem_imagem(): void
    {
        // Os 75 originais têm foto real de acervo (wger, CC-BY-SA). Regerar em
        // cima deles seria trocar foto real por imagem sintética, e ainda pagar
        // por isso.
        $comFoto = $this->exercicioSemImagem([
            'name' => 'Supino reto',
            'image_url' => '/exercise-photos/supino-reto.png',
        ]);
        Http::fake();

        $this->artisan('exercicios:gerar-demonstracao', ['--dry-run' => true, '--limite' => 50])
            ->doesntExpectOutputToContain('Supino reto')
            ->assertSuccessful();

        $this->assertSame('/exercise-photos/supino-reto.png', $comFoto->fresh()->image_url);
    }

    // ---- Prompt ----------------------------------------------------------

    public function test_prompt_descarta_prescricao_de_serie_e_mantem_postura(): void
    {
        $this->assertNull(GerarDemonstracaoExercicio::instrucaoVisual(
            '7 reps na metade inferior, 7 na superior e 7 completas.'
        ));
        $this->assertNull(GerarDemonstracaoExercicio::instrucaoVisual(
            'Sustente a posição pelo tempo prescrito.'
        ));
        $this->assertSame(
            'Cotovelos sob os ombros, corpo em linha reta da cabeça ao calcanhar.',
            GerarDemonstracaoExercicio::instrucaoVisual(
                'Cotovelos sob os ombros, corpo em linha reta da cabeça ao calcanhar.'
            )
        );
    }

    public function test_prompt_cita_o_exercicio_e_o_equipamento(): void
    {
        $prompt = GerarDemonstracaoExercicio::montarPrompt($this->exercicioSemImagem());

        $this->assertStringContainsString('Rosca spider', $prompt);
        $this->assertStringContainsString('Barra W', $prompt);
        $this->assertStringContainsString('referência', $prompt);
    }
}
