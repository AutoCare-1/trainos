<?php

namespace App\Console\Commands;

use App\Models\MealLog;
use App\Support\Uploads;
use Illuminate\Console\Command;

/**
 * Apaga a FOTO das refeições antigas, mantendo o registro.
 *
 * O diário alimentar não tem prazo de validade — e não deveria ter: o personal
 * usa o histórico pra enxergar padrão ao longo do tempo. O problema é só de
 * disco, e ele é todo da foto: o texto de uma refeição ocupa alguns bytes, a
 * foto ocupa até 8 MB. Um aluno registrando 3 refeições por dia com foto passa
 * de meio giga por ano; com 50 alunos isso vira volume de verdade.
 *
 * Então a poda é cirúrgica: some a imagem, fica o "31/08 · Almoço · arroz,
 * feijão e frango". O personal continua conseguindo ler o padrão de meses
 * atrás, que é pra que ele olha isso.
 *
 * A janela bate com o que a tela do personal mostra (GET /alunos/{id}/nutricao
 * aceita até 30 dias) — apagar antes disso deixaria buraco visível.
 */
class PodarFotosDeRefeicao extends Command
{
    protected $signature = 'nutricao:podar-fotos {--dias=30 : Idade a partir da qual a foto é apagada} {--dry-run : Só mostra o que seria apagado}';

    protected $description = 'Apaga as fotos de refeições antigas, preservando o registro em texto.';

    public function handle(): int
    {
        $dias = max(1, (int) $this->option('dias'));
        $corte = now()->subDays($dias)->toDateString();

        $antigas = MealLog::whereNotNull('file_path')
            ->whereDate('data', '<', $corte)
            ->get(['id', 'data', 'file_path']);

        if ($antigas->isEmpty()) {
            $this->info("Nenhuma foto anterior a {$corte} pra podar.");

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info($antigas->count()." foto(s) anteriores a {$corte} seriam apagadas (o texto ficaria).");

            return self::SUCCESS;
        }

        $apagadas = 0;
        foreach ($antigas as $refeicao) {
            Uploads::deletePrivateQuietly($refeicao->file_path);
            // Só depois de sumir do disco: se a coluna zerasse primeiro e o
            // processo morresse no meio, o arquivo viraria órfão sem ninguém
            // sabendo que ele existe.
            $refeicao->forceFill(['file_path' => null])->save();
            $apagadas++;
        }

        $this->info("{$apagadas} foto(s) apagadas. Os registros em texto continuam.");

        return self::SUCCESS;
    }
}
