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
 * Regra de ouro: número gravado NUNCA é trocado. Uma vez que a linha do
 * exercício recebeu o número, ele vai junto em qualquer rename (é a mesma
 * linha, o mesmo id), e quem é retirado leva o número embora — fica vago.
 *
 * Roda duas vezes no deploy:
 *
 * 1. `--antes-do-rename`: pelo nome EXATO. Na produção, no primeiro deploy, os
 *    nomes ainda são os que o Hugo viu, então cada número cai na linha certa
 *    pelo id — inclusive se um rename depois for bloqueado (exercício preso num
 *    treino de aluno).
 * 2. Sem opção, depois do rename e da poda: segue renames_revisao_hugo.php a
 *    partir do nome da lista. Só serve pra banco criado do zero (os seeders já
 *    nascem com o nome novo). Pula os números de numeracao_vaga_apos_renome.php,
 *    cujo nome foi passado a outro exercício.
 *
 * Idempotente: só preenche quem está sem número, com número que ninguém usa.
 */
class CongelarNumeracao extends Command
{
    protected $signature = 'exercicios:congelar-numeracao
        {--antes-do-rename : Casa só pelo nome exato (roda antes do exercicios:renomear)}
        {--dry-run : Só mostra o que seria gravado}
        {--mapa= : Caminho de outro mapa de numeração (o teste usa)}
        {--renames= : Caminho de outro mapa de renomes (o teste usa)}
        {--vagos= : Caminho de outra lista de números vagos após o rename (o teste usa)}';

    protected $description = 'Grava o número congelado da lista do Hugo (13/10) em cada exercício.';

    public function handle(): int
    {
        $mapa = require $this->option('mapa') ?: database_path('numeracao_revisao_2026-10-13.php');
        $exato = (bool) $this->option('antes-do-rename');
        $renames = $exato ? [] : require ($this->option('renames') ?: database_path('renames_revisao_hugo.php'));
        $vagos = $exato ? [] : array_flip(require ($this->option('vagos') ?: database_path('numeracao_vaga_apos_renome.php')));
        $seco = (bool) $this->option('dry-run');

        $numerosEmUso = Exercise::whereNotNull('numero_revisao')->pluck('numero_revisao')->map(fn ($n) => (int) $n)->flip();

        $gravar = [];
        $jaCertos = 0;
        $semExercicio = 0;
        $vagosDeProposito = 0;
        $conflitos = [];

        foreach ($mapa as $numero => $nomeNaLista) {
            $numero = (int) $numero;

            if (isset($vagos[$numero])) {
                $vagosDeProposito++;

                continue;
            }

            $nome = $exato ? $nomeNaLista : self::nomeAtual($nomeNaLista, $renames);
            $ex = Exercise::where('name', $nome)->first();

            if (! $ex) {
                $semExercicio++;

                continue;
            }

            if ($ex->numero_revisao !== null) {
                // Já tem número: nunca troca. Se for outro, é porque o nome desta
                // entrada passou a outro exercício — o caso é relatado.
                if ((int) $ex->numero_revisao === $numero) {
                    $jaCertos++;
                } else {
                    $conflitos[] = "#{$numero} {$nomeNaLista} -> {$ex->name}, que já tem o #{$ex->numero_revisao}";
                }

                continue;
            }

            if (isset($numerosEmUso[$numero]) || in_array($numero, $gravar, true)) {
                $conflitos[] = "#{$numero} {$nomeNaLista}: o número já é de outro exercício";

                continue;
            }

            if (isset($gravar[$ex->id])) {
                $conflitos[] = "#{$numero} {$nomeNaLista} -> {$ex->name}, que já vai receber o #{$gravar[$ex->id]}";

                continue;
            }

            $gravar[$ex->id] = $numero;
        }

        $this->info('Numeração congelada'.($exato ? ' (antes do rename, nome exato)' : '').': '.count($mapa).' números na lista');
        $this->line('  a gravar:                       '.count($gravar));
        $this->line('  já certos:                      '.$jaCertos);
        $this->line('  sem exercício (retirados, vago): '.$semExercicio);
        $this->line('  vagos de propósito (nome passado adiante): '.$vagosDeProposito);
        $this->line('  conflitos (nada trocado):       '.count($conflitos));
        foreach ($conflitos as $c) {
            $this->line("  <fg=yellow>conflito</>: {$c}");
        }

        if ($seco) {
            $this->comment('Dry-run: nada foi alterado.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($gravar) {
            foreach ($gravar as $id => $numero) {
                Exercise::whereKey($id)->whereNull('numero_revisao')->update(['numero_revisao' => $numero]);
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
