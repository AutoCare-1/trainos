<?php

namespace Tests\Feature;

use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Publicação dos vídeos de demonstração no armazenamento de objetos.
 *
 * Nenhum teste toca storage real: Storage::fake dá um disco de mentira. Enviar
 * 340 MB de verdade num teste seria lento e cobrado.
 */
class PublicarDemonstracoesTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = '__teste-publicar__';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('midia');
        config([
            'demonstracoes.disco' => 'midia',
            'demonstracoes.base_url' => 'https://midia.exemplo/exercise-demos',
            'demonstracoes.prefixo' => 'exercise-demos',
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->caminhoNoDisco());

        parent::tearDown();
    }

    private function caminhoRelativo(): string
    {
        return '/uploads/exercise-demos/'.self::SLUG.'.mp4';
    }

    private function caminhoNoDisco(): string
    {
        return public_path(ltrim($this->caminhoRelativo(), '/'));
    }

    private function criarArquivo(): void
    {
        $dir = dirname($this->caminhoNoDisco());
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($this->caminhoNoDisco(), 'bytes-de-video');
    }

    private function exercicio(?string $videoUrl): Exercise
    {
        return Exercise::create([
            'name' => 'Exercício de teste',
            'muscle_group' => 'Peito',
            'equipment' => 'Barra',
            'video_url' => $videoUrl,
        ]);
    }

    public function test_falha_com_mensagem_quando_o_destino_nao_esta_configurado(): void
    {
        // Sem destino o comando não sabe pra onde enviar nem que URL gravar.
        // Falhar dizendo o que falta é melhor que subir pro lugar errado.
        config(['demonstracoes.disco' => null, 'demonstracoes.base_url' => '']);
        $this->criarArquivo();
        $this->exercicio($this->caminhoRelativo());

        $this->artisan('exercicios:publicar-demonstracoes')->assertFailed();
    }

    public function test_envia_e_troca_o_caminho_local_pela_url_publica(): void
    {
        $this->criarArquivo();
        $ex = $this->exercicio($this->caminhoRelativo());

        $this->artisan('exercicios:publicar-demonstracoes')->assertSuccessful();

        Storage::disk('midia')->assertExists('exercise-demos/'.self::SLUG.'.mp4');
        $this->assertSame(
            'https://midia.exemplo/exercise-demos/'.self::SLUG.'.mp4',
            $ex->fresh()->video_url
        );
    }

    public function test_dry_run_nao_envia_nada(): void
    {
        // 340 MB de upload é caro em tempo e banda: tem que dar pra conferir
        // a seleção antes.
        $this->criarArquivo();
        $ex = $this->exercicio($this->caminhoRelativo());

        $this->artisan('exercicios:publicar-demonstracoes', ['--dry-run' => true])
            ->assertSuccessful();

        Storage::disk('midia')->assertMissing('exercise-demos/'.self::SLUG.'.mp4');
        $this->assertSame($this->caminhoRelativo(), $ex->fresh()->video_url);
    }

    public function test_nao_reenvia_o_que_ja_foi_publicado(): void
    {
        // Idempotência importa: depois de gerar um vídeo novo, rodar de novo
        // não pode reenviar os 340 MB inteiros.
        $ex = $this->exercicio('https://midia.exemplo/exercise-demos/ja-publicado.mp4');

        $this->artisan('exercicios:publicar-demonstracoes')->assertSuccessful();

        $this->assertSame(
            'https://midia.exemplo/exercise-demos/ja-publicado.mp4',
            $ex->fresh()->video_url
        );
    }

    public function test_ignora_exercicio_cujo_arquivo_local_sumiu(): void
    {
        $ex = $this->exercicio('/uploads/exercise-demos/nao-existe.mp4');

        $this->artisan('exercicios:publicar-demonstracoes')
            ->expectsOutputToContain('sem o arquivo local')
            ->assertSuccessful();

        $this->assertSame('/uploads/exercise-demos/nao-existe.mp4', $ex->fresh()->video_url);
    }
}
