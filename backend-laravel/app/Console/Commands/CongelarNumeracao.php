<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Grava em `exercises.numero_revisao` o número que cada exercício tinha na
 * lista do Hugo de 13/10/2026 (database/numeracao_revisao_2026-10-13.php).
 *
 * Por que congelar: a tela numerava na hora (grupo + alfabético), e qualquer
 * rename ou retirada deslocava os números seguintes. O Hugo vai reconferir pela
 * MESMA lista que mandou, então o número tem de ficar parado até a lista
 * inteira estar certa (decisão do Filipe, 14/10/2026).
 *
 * O mapa guarda o nome que o Hugo viu. Daqui em diante os exercícios podem ser
 * renomeados, então o nome atual é achado seguindo renames_revisao_hugo.php a
 * partir dele (há encadeamentos: A -> B -> C). Retirado = não encontrado, e o
 * número dele fica vago — é exatamente o buraco que se quer.
 *
 * Idempotente e seguro em todo deploy: só escreve onde o número difere, e
 * nunca deixa dois exercícios com o mesmo número.
 */
class CongelarNumeracao extends Command
{
    protected $signature = 'exercicios:congelar-numeracao
        {--dry-run : Só mostra o que seria gravado}
        {--mapa= : Caminho de outro mapa de numeração (o teste usa)}
        {--renames= : Caminho de outro mapa de renomes (o teste usa)}';

    protected $description = 'Grava o número congelado da lista do Hugo (13/10) em cada exercício.';

    public function handle(): int
    {
        $mapa = require $this->option('mapa') ?: database_path('numeracao_revisao_2026-10-13.php');
        $renames = require $this->option('renames') ?: database_path('renames_revisao_hugo.php');
        $seco = (bool) $this->option('dry-run');

        $gravar = [];
        $jaCertos = 0;
        $naoEncontrados = [];

        foreach ($mapa as $numero => $nomeNaLista) {
            $nomeAtual = self::nomeAtual($nomeNaLista, $renames);
            $ex = Exercise::where('name', $nomeAtual)->first();

            if (! $ex) {
                $naoEncontrados[] = "#{$numero} {$nomeNaLista}";

                continue;
            }

            if ((int) $ex->numero_revisao === (int) $numero) {
                $jaCertos++;

                continue;
            }

            $gravar[$ex->id] = (int) $numero;
        }

        $this->info('Numeração congelada: '.count($mapa).' números na lista');
        $this->line('  a gravar:                       '.count($gravar));
        $this->line('  já certos:                      '.$jaCertos);
        $this->line('  sem exercício (retirados, vago): '.count($naoEncontrados));

        if ($seco) {
            foreach ($gravar as $id => $numero) {
                $this->line("  #{$numero} -> exercício {$id}");
            }
            $this->comment('Dry-run: nada foi alterado.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($gravar) {
            foreach ($gravar as $id => $numero) {
                // Quem estiver com este número por engano perde ele antes:
                // nunca dois exercícios com o mesmo número na tela.
                Exercise::where('numero_revisao', $numero)->whereKeyNot($id)->update(['numero_revisao' => null]);
                Exercise::whereKey($id)->update(['numero_revisao' => $numero]);
            }
        });

        $this->info('Gravados: '.count($gravar).'.');

        return self::SUCCESS;
    }

    /** Segue a cadeia de renomes a partir do nome da lista (com trava contra ciclo). */
    public static function nomeAtual(string $nome, array $renames): string
    {
        $vistos = [];
        while (isset($renames[$nome]) && ! isset($vistos[$nome])) {
            $vistos[$nome] = true;
            $nome = $renames[$nome];
        }

        return $nome;
    }
}
