<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use App\Support\Higgsfield;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Importa uma demonstração JÁ GERADA (URL do CDN do Higgsfield) e prega no
 * exercício correspondente.
 *
 * Existe separado do exercicios:gerar-demonstracao porque a geração nem sempre
 * acontece pela API do app: dá pra gerar pelo site do Higgsfield ou pelo
 * conector MCP, onde é possível olhar o resultado antes de aceitar. Nesses
 * casos o que falta é só a parte de trazer pra dentro do produto — que é
 * obrigatória, porque a URL do CDN deles expira em ~7 dias.
 *
 * Uso:
 *   php artisan exercicios:importar-demonstracao "Nome do exercício" <url> [--imagem]
 */
class ImportarDemonstracaoGerada extends Command
{
    protected $signature = 'exercicios:importar-demonstracao
        {exercicio : Nome exato do exercício na biblioteca}
        {url : URL do arquivo gerado}
        {--imagem : Trata como imagem (padrão é vídeo)}
        {--force : Sobrescreve mesmo se o exercício já tiver mídia}';

    protected $description = 'Baixa uma demonstração já gerada e grava no exercício (a URL do CDN expira em ~7 dias).';

    public function handle(): int
    {
        $nome = (string) $this->argument('exercicio');
        $video = ! $this->option('imagem');

        $ex = Exercise::where('name', $nome)->first();
        if (! $ex) {
            $this->error("Exercício não encontrado: {$nome}");

            return self::FAILURE;
        }

        $jaTem = $video ? $ex->video_url : $ex->image_url;
        if ($jaTem && ! $this->option('force')) {
            // Os 75 originais têm foto real de acervo (wger, CC-BY-SA).
            // Sobrescrever sem querer trocaria foto real por conteúdo sintético.
            $this->warn("Já tem mídia ({$jaTem}). Use --force pra sobrescrever.");

            return self::FAILURE;
        }

        try {
            $caminho = Higgsfield::baixarProduto(
                (string) $this->argument('url'),
                Str::slug($nome),
                $video ? 'mp4' : 'jpg'
            );
        } catch (Throwable $e) {
            $this->error('Falha ao baixar: '.$e->getMessage());

            return self::FAILURE;
        }

        $ex->forceFill($video
            ? ['video_url' => $caminho]
            : ['image_url' => $caminho, 'image_credit' => 'Demonstração gerada por IA (Higgsfield)']
        )->save();

        $this->info("ok  {$nome} -> {$caminho}");

        return self::SUCCESS;
    }
}
