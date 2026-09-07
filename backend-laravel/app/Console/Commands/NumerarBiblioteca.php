<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Gera a lista numerada da biblioteca de exercícios.
 *
 * Por que existe: o personal revisa os vídeos de demonstração um a um e precisa
 * de um jeito curto de dizer "o vídeo tal está errado". Nome inteiro por
 * mensagem é ruim ("elevação frontal com barra" vira "elevação frontal" no
 * WhatsApp e some a variação). Um número resolve.
 *
 * A regra do número: ordem crescente, sem buraco, agrupado na mesma ordem da
 * tela /videos (ORDEM_GRUPOS) e, dentro do grupo, alfabético ignorando acento.
 * É tudo derivado — nunca se escreve um número à mão. Quando entrar exercício
 * novo num grupo que vem antes, rodar este comando de novo renumera todo o
 * resto pra frente. É de propósito que os números não são estáveis: a lista
 * vale pra UMA rodada de revisão, some depois que os apontamentos foram
 * aplicados, e aí sim se adiciona vídeo novo e renumera.
 *
 * Saída:
 *   database/biblioteca_numerada.md   — a lista pra ler / mandar pro personal
 *   database/biblioteca_numerada.json — número -> exercício, pra ferramenta
 *
 * `--check` não escreve nada: falha (código 1) se os arquivos no repo estão
 * desatualizados em relação ao banco. Serve de lembrete ("rodou seeder novo,
 * regenere a lista").
 */
class NumerarBiblioteca extends Command
{
    protected $signature = 'exercicios:numerar-biblioteca
        {--check : Não escreve; só verifica se os arquivos versionados estão atualizados}
        {--dir= : Diretório de saída (o teste usa o seu; no dia a dia, omita)}';

    protected $description = 'Gera database/biblioteca_numerada.{md,json} — a lista numerada pra revisão de vídeo.';

    /**
     * Mesma ordem de frontend/lib/bibliotecaExercicios.ts (ORDEM_GRUPOS).
     * Se mudar lá, mudar aqui — a tela e a lista numerada têm que bater, senão
     * o número que o personal lê na tela não é o mesmo da planilha.
     */
    private const ORDEM_GRUPOS = [
        'Peito', 'Costas', 'Ombros', 'Bíceps', 'Tríceps', 'Antebraço', 'Trapézio',
        'Pernas', 'Posterior', 'Glúteos', 'Panturrilha', 'Core', 'Funcional',
        'Esportivo', 'Ativação', 'Mobilidade', 'Equilíbrio', 'Prevenção', 'Alongamento',
    ];

    public function handle(): int
    {
        $ordenados = $this->ordenar(Exercise::all());

        $md = $this->montarMarkdown($ordenados);
        $json = $this->montarJson($ordenados);

        $dir = $this->option('dir') ?: database_path();
        $caminhoMd = $dir.'/biblioteca_numerada.md';
        $caminhoJson = $dir.'/biblioteca_numerada.json';

        if ($this->option('check')) {
            $desatualizados = [];
            if (! is_file($caminhoMd) || rtrim(file_get_contents($caminhoMd)) !== rtrim($md)) {
                $desatualizados[] = 'biblioteca_numerada.md';
            }
            if (! is_file($caminhoJson) || rtrim(file_get_contents($caminhoJson)) !== rtrim($json)) {
                $desatualizados[] = 'biblioteca_numerada.json';
            }

            if ($desatualizados) {
                $this->error('Desatualizado: '.implode(', ', $desatualizados).'. Rode `php artisan exercicios:numerar-biblioteca`.');

                return self::FAILURE;
            }

            $this->info('Lista numerada em dia ('.count($ordenados).' exercícios).');

            return self::SUCCESS;
        }

        file_put_contents($caminhoMd, $md);
        file_put_contents($caminhoJson, $json);

        $comVideo = collect($ordenados)->filter(fn ($e) => $this->temVideo($e))->count();
        $this->info(count($ordenados).' exercícios numerados ('.$comVideo.' com vídeo, '.(count($ordenados) - $comVideo).' sem).');
        $this->line('Escrito: database/biblioteca_numerada.md e .json');

        return self::SUCCESS;
    }

    /** @param  Collection<int, Exercise>  $exercicios
     *  @return array<int, Exercise> */
    private function ordenar($exercicios): array
    {
        return $exercicios
            ->sort(function (Exercise $a, Exercise $b) {
                $pa = $this->posicaoGrupo($a->muscle_group);
                $pb = $this->posicaoGrupo($b->muscle_group);
                if ($pa !== $pb) {
                    return $pa <=> $pb;
                }
                // grupo fora da ORDEM_GRUPOS: desempata pelo nome do grupo
                if ($a->muscle_group !== $b->muscle_group) {
                    return $a->muscle_group <=> $b->muscle_group;
                }

                return $this->chaveNome($a->name) <=> $this->chaveNome($b->name);
            })
            ->values()
            ->all();
    }

    private function posicaoGrupo(string $grupo): int
    {
        $i = array_search($grupo, self::ORDEM_GRUPOS, true);

        return $i === false ? count(self::ORDEM_GRUPOS) : $i;
    }

    /** Alfabético como um humano espera em pt-BR: sem acento, minúsculo. */
    private function chaveNome(string $nome): string
    {
        $semAcento = Str::ascii($nome);

        return mb_strtolower(trim($semAcento));
    }

    private function temVideo(Exercise $ex): bool
    {
        return filled($ex->video_url);
    }

    /** @param  array<int, Exercise>  $ordenados */
    private function montarMarkdown(array $ordenados): string
    {
        $total = count($ordenados);
        $comVideo = collect($ordenados)->filter(fn ($e) => $this->temVideo($e))->count();

        $linhas = [];
        $linhas[] = '# Biblioteca de exercícios — lista numerada';
        $linhas[] = '';
        $linhas[] = 'Gerado por `php artisan exercicios:numerar-biblioteca`. **Não editar à mão.**';
        $linhas[] = '';
        $linhas[] = 'Para a revisão dos vídeos de demonstração: o personal assiste na tela **Vídeos dos '.
            'exercícios** (o mesmo número aparece antes do nome lá) e reporta só o número do que estiver errado.';
        $linhas[] = '';
        $linhas[] = "**{$total} exercícios · {$comVideo} com vídeo · ".($total - $comVideo).' sem vídeo.**';
        $linhas[] = '';
        $linhas[] = 'Ordem: grupo muscular (mesma ordem da tela) e, dentro do grupo, alfabética. '.
            'Ao entrar exercício novo num grupo anterior, a lista é regerada e os números seguintes andam junto — '.
            'nunca entra número no meio sem ajustar o resto.';
        $linhas[] = '';

        $n = 0;
        $grupoAtual = null;
        foreach ($ordenados as $ex) {
            if ($ex->muscle_group !== $grupoAtual) {
                $grupoAtual = $ex->muscle_group;
                $linhas[] = '';
                $linhas[] = "## {$grupoAtual}";
                $linhas[] = '';
                $linhas[] = '| # | Exercício | Equipamento | Vídeo |';
                $linhas[] = '|--:|---|---|:--:|';
            }
            $n++;
            $video = $this->temVideo($ex) ? '✅' : '—';
            $equip = $ex->equipment ?: '';
            $linhas[] = "| {$n} | {$ex->name} | {$equip} | {$video} |";
        }

        $linhas[] = '';

        return implode("\n", $linhas)."\n";
    }

    /** @param  array<int, Exercise>  $ordenados */
    private function montarJson(array $ordenados): string
    {
        $itens = [];
        $n = 0;
        foreach ($ordenados as $ex) {
            $n++;
            $itens[] = [
                'numero' => $n,
                'nome' => $ex->name,
                'grupo' => $ex->muscle_group,
                'equipamento' => $ex->equipment,
                'tem_video' => $this->temVideo($ex),
                'slug' => Str::slug($ex->name),
            ];
        }

        // Sem timestamp de propósito: a saída tem que ser função só do banco,
        // senão `--check` acusaria diferença a cada rodada.
        return json_encode([
            'total' => count($itens),
            'com_video' => collect($itens)->where('tem_video', true)->count(),
            'itens' => $itens,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
    }
}
