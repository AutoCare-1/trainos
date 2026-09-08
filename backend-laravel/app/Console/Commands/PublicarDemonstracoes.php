<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Sobe os vídeos de demonstração pro armazenamento de objetos e aponta o banco
 * pra lá.
 *
 * Por que existe: os 327 arquivos viviam só em public/uploads da máquina de
 * quem gerou. Quem clonava o repositório não recebia nada — a pasta está no
 * .gitignore — e produção não tinha de onde servir. Ver config/demonstracoes.php
 * pro raciocínio da escolha (R2, por causa do egress).
 *
 * Depois de rodar, `exercicios:aplicar-demonstracoes --exportar` grava as URLs
 * novas no mapeamento versionado. Aí quem clonar recebe as URLs pelo git e não
 * precisa de nenhum arquivo local.
 *
 * Rodar de novo é barato: quem já tem URL absoluta no banco é ignorado, então
 * depois de gerar um vídeo novo só ele sobe. E quem volta a ter caminho local
 * — que é o que a importação faz — sobe de novo mesmo já existindo no destino,
 * porque isso significa que o arquivo mudou.
 *
 * Junto de cada <slug>.mp4 sobe um <slug>.jpg com o primeiro quadro (poster do
 * <video> — sem ele o iPhone mostra retângulo transparente enquanto o autoplay
 * não é liberado). O app monta a URL do poster trocando a extensão, então nada
 * disso entra no mapa nem no banco. Pra preencher poster de vídeo já publicado
 * numa rodada antiga, `--posters` lê os .mp4 locais e sobe só os .jpg.
 */
class PublicarDemonstracoes extends Command
{
    protected $signature = 'exercicios:publicar-demonstracoes
        {--dry-run : Mostra o que subiria, sem enviar nada}
        {--posters : Só (re)gera e sobe os posters .jpg a partir dos .mp4 locais}';

    protected $description = 'Envia os vídeos de demonstração para o storage configurado e atualiza video_url.';

    /** Resolvido uma vez por execução por gerarPoster(). */
    private ?bool $temFfmpeg = null;

