<?php

namespace Tests\Feature;

use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A lista numerada que o personal usa pra apontar vídeo errado.
 *
 * O contrato que o personal e o Filipe combinaram: número em ordem crescente,
 * sem buraco, agrupado na ordem da tela e alfabético dentro do grupo. Nunca
 * entra número no meio sem o resto andar junto. Estes testes travam isso.
 */
class NumerarBibliotecaTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        // Diretório próprio: sem isto o comando reescreveria o
        // database/biblioteca_numerada.{md,json} de verdade com as 2-3 linhas
        // do banco de teste — o mesmo tipo de acidente que já apagou um vídeo
        // real no teste de aplicar-demonstracoes.
        $this->dir = sys_get_temp_dir().'/numerar-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir.'/biblioteca_numerada.md');
        @unlink($this->dir.'/biblioteca_numerada.json');
        @rmdir($this->dir);

        parent::tearDown();
    }

    private function criar(string $nome, string $grupo, bool $comVideo = true): Exercise
    {
        return Exercise::create([
            'name' => $nome,
            'muscle_group' => $grupo,
            'equipment' => 'Barra',
            'video_url' => $comVideo ? '/uploads/exercise-demos/x.mp4' : null,
        ]);
    }

    /** @return array<int, array{numero:int, nome:string, grupo:string}> */
    private function gerarJson(): array
    {
        $this->artisan('exercicios:numerar-biblioteca', ['--dir' => $this->dir])->assertSuccessful();

        return json_decode(file_get_contents($this->dir.'/biblioteca_numerada.json'), true)['itens'];
    }

    public function test_numeracao_e_crescente_sem_buraco_comecando_em_1(): void
    {
        $this->criar('Supino reto', 'Peito');
        $this->criar('Agachamento livre', 'Pernas');
        $this->criar('Rosca direta', 'Bíceps');

        $numeros = array_column($this->gerarJson(), 'numero');

        $this->assertSame(range(1, count($numeros)), $numeros);
    }

    public function test_grupos_seguem_a_ordem_da_tela_nao_alfabetica(): void
    {
        // Alfabética jogaria Bíceps e Core na frente de Peito — a tela usa a
        // ordem de quem monta treino, e a lista tem que bater com a tela.
        $this->criar('Rosca direta', 'Bíceps');
        $this->criar('Prancha', 'Core');
        $this->criar('Supino reto', 'Peito');
        $this->criar('Agachamento', 'Pernas');

        $grupos = array_values(array_unique(array_column($this->gerarJson(), 'grupo')));

        // ORDEM_GRUPOS: Peito, Costas, Ombros, Bíceps, Tríceps, ..., Pernas, ..., Core
        $this->assertSame(['Peito', 'Bíceps', 'Pernas', 'Core'], $grupos);
    }

    public function test_dentro_do_grupo_e_alfabetico_ignorando_acento(): void
    {
        $this->criar('Última variação', 'Peito');
        $this->criar('Abdução no peck', 'Peito');
        $this->criar('Édipo (nome só pra acento)', 'Peito');

        $nomes = array_column($this->gerarJson(), 'nome');

        $this->assertSame(
            ['Abdução no peck', 'Édipo (nome só pra acento)', 'Última variação'],
            $nomes
        );
    }

    public function test_exercicio_novo_em_grupo_anterior_empurra_os_numeros_seguintes(): void
    {
        // O caso que o Filipe descreveu: adiciono um de Peito depois, e um de
        // Mobilidade que já existia tem que mudar de número.
        $this->criar('Supino reto', 'Peito');
        $this->criar('Gato e camelo', 'Mobilidade');

        $antes = collect($this->gerarJson())->firstWhere('nome', 'Gato e camelo')['numero'];

        $this->criar('Crucifixo novo', 'Peito');

        $depois = collect($this->gerarJson())->firstWhere('nome', 'Gato e camelo')['numero'];

        $this->assertSame($antes + 1, $depois);
    }

    public function test_check_falha_quando_o_arquivo_esta_desatualizado(): void
    {
        $this->criar('Supino reto', 'Peito');
        $this->artisan('exercicios:numerar-biblioteca', ['--dir' => $this->dir])->assertSuccessful();

        $this->criar('Agachamento', 'Pernas');

        $this->artisan('exercicios:numerar-biblioteca', ['--dir' => $this->dir, '--check' => true])->assertFailed();
    }

    public function test_check_passa_logo_apos_gerar(): void
    {
        $this->criar('Supino reto', 'Peito');

        $this->artisan('exercicios:numerar-biblioteca', ['--dir' => $this->dir])->assertSuccessful();
        $this->artisan('exercicios:numerar-biblioteca', ['--dir' => $this->dir, '--check' => true])->assertSuccessful();
    }

    public function test_conta_exercicio_sem_video_mas_marca_como_sem(): void
    {
        $this->criar('Com vídeo', 'Peito', comVideo: true);
        $this->criar('Sem vídeo', 'Peito', comVideo: false);

        $itens = $this->gerarJson();

        $this->assertCount(2, $itens);
        $this->assertFalse(collect($itens)->firstWhere('nome', 'Sem vídeo')['tem_video']);
    }
}
