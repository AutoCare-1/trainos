<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use App\Support\Higgsfield;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Gera a demonstração visual dos exercícios que não têm imagem, usando as fotos
 * de um modelo real como referência de identidade (via Higgsfield).
 *
 * Contexto: 571 dos 646 exercícios da biblioteca ficaram sem foto porque o
 * wger.de (fonte dos 75 originais) só tem 66 traduções em português e 365
 * imagens no acervo inteiro. Hoje esses 571 caem no boneco-palito animado.
 *
 * É um comando, e não um job disparado pela interface, de propósito: isso é
 * produção de catálogo, feita uma vez por exercício e revisada por gente antes
 * de ir pro ar — não algo que o personal dispara no meio do uso. Cada geração
 * custa dinheiro de verdade, então o padrão é --limite pequeno e --dry-run.
 */
class GerarDemonstracaoExercicio extends Command
{
    protected $signature = 'exercicios:gerar-demonstracao
        {--limite=5 : Quantos exercícios processar nesta rodada}
        {--grupo= : Filtra por grupo muscular (ex: "Peito")}
        {--exercicio=* : Nome exato de exercício específico (pode repetir)}
        {--video : Gera vídeo curto em vez de imagem estática}
        {--referencias=6 : Quantas fotos do modelo usar como referência}
        {--dry-run : Mostra o que seria gerado e o prompt, sem chamar a API}';

    protected $description = 'Gera imagem/vídeo de demonstração para exercícios sem foto, a partir das fotos de referência do modelo.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! Higgsfield::configurado()) {
            $this->error('HIGGSFIELD_KEY_ID/HIGGSFIELD_KEY_SECRET não configurados no .env.');
            $this->line('Use --dry-run para conferir a seleção e os prompts sem chamar a API.');

