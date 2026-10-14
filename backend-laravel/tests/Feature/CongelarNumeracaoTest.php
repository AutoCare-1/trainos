<?php

namespace Tests\Feature;

use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Numeração congelada da tela de vídeos (lista do Hugo de 13/10/2026).
 *
 * O que o teste protege: (1) retirar um exercício deixa o número vago e NÃO
 * desloca os seguintes — o 401 continua 401; (2) renomear (inclusive em
 * cadeia) não perde o número; (3) roda em todo deploy, então é idempotente;
 * (4) nunca dois exercícios com o mesmo número.
 *
 * Usa mapas próprios, nunca os arquivos reais, pra não quebrar quando eles
 * mudarem.
 */
class CongelarNumeracaoTest extends TestCase
{
    use RefreshDatabase;

    private string $mapa;

    private string $renames;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapa = sys_get_temp_dir().'/numeracao-'.uniqid().'.php';
        $this->renames = sys_get_temp_dir().'/renames-'.uniqid().'.php';
        $this->escrever($this->mapa, [
            399 => 'Exercício A',
            400 => 'Exercício B',
            401 => 'Exercício C',
            402 => 'Exercício D',
        ]);
        $this->escrever($this->renames, []);
    }

    protected function tearDown(): void
    {
        @unlink($this->mapa);
        @unlink($this->renames);
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

    private function congelar(): void
    {
        $this->artisan('exercicios:congelar-numeracao', ['--mapa' => $this->mapa, '--renames' => $this->renames])
            ->assertSuccessful();
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
        $this->congelar();

        $b->delete();
        $this->congelar();

        $this->assertSame(401, $c->fresh()->numero_revisao, 'o 401 tem de continuar 401');
        $this->assertSame(402, $d->fresh()->numero_revisao);
        $this->assertFalse(Exercise::where('numero_revisao', 400)->exists(), 'o 400 fica vago');
    }

    public function test_renomear_em_cadeia_nao_perde_o_numero(): void
    {
        $c = $this->criar('Exercício C');
        $this->congelar();

        // C foi renomeado duas vezes depois da lista: C -> C2 -> C3.
        $this->escrever($this->renames, ['Exercício C' => 'Exercício C2', 'Exercício C2' => 'Exercício C3']);
        $c->update(['name' => 'Exercício C3']);
        $this->congelar();

        $this->assertSame(401, $c->fresh()->numero_revisao);
    }

    public function test_banco_novo_ja_com_nome_novo_recebe_o_numero(): void
    {
        // Banco criado do zero: o seeder já cria com o nome novo.
        $this->escrever($this->renames, ['Exercício B' => 'Exercício B novo']);
        $b = $this->criar('Exercício B novo');

        $this->congelar();

        $this->assertSame(400, $b->fresh()->numero_revisao);
    }

    public function test_e_idempotente(): void
    {
        $a = $this->criar('Exercício A');
        $this->congelar();

        $this->artisan('exercicios:congelar-numeracao', ['--mapa' => $this->mapa, '--renames' => $this->renames])
            ->expectsOutputToContain('a gravar:                       0')
            ->assertSuccessful();

        $this->assertSame(399, $a->fresh()->numero_revisao);
    }

    public function test_nunca_dois_exercicios_com_o_mesmo_numero(): void
    {
        $intruso = $this->criar('Outro exercício');
        $intruso->forceFill(['numero_revisao' => 399])->save();
        $a = $this->criar('Exercício A');

        $this->congelar();

        $this->assertSame(399, $a->fresh()->numero_revisao);
        $this->assertNull($intruso->fresh()->numero_revisao);
    }

    public function test_dry_run_nao_altera_nada(): void
    {
        $a = $this->criar('Exercício A');

        $this->artisan('exercicios:congelar-numeracao', ['--mapa' => $this->mapa, '--renames' => $this->renames, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertNull($a->fresh()->numero_revisao);
    }
}