    public function handle(): int
    {
        $disco = (string) config('demonstracoes.disco');
        $baseUrl = (string) config('demonstracoes.base_url');
        $prefixo = trim((string) config('demonstracoes.prefixo'), '/');
        $soPosters = (bool) $this->option('posters');

        // base_url só é obrigatória pra gravar video_url no banco — o modo
        // --posters não grava nada, só precisa do disco de destino.
        if ($disco === '' || (! $soPosters && $baseUrl === '')) {
            $this->error('DEMONSTRACOES_DISCO e DEMONSTRACOES_BASE_URL precisam estar no .env.');
            $this->line('Sem os dois o comando não sabe pra onde enviar nem que URL gravar no banco.');
            $this->line('Ver config/demonstracoes.php.');

            return self::FAILURE;
        }

        if ($soPosters) {
            return $this->publicarPosters($disco, $prefixo);
        }

        $exercicios = Exercise::whereNotNull('video_url')->orderBy('name')->get();
        if ($exercicios->isEmpty()) {
            $this->info('Nenhum exercício com vídeo. Nada a publicar.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $enviados = 0;
        $jaRemotos = 0;
        $semArquivo = [];
        $falhas = [];

        foreach ($exercicios as $ex) {
            $url = (string) $ex->video_url;

            // Já é URL absoluta: este exercício já foi publicado numa rodada
            // anterior. Reenviar exigiria baixar do CDN pra subir de volta.
            if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                $jaRemotos++;

                continue;
            }

            $local = public_path(ltrim($url, '/'));
            if (! is_file($local)) {
                $semArquivo[] = $ex->name;

                continue;
            }

            $nomeRemoto = $prefixo.'/'.basename($local);

            // Não existe "pular porque já está lá". O nome do arquivo vem do
            // slug do exercício, então um vídeo regerado sobrescreve o antigo
            // com o MESMO nome — e video_url volta a apontar pro disco local.
            // Ou seja: chegar aqui com caminho local significa exatamente "este
            // mudou". Pular pela existência do objeto deixaria o CDN servindo a
            // versão velha em silêncio, que é o pior desfecho possível: o vídeo
            // reprovado continua no ar e ninguém percebe.
            //
            // Quem já foi publicado não chega aqui: sai antes, no $jaRemotos.
            if ($dryRun) {
                $this->line("  subiria <fg=cyan>{$nomeRemoto}</> (".$this->tamanho($local).')');
                $enviados++;

                continue;
            }

            try {
                $stream = fopen($local, 'rb');
                Storage::disk($disco)->put($nomeRemoto, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }

                // $nomeRemoto, não basename(): o arquivo sobe DENTRO do prefixo
                // ("exercise-demos/x.mp4") e a URL precisa apontar pro mesmo
                // lugar. Gravar só o basename gerava 404 em todos os vídeos —
                // e em silêncio, porque o comando reporta "ok" pelo upload, que
                // de fato deu certo. Só aparece quando alguém abre o app.
                $ex->forceFill(['video_url' => $baseUrl.'/'.$nomeRemoto])->save();
                $this->subirPoster($disco, $prefixo, $local, $nomeRemoto);
                $enviados++;
                $this->line("  <fg=green>ok</> {$ex->name}");
            } catch (Throwable $e) {
                // Uma falha não derruba o lote: 327 uploads e um erro de rede
                // no meio não pode obrigar a recomeçar do zero.
                $falhas[$ex->name] = $e->getMessage();
                $this->line("  <fg=red>falhou</> {$ex->name}: ".$e->getMessage());
            }
        }

        $this->newLine();
        $this->info($dryRun
            ? "{$enviados} arquivo(s) subiriam. Dry-run: nada foi enviado."
            : "{$enviados} enviado(s), {$jaRemotos} já publicado(s) antes.");

        if ($semArquivo !== []) {
            $this->warn(count($semArquivo).' exercício(s) sem o arquivo local — nada a enviar:');
            foreach (array_slice($semArquivo, 0, 10) as $nome) {
                $this->line("  <fg=yellow>{$nome}</>");
            }
        }

        if ($falhas !== []) {
            $this->newLine();
            $this->error(count($falhas).' falha(s). Rode de novo: o comando pula o que já subiu.');

            return self::FAILURE;
        }

        if (! $dryRun && $enviados > 0) {
            $this->newLine();
            $this->comment('Subiu o vídeo e o poster (<slug>.jpg) de cada um.');
            $this->comment('Agora rode `exercicios:aplicar-demonstracoes --exportar` e commite o mapeamento,');
            $this->comment('pra quem clonar receber as URLs sem precisar dos arquivos.');
        }

        return self::SUCCESS;
    }

    /**
     * Sobe só os posters, lendo os .mp4 de public/uploads. Serve pra preencher
     * o primeiro quadro dos vídeos já publicados numa rodada antiga — esses
     * aparecem como "já publicado(s)" no fluxo normal e não seriam reenviados.
     */
    private function publicarPosters(string $disco, string $prefixo): int
    {
        $pastas = array_values(array_filter([
            public_path('uploads/'.$prefixo),
            public_path($prefixo),
        ], 'is_dir'));

        if ($pastas === []) {
            $this->error('Nenhuma pasta de vídeo local encontrada (public/uploads/'.$prefixo.').');
            $this->line('Os .mp4 não vêm pelo git — peça a cópia a quem gerou.');

            return self::FAILURE;
        }

        $mp4s = [];
        foreach ($pastas as $pasta) {
            foreach (glob($pasta.'/*.mp4') ?: [] as $arquivo) {
                $mp4s[basename($arquivo)] ??= $arquivo; // dedup por nome, 1ª pasta vence
            }
        }

        if ($mp4s === []) {
            $this->info('Nenhum .mp4 nas pastas locais. Nada a fazer.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $enviados = 0;
        $semPoster = 0;

        foreach ($mp4s as $nome => $caminho) {
            $poster = $this->gerarPoster($caminho);
            if ($poster === null) {
                $semPoster++;

                continue;
            }

            $remoto = $prefixo.'/'.pathinfo($nome, PATHINFO_FILENAME).'.jpg';

            if ($dryRun) {
                $this->line("  subiria <fg=cyan>{$remoto}</>");
                @unlink($poster);
                $enviados++;

                continue;
            }

            try {
                $stream = fopen($poster, 'rb');
                Storage::disk($disco)->put($remoto, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                $enviados++;
                $this->line("  <fg=green>ok</> {$remoto}");
            } catch (Throwable $e) {
                $this->line("  <fg=red>falhou</> {$remoto}: ".$e->getMessage());
            } finally {
                @unlink($poster);
            }
        }

        $this->newLine();
        $this->info("{$enviados} poster(s) ".($dryRun ? 'subiriam' : 'enviado(s)').'.');
        if ($semPoster > 0) {
            $this->warn("{$semPoster} sem poster (ffmpeg falhou nesses arquivos).");
        }

        return self::SUCCESS;
    }

    /** Gera e sobe o poster de um vídeo recém-enviado. Falha aqui não derruba o lote. */
    private function subirPoster(string $disco, string $prefixo, string $videoLocal, string $nomeRemotoVideo): void
    {
        $poster = $this->gerarPoster($videoLocal);
        if ($poster === null) {
            return;
        }

        $remoto = $prefixo.'/'.pathinfo($nomeRemotoVideo, PATHINFO_FILENAME).'.jpg';
        try {
            $stream = fopen($poster, 'rb');
            Storage::disk($disco)->put($remoto, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        } catch (Throwable $e) {
            $this->line("  <fg=yellow>poster falhou</> {$remoto}: ".$e->getMessage());
        } finally {
            @unlink($poster);
        }
    }

    /**
     * Primeiro quadro do vídeo, em JPG. Precisa de ffmpeg no PATH; sem ele
     * avisa uma vez e devolve null — os vídeos sobem sem poster e o app degrada
     * pro comportamento de hoje (retângulo até o autoplay liberar).
     */
    private function gerarPoster(string $videoLocal): ?string
    {
        if ($this->temFfmpeg === null) {
            $probe = new Process(['ffmpeg', '-version']);
            $probe->run();
            $this->temFfmpeg = $probe->isSuccessful();
            if (! $this->temFfmpeg) {
                $this->warn('ffmpeg não encontrado no PATH — vídeos vão sem poster.');
                $this->line('Instale o ffmpeg e rode `exercicios:publicar-demonstracoes --posters` pra preencher depois.');
            }
        }

        if (! $this->temFfmpeg) {
            return null;
        }

        // tempnam cria o arquivo sem extensão; o ffmpeg infere o formato pela
        // extensão, então move-se pro .jpg e apaga-se o stub.
        $stub = tempnam(sys_get_temp_dir(), 'poster_');
        $destino = $stub.'.jpg';
        @unlink($stub);

        $proc = new Process([
            'ffmpeg', '-y', '-ss', '0.1', '-i', $videoLocal,
            '-frames:v', '1', '-q:v', '3', $destino,
        ]);
        $proc->run();

        if (! $proc->isSuccessful() || ! is_file($destino) || filesize($destino) === 0) {
            $this->line('  <fg=yellow>sem poster</> '.basename($videoLocal));
            @unlink($destino);

            return null;
        }

        return $destino;
    }

    private function tamanho(string $caminho): string
    {
        return round(filesize($caminho) / 1024).' KB';
    }
}
