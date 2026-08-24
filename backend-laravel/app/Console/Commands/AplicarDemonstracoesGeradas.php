<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use Illuminate\Console\Command;

/**
 * Liga cada exercício ao vídeo de demonstração que já existe no disco.
 *
 * Existe porque o repositório sozinho não bastava: o .mp4 mora em
 * public/uploads (no .gitignore, 340 MB) e o caminho morava só na coluna
 * video_url do banco de quem gerou. Quem clonava não via vídeo nenhum — e,
 * pior, não tinha como saber que estava faltando alguma coisa.
 *
 * O mapeamento agora é versionado em database/demonstracoes_geradas.php.
 * Este comando aplica, e `--exportar` faz o caminho inverso: lê o banco e
 * reescreve o arquivo, pra quem gerar vídeo novo não precisar editar à mão.
 */
class AplicarDemonstracoesGeradas extends Command
{
    protected $signature = 'exercicios:aplicar-demonstracoes
        {--exportar : Em vez de aplicar, reescreve o arquivo a partir do banco}
        {--force : Sobrescreve exercício que já tem mídia}
        {--arquivo= : Caminho de outro mapeamento (o teste usa; no dia a dia, omita)}';

    protected $description = 'Aponta cada exercício para o vídeo de demonstração já presente em public/uploads.';

    public function handle(): int
    {
        return $this->option('exportar') ? $this->exportar() : $this->aplicar();
    }

    /**
     * O caminho é opção e não constante porque o teste precisa de um mapa
     * próprio. Sem isso ele testaria contra a lista real — e a única forma de
     * exercitar "o arquivo não chegou" seria mexer num .mp4 de verdade. Foi o
     * que aconteceu na primeira versão deste teste: ele sobrescreveu e apagou
     * o vídeo do farmer walk, que teve de ser regerado.
     */
    private function caminhoDoMapa(): string
    {
        return $this->option('arquivo') ?: database_path('demonstracoes_geradas.php');
    }

    private function aplicar(): int
    {
        $mapa = require $this->caminhoDoMapa();

        $ok = 0;
        $semArquivo = [];
        $semExercicio = [];
        $jaTinha = 0;

        foreach ($mapa as $nome => $caminho) {
            $ex = Exercise::where('name', $nome)->first();
            if (! $ex) {
                $semExercicio[] = $nome;

                continue;
            }

            // URL absoluta é vídeo já publicado no storage: não há arquivo local
            // pra conferir, e é exatamente o caso que resolve a vida de quem
            // clona — recebe a URL pelo git e não precisa baixar 340 MB.
            $remoto = str_starts_with($caminho, 'http://') || str_starts_with($caminho, 'https://');

            // Já o caminho local só vale se o arquivo chegou: apontar pra
            // arquivo ausente é pior que não apontar, porque a tela mostra um
            // player quebrado em vez do fallback de "sem demonstração".
            if (! $remoto && ! is_file(public_path(ltrim($caminho, '/')))) {
                $semArquivo[] = $nome;

                continue;
            }

            if ($ex->video_url && ! $this->option('force')) {
                $jaTinha++;

                continue;
            }

            $ex->forceFill(['video_url' => $caminho])->save();
            $ok++;
        }

        $this->info("{$ok} exercício(s) ligado(s) ao vídeo.");
        if ($jaTinha > 0) {
            $this->line("{$jaTinha} já tinham vídeo (use --force pra sobrescrever).");
        }

        if ($semArquivo !== []) {
            $this->newLine();
            $this->warn(count($semArquivo).' exercício(s) sem o arquivo em public/uploads/exercise-demos:');
            foreach (array_slice($semArquivo, 0, 10) as $nome) {
                $this->line("  <fg=yellow>{$nome}</>");
            }
            if (count($semArquivo) > 10) {
                $this->line('  ... e mais '.(count($semArquivo) - 10).'.');
            }
            $this->line('Os .mp4 não vêm pelo git (340 MB, /public/uploads está no .gitignore).');
            $this->line('Peça uma cópia da pasta a quem gerou, ou baixe do storage compartilhado.');
        }

        if ($semExercicio !== []) {
            $this->newLine();
            $this->warn(count($semExercicio).' nome(s) do arquivo que não existem na biblioteca — provável renomeação:');
            foreach ($semExercicio as $nome) {
                $this->line("  <fg=yellow>{$nome}</>");
            }
        }

        return self::SUCCESS;
    }

    private function exportar(): int
    {
        $exercicios = Exercise::whereNotNull('video_url')
            ->orderBy('muscle_group')
            ->orderBy('name')
            ->get();

        $arquivo = database_path('demonstracoes_geradas.php');
        $conteudo = file_get_contents($arquivo);

        // Preserva o cabeçalho explicativo: ele diz por que o arquivo existe, e
        // reescrever tudo do zero apagaria isso a cada exportação.
        $cabecalho = substr($conteudo, 0, strpos($conteudo, 'return [') + strlen('return ['));

        $linhas = $exercicios->map(function (Exercise $ex) {
            $nome = str_replace("'", "\\'", $ex->name);

            return "    '{$nome}' => '{$ex->video_url}',";
        })->implode(PHP_EOL);

        file_put_contents($arquivo, $cabecalho.PHP_EOL.$linhas.PHP_EOL.'];'.PHP_EOL);

        $this->info($exercicios->count().' entrada(s) escritas em database/demonstracoes_geradas.php.');

        return self::SUCCESS;
    }
}