            return self::FAILURE;
        }

        $referencias = $this->fotosDeReferencia((int) $this->option('referencias'));
        if ($referencias === [] && ! $dryRun) {
            $this->error('Nenhuma foto de referência em '.config('higgsfield.dir_referencias'));

            return self::FAILURE;
        }

        $exercicios = $this->selecionar();
        if ($exercicios->isEmpty()) {
            $this->info('Nada a fazer: nenhum exercício sem imagem bate com o filtro.');

            return self::SUCCESS;
        }

        $this->info("{$exercicios->count()} exercício(s) nesta rodada · ".count($referencias).' foto(s) de referência');
        $this->newLine();

        if ($dryRun) {
            foreach ($exercicios as $ex) {
                $this->line("<fg=cyan>{$ex->name}</> ({$ex->muscle_group} · {$ex->equipment})");
                $this->line('  '.self::montarPrompt($ex));
                $this->newLine();
            }
            $this->comment('Dry-run: nada foi gerado e nada foi cobrado.');

            return self::SUCCESS;
        }

        // Sobe as referências uma vez só e reaproveita em todos os exercícios
        // do lote — subir as mesmas fotos a cada exercício seria desperdício.
        $this->line('Enviando fotos de referência...');
        $referenciasUrl = [];
        foreach ($referencias as $caminho) {
            $referenciasUrl[] = Higgsfield::enviarArquivo($caminho);
        }

        $ok = 0;
        $falhas = [];
        foreach ($exercicios as $ex) {
            $this->line("<fg=cyan>{$ex->name}</>");
            try {
                $this->processar($ex, $referenciasUrl);
                $ok++;
            } catch (Throwable $e) {
                // Uma falha não derruba o lote: o resto continua e o motivo
                // aparece no resumo do fim.
                $falhas[$ex->name] = $e->getMessage();
                $this->line('  <fg=red>falhou:</> '.$e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Concluído: {$ok} gerado(s), ".count($falhas).' falha(s).');
        foreach ($falhas as $nome => $motivo) {
            $this->line("  <fg=red>{$nome}</>: {$motivo}");
        }

        return $falhas === [] ? self::SUCCESS : self::FAILURE;
    }

    private function processar(Exercise $ex, array $referenciasUrl): void
    {
        $video = (bool) $this->option('video');

        $envio = Higgsfield::gerarDemonstracao(self::montarPrompt($ex), $referenciasUrl, $video);
        $requestId = $envio['request_id'] ?? null;
        if (! $requestId) {
            throw new \RuntimeException('Resposta sem request_id');
        }

        $resposta = Higgsfield::aguardar(
            $requestId,
            fn ($tentativa, $status) => $tentativa % 5 === 0 ? $this->line("  ...{$status}") : null
        );

        if (($resposta['status'] ?? null) !== Higgsfield::STATUS_COMPLETO) {
            throw new \RuntimeException(
                'status='.($resposta['status'] ?? '?').' '.($resposta['error'] ?? '')
            );
        }

        $url = Higgsfield::urlDoProduto($resposta);
        if (! $url) {
            throw new \RuntimeException('Resposta completa mas sem arquivo');
        }

        $caminho = Higgsfield::baixarProduto($url, Str::slug($ex->name), $video ? 'mp4' : 'jpg');

        // Grava só a coluna do tipo gerado. image_credit registra a origem
        // sintética — o personal (e nós) precisa conseguir distinguir uma foto
        // real de wger de uma demonstração gerada por IA.
        $ex->forceFill($video
            ? ['video_url' => $caminho]
            : ['image_url' => $caminho, 'image_credit' => 'Demonstração gerada por IA (Higgsfield)']
        )->save();

        $this->line("  <fg=green>ok</> {$caminho}");
    }

    /** @return \Illuminate\Support\Collection<int, Exercise> */
    private function selecionar()
    {
        $nomes = (array) $this->option('exercicio');

        return Exercise::query()
            ->when($nomes !== [], fn ($q) => $q->whereIn('name', $nomes))
            ->when($nomes === [], fn ($q) => $q->whereNull('image_url')->whereNull('video_url'))
            ->when($this->option('grupo'), fn ($q, $g) => $q->where('muscle_group', $g))
            ->orderBy('muscle_group')
            ->orderBy('name')
            ->limit(max(1, (int) $this->option('limite')))
            ->get();
    }

    /** @return string[] caminhos absolutos */
    private function fotosDeReferencia(int $quantas): array
    {
        $dir = (string) config('higgsfield.dir_referencias');
        if (! is_dir($dir)) {
            return [];
        }

        $arquivos = glob($dir.'/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
        sort($arquivos);

        return array_slice($arquivos, 0, max(1, $quantas));
    }

    /**
     * O prompt precisa descrever a EXECUÇÃO, não só nomear o exercício: o
     * modelo não sabe o que é "rosca scott", mas sabe desenhar alguém com o
     * braço apoiado num banco inclinado flexionando o cotovelo. Por isso a
     * instrução do próprio exercício (que já existe na biblioteca) entra no
     * prompt — ela é exatamente essa descrição.
     *
     * O texto de cena vai em inglês e a execução em português, misturados de
     * propósito. Testado com crédito real em 22/08/2026:
     * - Prompt todo em português, liderando com "Foto de demonstração de
     *   exercício": o modelo ignorou o movimento e devolveu a pessoa parada em
     *   pé nas 3 tentativas.
     * - Cena em inglês + execução na frente: acertou o movimento de primeira.
     * O modelo responde muito melhor ao enquadramento descrito em inglês, mas
     * entende a descrição de postura em português — o que permite reaproveitar
     * as instruções que já existem na biblioteca em vez de traduzir 300 delas.
     *
     * Revisão do Filipe em 23/08/2026, com os 129 primeiros vídeos na mão,
     * mudou mais três coisas aqui:
     * - "clean white studio background" era ignorado: o modelo tirava o cenário
     *   das fotos de referência e plantava banco romano e máquina de remada no
     *   corredor do estúdio. Descrever a academia explicitamente segura melhor.
     * - Sem guarda de anatomia saíam membros a mais (rosca inclinada).
     * - A montagem da cena (de que lado a pessoa senta em relação ao aparelho)
     *   não está em `instructions` e o modelo chuta — daí dicaDeCena().
     */
    public static function montarPrompt(Exercise $ex): string
    {
        $descricao = self::instrucaoVisual($ex->instructions);

        $partes = [
            'The man from the reference photos performs the gym exercise',
            "\"{$ex->name}\"",
            $ex->equipment ? "using {$ex->equipment}." : '.',
            $descricao ? "Execution: {$descricao}" : null,
            self::dicaDeCena($ex->name),
            'He repeats the full movement twice, smoothly and under control.',
            'Static camera, full body and the equipment in frame.',
            'Setting: open gym floor with light grey walls, rubber flooring and',
            'weight machines in the background.',
            'One single person only, correct human anatomy with exactly two arms',
            'and two legs, clearly separated limbs.',
            'Realistic photograph, bright even lighting, no text on screen.',
        ];

        return implode(' ', array_filter($partes));
    }

    /**
     * Ajuste de cena específico deste exercício, quando existe.
     *
     * A biblioteca descreve o movimento, nunca a montagem (de que lado a
     * pessoa senta, onde o aparelho fica em relação a ela) — e o gerador
     * chuta. A curadoria de quando esse chute erra fica em
     * database/dicas_demonstracao.php, fora do código, pra dar pra revisar
     * sem ler PHP.
     */
    private static function dicaDeCena(string $nome): ?string
    {
        static $dicas = null;
        $dicas ??= require database_path('dicas_demonstracao.php');

        return $dicas[$nome] ?? null;
    }

    /**
     * Parte da instrução que descreve POSTURA (útil pra gerar a imagem),
     * descartando o que é prescrição de série/tempo.
     *
     * "Rosca 21: 7 reps na metade inferior, 7 na superior e 7 completas" é
     * instrução legítima pro personal, mas não diz nada sobre como a pessoa
     * aparece na foto — vira ruído no prompt, e pior, joga números soltos num
     * gerador de imagem, que é um jeito conhecido de produzir aberração.
     */
    public static function instrucaoVisual(?string $instrucoes): ?string
    {
        if (! $instrucoes) {
            return null;
        }

        $prescricao = '/\b(\d+\s*(reps?|repetic|serie|s\b|segundos?)|sustente|pause|segure \d|até a falha|tempo prescrito|ritmo|cadência)/iu';

        $frases = preg_split('/(?<=[.;])\s+/u', $instrucoes) ?: [];
        $visuais = array_filter(
            array_map('trim', $frases),
            fn ($f) => $f !== '' && ! preg_match($prescricao, $f)
        );

        $texto = trim(implode(' ', $visuais));

        return $texto !== '' ? $texto : null;
    }
}
