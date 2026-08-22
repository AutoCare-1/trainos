<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remove da biblioteca os exercícios marcados como redundantes ou raros
 * (database/biblioteca_podada.php), reduzindo 646 -> ~400.
 *
 * A curadoria saiu de um pedido do produto: com 646 opções, achar o exercício
 * certo virou o gargalo da prescrição. Foram cortadas variações quase idênticas
 * (mesma mecânica com pegada/ângulo trocado), coisas que são nota de prescrição
 * e não exercício (isometria, pausa, parcial), e equipamento que academia comum
 * não tem (landmine, trap bar, trenó).
 *
 * NUNCA apaga sem checar dependência. Duas famílias de risco, com naturezas
 * diferentes:
 *
 * 1. workout_exercises / workout_template_exercises usam RESTRICT — o banco já
 *    barraria, mas o comando checa antes pra dar um relatório em vez de estourar
 *    no meio do lote.
 * 2. exercise_media_overrides, form_feedback_history e form_correction_videos
 *    usam CASCADE. Essas o banco NÃO protege: apagar o exercício levaria junto,
 *    em silêncio, o vídeo que um personal gravou e o histórico de análise de
 *    forma de um aluno. É o motivo principal deste comando existir em vez de um
 *    DELETE direto.
 */
class PodarBibliotecaExercicios extends Command
{
    protected $signature = 'exercicios:podar-biblioteca
        {--dry-run : Só mostra o que seria removido e o que está protegido}
        {--force : Executa a remoção de verdade}';

    protected $description = 'Remove exercícios redundantes/raros da biblioteca, preservando o que está em uso.';

    public function handle(): int
    {
        $nomes = require database_path('biblioteca_podada.php');

        if (! $this->option('force') && ! $this->option('dry-run')) {
            $this->error('Isso apaga dados. Rode com --dry-run pra conferir, ou --force pra executar.');

            return self::FAILURE;
        }

        $alvos = Exercise::whereIn('name', $nomes)->get();
        $inexistentes = array_diff($nomes, $alvos->pluck('name')->all());

        $emUso = [];
        $comMidia = [];
        $comTrabalhoDePersonal = [];
        $podeRemover = [];

        foreach ($alvos as $ex) {
            if ($ex->image_url || $ex->video_url) {
                // Foto de acervo (wger) ou demonstração já gerada: se chegou a
                // ganhar mídia, alguém investiu nele — não é candidato a corte.
                $comMidia[] = $ex->name;

                continue;
            }

            $usos = DB::table('workout_exercises')->where('exercise_id', $ex->id)->count()
                + DB::table('workout_template_exercises')->where('exercise_id', $ex->id)->count();
            if ($usos > 0) {
                $emUso[] = "{$ex->name} ({$usos}x)";

                continue;
            }

            $doPersonal = DB::table('exercise_media_overrides')->where('exercise_id', $ex->id)->count()
                + DB::table('form_correction_videos')->where('exercise_id', $ex->id)->count()
                + DB::table('form_feedback_history')->where('exercise_id', $ex->id)->count();
            if ($doPersonal > 0) {
                $comTrabalhoDePersonal[] = "{$ex->name} ({$doPersonal}x)";

                continue;
            }

            $podeRemover[] = $ex;
        }

        $this->info('Marcados para corte: '.count($nomes));
        $this->line('  removíveis:                '.count($podeRemover));
        $this->line('  protegidos (em treino):    '.count($emUso));
        $this->line('  protegidos (mídia própria):'.count($comTrabalhoDePersonal));
        $this->line('  protegidos (têm imagem/vídeo): '.count($comMidia));
        $this->line('  não existem no banco:      '.count($inexistentes));

        foreach ([['em treino', $emUso], ['mídia do personal', $comTrabalhoDePersonal], ['com mídia', $comMidia]] as [$rotulo, $lista]) {
            foreach ($lista as $nome) {
                $this->line("  <fg=yellow>preservado ({$rotulo})</>: {$nome}");
            }
        }
        foreach ($inexistentes as $nome) {
            $this->line("  <fg=red>não encontrado</>: {$nome}");
        }

        if ($this->option('dry-run')) {
            $restante = Exercise::count() - count($podeRemover);
            $this->newLine();
            $this->comment("Dry-run: nada foi apagado. A biblioteca ficaria com {$restante} exercícios.");

            return self::SUCCESS;
        }

        DB::transaction(function () use ($podeRemover) {
            Exercise::whereIn('id', collect($podeRemover)->pluck('id'))->delete();
        });

        $this->newLine();
        $this->info('Removidos: '.count($podeRemover).'. Biblioteca agora com '.Exercise::count().' exercícios.');

        return self::SUCCESS;
    }
}
