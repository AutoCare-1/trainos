<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
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
 */
class PublicarDemonstracoes extends Command
{
    protected $signature = 'exercicios:publicar-demonstracoes
        {--dry-run : Mostra o que subiria, sem enviar nada}';

    protected $description = 'Envia os vídeos de demonstração para o storage configurado e atualiza video_url.';

    public function handle(): int
    {
        $disco = (string) config('demonstracoes.disco');
        $baseUrl = (string) config('demonstracoes.base_url');
        $prefixo = trim((string) config('demonstracoes.prefixo'), '/');

        if ($disco === '' || $baseUrl === '') {
            $this->error('DEMONSTRACOES_DISCO e DEMONSTRACOES_BASE_URL precisam estar no .env.');
            $this->line('Sem os dois o comando não sabe pra onde enviar nem que URL gravar no banco.');
            $this->line('Ver config/demonstracoes.php.');

            return self::FAILURE;
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

                $ex->forceFill(['video_url' => $baseUrl.'/'.basename($local)])->save();
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
            $this->comment('Agora rode `exercicios:aplicar-demonstracoes --exportar` e commite o mapeamento,');
            $this->comment('pra quem clonar receber as URLs sem precisar dos arquivos.');
        }

        return self::SUCCESS;
    }

    private function tamanho(string $caminho): string
    {
        return round(filesize($caminho) / 1024).' KB';
    }
}
