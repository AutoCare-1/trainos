<?php

namespace Tests\Feature;

use App\Console\Commands\GerarDemonstracaoExercicio;
use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O arquivo database/dicas_demonstracao.php é carregado com `require` de dentro
 * de um método, e `require` executa no escopo de quem chama. O arquivo tem
 * `foreach ($varreduraPreventiva as $nome => ...)` no topo, e isso sobrescrevia
 * o parâmetro $nome de GerarDemonstracaoExercicio::ajuste().
 *
 * O efeito era caro e silencioso: só a PRIMEIRA chamada de cada processo roda o
 * require (depois o static está quente), então o primeiro exercício de cada
 * rodada de geração recebia a cena de outro exercício — a última chave do
 * arquivo — e saía um vídeo errado por 4 créditos, sem erro nenhum no log.
 *
 * Descoberto em 16/09/2026 investigando por que 'Puxada frontal pegada neutra'
 * vinha com a cena do tríceps de finalização de braçada.
 */
class DicaDemonstracaoEscopoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reproduz a condição exata: uma função com um parâmetro chamado $nome que
     * dá require no arquivo. Se o arquivo vazar variáveis, $nome muda.
     */
    public function test_o_arquivo_de_dicas_nao_sobrescreve_variaveis_de_quem_carrega(): void
    {
        $sonda = static function (string $nome) {
            $dicas = (static fn (string $c) => require $c)(database_path('dicas_demonstracao.php'));

            return [$nome, count($dicas)];
        };

        [$nomeDepois, $total] = $sonda('Puxada frontal pegada neutra');

        $this->assertSame('Puxada frontal pegada neutra', $nomeDepois);
        $this->assertGreaterThan(100, $total);
    }

    /**
     * O teste de ponta a ponta: a cena que entra no prompt tem que ser a do
     * exercício pedido, não a de outro.
     */
    public function test_a_cena_do_prompt_e_a_do_exercicio_pedido(): void
    {
        $ex = Exercise::create([
            'name' => 'Puxada frontal pegada neutra',
            'muscle_group' => 'Costas',
            'equipment' => 'Polia',
            'instructions' => 'Punhos voltados um para o outro, puxe até o peito superior.',
        ]);

        $prompt = GerarDemonstracaoExercicio::montarPrompt($ex);

        $this->assertStringContainsString('facing the machine', $prompt);
        $this->assertStringNotContainsString('swimming stroke', $prompt);
    }
}
