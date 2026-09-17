<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Renomeia exercícios da biblioteca a partir de um mapa versionado
 * (database/renames_revisao_hugo.php).
 *
 * Por que um comando e não só editar os seeders: o nome é a CHAVE. O
 * ExerciseSeeder faz `updateOrCreate(['name' => ...])`, então trocar o nome só
 * no seeder não renomeia nada — cria um exercício novo e deixa o antigo para
 * trás, com o vídeo e o histórico grudados nele. O rename tem de acontecer no
 * banco, e por isso este comando roda no entrypoint ANTES dos seeders: quando
 * eles rodam, a linha já está com o nome novo e o updateOrCreate casa nela.
 *
 * O que ele protege:
 *
 * 1. `name` tem índice unique. Se o nome novo já existir, um rename cego
 *    estoura no meio do lote — aqui ele é relatado e pulado.
 * 2. Se o destino já existir porque um seeder correu antes deste comando (a
 *    ordem invertida cria exatamente isso), a linha nova é um fantasma sem
 *    vídeo nem histórico. Nesse caso, e só nesse, ela é apagada e o rename
 *    segue — depois de checar que ninguém depende dela.
 * 3. Idempotente: no segundo deploy o nome antigo não existe mais e o comando
 *    só relata "já renomeado".
 */
class RenomearExercicios extends Command
{
    protected $signature = 'exercicios:renomear
        {--dry-run : Só mostra o que seria renomeado}
        {--force : Executa de verdade}
        {--arquivo= : Caminho de outro mapa (o teste usa; no dia a dia, omita)}';

    protected $description = 'Renomeia exercícios da biblioteca a partir do mapa versionado.';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->option('dry-run')) {
            $this->error('Isso altera dados. Rode com --dry-run pra conferir, ou --force pra executar.');

            return self::FAILURE;
        }

        $mapa = require $this->option('arquivo') ?: database_path('renames_revisao_hugo.php');
        $seco = $this->option('dry-run');

        $renomear = [];
        $jaFeitos = [];
        $bloqueados = [];
        $ausentes = [];
        $fantasmas = [];

        foreach ($mapa as $antigo => $novo) {
            $origem = Exercise::where('name', $antigo)->first();
            $destino = Exercise::where('name', $novo)->first();

            if (! $origem) {
                // Sem origem e com destino = já rodou antes. Sem os dois = o
                // exercício não está nesta base (poda, ou base de teste).
                $destino ? $jaFeitos[] = $novo : $ausentes[] = $antigo;

                continue;
            }

            if ($destino) {
                $fantasma = $this->ehFantasmaDeSeeder($destino);
                if (! $fantasma) {
                    $bloqueados[] = "{$antigo} -> {$novo} (o nome novo já é de outro exercício)";

                    continue;
                }
                $fantasmas[] = $novo;
            }

            $renomear[] = [$origem, $novo, $destino];
        }

        $this->info('Mapa de renomeação: '.count($mapa));
        $this->line('  a renomear:        '.count($renomear));
        $this->line('  já renomeados:     '.count($jaFeitos));
        $this->line('  bloqueados:        '.count($bloqueados));
        $this->line('  fora desta base:   '.count($ausentes));

        foreach ($bloqueados as $b) {
            $this->line("  <fg=red>bloqueado</>: {$b}");
        }
        foreach ($fantasmas as $f) {
            $this->line("  <fg=yellow>duplicata de seeder será apagada</>: {$f}");
        }
        foreach ($renomear as [$origem, $novo]) {
            $this->line("  {$origem->name} <fg=green>-></> {$novo}");
        }

        if ($seco) {
            $this->newLine();
            $this->comment('Dry-run: nada foi alterado.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($renomear) {
            foreach ($renomear as [$origem, $novo, $destino]) {
                if ($destino) {
                    $destino->delete();
                }
                $origem->name = $novo;
                $origem->save();
            }
        });

        $this->newLine();
        $this->info('Renomeados: '.count($renomear).'.');

        return count($bloqueados) > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Linha recém-criada por seeder: tem os campos do catálogo, mas nenhum
     * vídeo e nenhuma dependência. Só nesse caso ela pode sair da frente.
     */
    private function ehFantasmaDeSeeder(Exercise $ex): bool
    {
        if ($ex->video_url) {
            return false;
        }

        $dependencias = DB::table('workout_exercises')->where('exercise_id', $ex->id)->count()
            + DB::table('workout_template_exercises')->where('exercise_id', $ex->id)->count()
            + DB::table('exercise_media_overrides')->where('exercise_id', $ex->id)->count()
            + DB::table('form_correction_videos')->where('exercise_id', $ex->id)->count()
            + DB::table('form_feedback_history')->where('exercise_id', $ex->id)->count();

        return $dependencias === 0;
    }
}
