<?php

namespace Tests\Feature;

use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Numeração congelada da tela de vídeos (lista do Hugo de 13/10/2026).
 *
 * O que o teste protege: (1) retirar um exercício deixa o número vago e NÃO
 * desloca os seguintes — o 401 continua 401; (2) renomear (inclusive em cadeia,
 * e inclusive quando o rename é bloqueado) não perde nem troca o número;
 * (3) número gravado nunca é trocado; (4) roda em todo deploy, então é
 * idempotente; (5) nunca dois exercícios com o mesmo número.
 *
 * Usa mapas próprios, nunca os arquivos reais, pra não quebrar quando eles
 * mudarem.
 */
class CongelarNumeracaoTest extends TestCase
{
    use RefreshDatabase;

    private string $mapa;

    private string $renames;

    private string $vagos;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapa = sys_get_temp_dir().'/numeracao-'.uniqid().'.php';
        $this->renames = sys_get_temp_dir().'/renames-'.uniqid().'.php';
        $this->vagos = sys_get_temp_dir().'/vagos-'.uniqid().'.php';
        $this->escrever($this->mapa, [
            399 => 'Exercício A',
            400 => 'Exercício B',
            401 => 'Exercício C',
            402 => 'Exercício D',
        ]);
        $this->escrever($this->renames, []);
        $this->escrever($this->vagos, []);
    }

    protected function tearDown(): void
    {
        @unlink($this->mapa);
        @unlink($this->renames);
        @unlink($this->vagos);
        parent::tearDown();
    }

    private function escrever(string $arquivo, array $m): void
    {
        file_put_contents($arquivo, '<?php return '.var_export($m, true).';');
    }

    private function criar(string $nome): Exercise
    {
        return Exercise::create(['name' => $nome, 'muscle_group' => 'Peito']);
    }

    private function congelar(bool $antesDoRename = false): void
    {
        $this->artisan('exercicios:congelar-numeracao', [
            '--mapa' => $this->mapa,
            '--renames' => $this->renames,
            '--vagos' => $this->vagos,
            '--antes-do-rename' => $antesDoRename,
        ])->assertSuccessful();
    }

    public function test_grava_o_numero_da_lista(): void
    {
        $a = $this->criar('Exercício A');
        $d = $this->criar('Exercício D');

        $this->congelar();

        $this->assertSame(399, $a->fresh()->numero_revisao);
        $this->assertSame(402, $d->fresh()->numero_revisao);
    }

    public function test_retirar_um_deixa_o_buraco_e_nao_desloca_os_seguintes(): void
    {
        $this->criar('Exercício A');
        $b = $this->criar('Exercício B');
        $c = $this->criar('Exercício C');
        $d = $this->criar('Exercício D');
        $this->congelar(antesDoRename: true);

        $b->delete();
        $this->congelar(antesDoRename: true);
        $this->congelar();

        $this->assertSame(401, $c->fresh()->numero_revisao, 'o 401 tem de continuar 401');
        $this->assertSame(402, $d->fresh()->numero_revisao);
        $this->assertFalse(Exercise::where('numero_revisao', 400)->exists(), 'o 400 fica vago');
    }

    public function test_renomear_em_cadeia_nao_perde_o_numero(): void
    {
        $c = $this->criar('Exercício C');
        $this->congelar(antesDoRename: true);

        // C foi renomeado duas vezes depois da lista: C -> C2 -> C3.
        $this->escrever($this->renames, ['Exercício C' => 'Exercício C2', 'Exercício C2' => 'Exercício C3']);
        $c->update(['name' => 'Exercício C3']);
        $this->congelar(antesDoRename: true);
        $this->congelar();

        $this->assertSame(401, $c->fresh()->numero_revisao);
    }

    public function test_banco_novo_ja_com_nome_novo_recebe_o_numero(): void
    {
        // Banco criado do zero: o seeder já cria com o nome novo, e o passo de
        // antes do rename não acha nada (a tabela ainda estava vazia).
        $this->escrever($this->renames, ['Exercício B' => 'Exercício B novo']);
        $b = $this->criar('Exercício B novo');

        $this->congelar();

        $this->assertSame(400, $b->fresh()->numero_revisao);
    }

    /**
     * O caso #256 -> #253 da lista real: B sai e passa o nome a C. No deploy, o
     * passo de antes do rename numera cada um pelo nome exato; depois o rename
     * apaga B e dá o nome "Exercício B" à linha de C.
     */
    public function test_nome_passado_adiante_nao_leva_o_numero_de_quem_saiu(): void
    {
        $b = $this->criar('Exercício B');
        $c = $this->criar('Exercício C');
        $this->escrever($this->renames, ['Exercício C' => 'Exercício B']);
        $this->escrever($this->vagos, [400]);
        $this->congelar(antesDoRename: true);

        $b->delete();
        $c->update(['name' => 'Exercício B']);
        $this->congelar(antesDoRename: true);
        $this->congelar();

        $this->assertSame(401, $c->fresh()->numero_revisao, 'quem herdou o nome fica com o PRÓPRIO número');
        $this->assertFalse(Exercise::where('numero_revisao', 400)->exists(), 'o 400 fica vago');
    }

    /** O mesmo caso em banco novo: só o passo de depois do rename consegue numerar. */
    public function test_nome_passado_adiante_em_banco_novo(): void
    {
        $this->escrever($this->renames, ['Exercício C' => 'Exercício B']);
        $this->escrever($this->vagos, [400]);

        // Ordem real num banco novo: o passo de antes do rename roda logo depois
        // da migration, com a tabela ainda vazia; os seeders criam depois.
        $this->congelar(antesDoRename: true);
        $c = $this->criar('Exercício B'); // o seeder já nasce com o nome herdado
        $this->congelar();

        $this->assertSame(401, $c->fresh()->numero_revisao);
        $this->assertFalse(Exercise::where('numero_revisao', 400)->exists());
    }

    /**
     * Rename bloqueado (o B está no treino de um aluno e não pode sair): os dois
     * continuam, cada um com o próprio número.
     */
    public function test_rename_bloqueado_cada_um_fica_com_o_seu_numero(): void
    {
        $b = $this->criar('Exercício B');
        $c = $this->criar('Exercício C');
        $this->escrever($this->renames, ['Exercício C' => 'Exercício B']);
        $this->escrever($this->vagos, [400]);

        $this->congelar(antesDoRename: true);
        // o rename não aconteceu: B e C seguem com os nomes da lista
        $this->congelar();

        $this->assertSame(400, $b->fresh()->numero_revisao);
        $this->assertSame(401, $c->fresh()->numero_revisao);
    }

    public function test_numero_gravado_nunca_e_trocado(): void
    {
        $intruso = $this->criar('Outro exercício');
        $intruso->forceFill(['numero_revisao' => 399])->save();
        $a = $this->criar('Exercício A');

        $this->congelar(antesDoRename: true);
        $this->congelar();

        $this->assertSame(399, $intruso->fresh()->numero_revisao, 'número gravado não é tirado de ninguém');
        $this->assertNull($a->fresh()->numero_revisao, 'e não é dado a um segundo exercício');
    }

    public function test_e_idempotente(): void
    {
        $a = $this->criar('Exercício A');
        $this->congelar(antesDoRename: true);

        $this->artisan('exercicios:congelar-numeracao', ['--mapa' => $this->mapa, '--renames' => $this->renames, '--vagos' => $this->vagos])
            ->expectsOutputToContain('a gravar:                       0')
            ->assertSuccessful();

        $this->assertSame(399, $a->fresh()->numero_revisao);
    }

    public function test_dois_numeros_no_mesmo_exercicio_fica_o_primeiro(): void
    {
        $this->escrever($this->mapa, [10 => 'Exercício A', 20 => 'Exercício A']);
        $a = $this->criar('Exercício A');

        $this->congelar();

        $this->assertSame(10, $a->fresh()->numero_revisao);
    }

    public function test_dry_run_nao_altera_nada(): void
    {
        $a = $this->criar('Exercício A');

        $this->artisan('exercicios:congelar-numeracao', ['--mapa' => $this->mapa, '--renames' => $this->renames, '--vagos' => $this->vagos, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertNull($a->fresh()->numero_revisao);
    }
}
