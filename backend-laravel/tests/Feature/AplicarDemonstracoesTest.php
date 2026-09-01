<?php

namespace Tests\Feature;

use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ligação entre exercício e o vídeo de demonstração que está no disco.
 *
 * O caso que motivou tudo: quem clona o repositório não recebe os .mp4 (340 MB,
 * /public/uploads está no .gitignore) nem os caminhos (viviam só na coluna
 * video_url do banco de quem gerou). O mapeamento versionado resolve a segunda
 * metade — e não pode, ao resolvê-la, apontar pra arquivo que não chegou.
 *
 * O teste usa mapa e nomes próprios, nunca a lista real. A primeira versão dele
 * usava, e pra exercitar "o arquivo não chegou" acabou sobrescrevendo e
 * apagando o vídeo do farmer walk — que teve de ser regerado.
 */
class AplicarDemonstracoesTest extends TestCase
{
    use RefreshDatabase;

    /** Prefixo que nenhum vídeo real usa, pra não haver colisão de nome. */
    private const SLUG = '__teste-aplicar-demonstracoes__';

    private string $mapa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapa = sys_get_temp_dir().'/mapa-'.uniqid().'.php';
    }

    protected function tearDown(): void
    {
        @unlink($this->mapa);
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

    private function escreverMapa(array $mapa): void
    {
        file_put_contents($this->mapa, '<?php return '.var_export($mapa, true).';');
    }

    private function criarArquivo(): void
    {
        $dir = dirname($this->caminhoNoDisco());
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($this->caminhoNoDisco(), 'bytes-de-video');
    }

    private function exercicio(string $nome): Exercise
    {
        return Exercise::create([
            'name' => $nome,
            'muscle_group' => 'Peito',
            'equipment' => 'Barra',
        ]);
    }

    public function test_nao_aponta_para_arquivo_que_nao_chegou(): void
    {
        // Apontar pra arquivo inexistente é pior que não apontar: a tela mostra
        // um player quebrado em vez do fallback de "sem demonstração", e quem
        // clonou não descobre que faltou copiar os vídeos.
        $ex = $this->exercicio('Exercício de teste');
        $this->escreverMapa(['Exercício de teste' => $this->caminhoRelativo()]);

        $this->artisan('exercicios:aplicar-demonstracoes', ['--arquivo' => $this->mapa])
            ->assertSuccessful();

        $this->assertNull($ex->fresh()->video_url);
    }

    public function test_avisa_quando_faltam_os_arquivos(): void
    {
        // O aviso é o que transforma "não aparece vídeo" em "faltou copiar a
        // pasta". Sem ele, o sintoma parece bug do app.
        $this->exercicio('Exercício de teste');
        $this->escreverMapa(['Exercício de teste' => $this->caminhoRelativo()]);

        $this->artisan('exercicios:aplicar-demonstracoes', ['--arquivo' => $this->mapa])
            ->expectsOutputToContain('sem o arquivo em public/uploads')
            ->assertSuccessful();
    }

    public function test_liga_o_exercicio_quando_o_arquivo_existe(): void
    {
        $ex = $this->exercicio('Exercício de teste');
        $this->escreverMapa(['Exercício de teste' => $this->caminhoRelativo()]);
        $this->criarArquivo();

        $this->artisan('exercicios:aplicar-demonstracoes', ['--arquivo' => $this->mapa])
            ->assertSuccessful();

        $this->assertSame($this->caminhoRelativo(), $ex->fresh()->video_url);
    }

    public function test_url_absoluta_nao_depende_de_arquivo_local(): void
    {
        // O ponto do storage de objetos: quem clona recebe a URL pelo git e não
        // precisa baixar os 340 MB. Se o comando exigisse arquivo local aqui,
        // recusaria justamente o caso que resolve o problema.
        $ex = $this->exercicio('Exercício de teste');
        $this->escreverMapa([
            'Exercício de teste' => 'https://midia.exemplo/exercise-demos/teste.mp4',
        ]);

        $this->artisan('exercicios:aplicar-demonstracoes', ['--arquivo' => $this->mapa])
            ->assertSuccessful();

        $this->assertSame(
            'https://midia.exemplo/exercise-demos/teste.mp4',
            $ex->fresh()->video_url
        );
    }

    public function test_avisa_quando_o_nome_do_mapa_nao_existe_na_biblioteca(): void
    {
        $this->escreverMapa(['Exercício que ninguém cadastrou' => $this->caminhoRelativo()]);

        $this->artisan('exercicios:aplicar-demonstracoes', ['--arquivo' => $this->mapa])
            ->expectsOutputToContain('provável renomeação')
            ->assertSuccessful();
    }

    public function test_todo_nome_do_mapa_real_existe_na_biblioteca(): void
    {
        // Renomear um exercício sem atualizar o mapa deixa o vídeo órfão: o
        // arquivo está lá, o exercício está lá, e nada os liga. Este é o único
        // teste que lê a lista real — e só lê.
        $this->seed(\Database\Seeders\ExerciseSeeder::class);
        $this->seed(\Database\Seeders\ExercicioBibliotecaAmpliadaSeeder::class);
        // A leva complementar entrou em 31/08 e 131 dela já têm vídeo — sem
        // semear aqui, o mapa real aparece inteiro como "exercício que não
        // existe" e o teste acusa órfão onde não há.
        $this->seed(\Database\Seeders\ExercicioBibliotecaComplementarSeeder::class);

        $doMapa = array_keys(require database_path('demonstracoes_geradas.php'));
        $existentes = Exercise::whereIn('name', $doMapa)->pluck('name')->all();

        $this->assertSame([], array_values(array_diff($doMapa, $existentes)));
    }
}
