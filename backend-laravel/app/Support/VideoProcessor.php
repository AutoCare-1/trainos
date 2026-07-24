<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Espelha backend/src/services/videoProcessor.ts do Node (lá usa fluent-ffmpeg;
 * aqui chama os binários ffmpeg/ffprobe diretamente via Process, mesmo efeito).
 */
class VideoProcessor
{
    private const MAX_FRAMES = 8;

    /** Extrai até MAX_FRAMES frames de um vídeo, espaçados uniformemente, redimensionados a ~1024px de largura. */
    public static function extrairFramesDeVideo(string $caminhoVideo, string $dirSaida): array
    {
        if (! is_dir($dirSaida)) {
            mkdir($dirSaida, 0755, true);
        }

        $duracao = self::obterDuracao($caminhoVideo);
        $quantidade = $duracao > 0 ? min(self::MAX_FRAMES, max(1, (int) ceil($duracao / 2))) : self::MAX_FRAMES;
        $timestamps = self::gerarTimestamps($duracao, $quantidade);

        return self::extrairFramesEm($caminhoVideo, $dirSaida, $timestamps);
    }

    /** Extrai 3 frames-chave (20%, 50%, 80% da duração) — usado na análise de forma, onde início/meio/fim bastam. */
    public static function extrairFramesChave(string $caminhoVideo, string $dirSaida): array
    {
        if (! is_dir($dirSaida)) {
            mkdir($dirSaida, 0755, true);
        }

        $duracao = self::obterDuracao($caminhoVideo);
        $timestamps = array_map(fn ($p) => number_format($duracao * $p, 2, '.', ''), [0.2, 0.5, 0.8]);

        return self::extrairFramesEm($caminhoVideo, $dirSaida, $timestamps);
    }

    public static function obterDuracaoVideo(string $caminhoVideo): float
    {
        return self::obterDuracao($caminhoVideo);
    }

    /** @param  string[]  $timestamps */
    private static function extrairFramesEm(string $caminhoVideo, string $dirSaida, array $timestamps): array
    {
        $arquivos = [];
        foreach ($timestamps as $i => $ts) {
            $arquivo = $dirSaida.'/frame-'.($i + 1).'.jpg';
            $result = Process::run([
                'ffmpeg', '-y', '-ss', (string) $ts, '-i', $caminhoVideo,
                '-vframes', '1', '-vf', 'scale=1024:-2',
                $arquivo,
            ]);
            if ($result->failed()) {
                throw new RuntimeException("Falha ao extrair frame do vídeo: {$result->errorOutput()}");
            }
            $arquivos[] = $arquivo;
        }

        return $arquivos;
    }

    private static function obterDuracao(string $caminhoVideo): float
    {
        $result = Process::run([
            'ffprobe', '-v', 'error', '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1', $caminhoVideo,
        ]);
        if ($result->failed()) {
            throw new RuntimeException("Falha ao ler duração do vídeo: {$result->errorOutput()}");
        }

        return (float) trim($result->output());
    }

    /** @return string[] */
    private static function gerarTimestamps(float $duracao, int $quantidade): array
    {
        if ($duracao <= 0) {
            return array_map(fn ($i) => (string) $i, range(0, $quantidade - 1));
        }
        $intervalo = $duracao / ($quantidade + 1);

        return array_map(
            fn ($i) => number_format(($i + 1) * $intervalo, 2, '.', ''),
            range(0, $quantidade - 1)
        );
    }
}
