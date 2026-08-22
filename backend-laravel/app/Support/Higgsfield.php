<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Cliente da API do Higgsfield (higgsfield.ai) — gera imagem/vídeo de
 * demonstração de exercício a partir de fotos de referência de um modelo real.
 *
 * Mesmo padrão de App\Support\MercadoPago e App\Support\Strava: classe
 * estática, Http:: cru, sem SDK.
 *
 * Como a API funciona (docs.higgsfield.ai, OpenAPI 2.0.0):
 * 1. Arquivo local vira URL pública: POST /files/generate-upload-url devolve
 *    uma URL pré-assinada; o PUT do arquivo vai SEM a credencial (a doc é
 *    explícita: não mandar a chave do Higgsfield pro storage pré-assinado).
 * 2. Geração é assíncrona: o POST devolve request_id + status_url, e o
 *    resultado sai consultando o status até um estado terminal
 *    (completed / failed / nsfw / canceled).
 * 3. O arquivo pronto fica no CDN deles por ~7 dias — por isso baixarProduto()
 *    persiste no nosso storage. Guardar a URL deles daria link quebrado.
 *
 * Sobre consistência de identidade: o ideal seria um "Soul ID" treinado com
 * 20+ fotos (/higgsfield-ai/soul/character), mas **não existe endpoint público
 * pra criar um** — só pelo site. Enquanto não houver um, geraDemonstracao()
 * usa /higgsfield-ai/soul/reference, que aceita uma foto de referência direto.
 * Se HIGGSFIELD_SOUL_ID for preenchido depois, o código passa a usar o
 * endpoint de melhor consistência sozinho.
 */
class Higgsfield
{
    /** Estados terminais devolvidos pelo status da requisição. */
    public const STATUS_COMPLETO = 'completed';

    private const STATUS_TERMINAIS = [self::STATUS_COMPLETO, 'failed', 'nsfw', 'canceled'];

    public static function configurado(): bool
    {
        return (bool) config('higgsfield.key_id') && (bool) config('higgsfield.key_secret');
    }

    private static function credencial(): string
    {
        if (! self::configurado()) {
            throw new RuntimeException('HIGGSFIELD_KEY_ID/HIGGSFIELD_KEY_SECRET não configurados no .env');
        }

        // Formato exigido pela API: "Key <id>:<secret>" (não é Bearer).
        return 'Key '.config('higgsfield.key_id').':'.config('higgsfield.key_secret');
    }

    private static function http()
    {
        return Http::withHeaders(['Authorization' => self::credencial()])
            ->acceptJson()
            ->timeout(60);
    }

    private static function url(string $caminho): string
    {
        return rtrim((string) config('higgsfield.base_url'), '/').$caminho;
    }

    /**
     * Sobe um arquivo local e devolve a URL pública que os endpoints de geração
     * aceitam em image_reference_url / image_url.
     */
    public static function enviarArquivo(string $caminhoAbsoluto, string $contentType = 'image/jpeg'): string
    {
        if (! is_file($caminhoAbsoluto)) {
            throw new RuntimeException("Arquivo de referência não encontrado: {$caminhoAbsoluto}");
        }

        $resposta = self::http()
            ->post(self::url('/files/generate-upload-url'), ['content_type' => $contentType])
            ->throw()
            ->json();

        $uploadUrl = $resposta['upload_url'] ?? null;
        $publicUrl = $resposta['public_url'] ?? null;
        if (! $uploadUrl || ! $publicUrl) {
            throw new RuntimeException('Resposta de upload sem upload_url/public_url');
        }

        // Sem Authorization de propósito: a URL pré-assinada já carrega a
        // autorização dela, e mandar a nossa chave pra um host de storage de
        // terceiro é vazamento de credencial.
        Http::withBody(file_get_contents($caminhoAbsoluto), $contentType)
            ->timeout(120)
            ->put($uploadUrl)
            ->throw();

        return $publicUrl;
    }

    /**
     * Dispara a geração de uma demonstração e devolve o request_id.
     *
     * @param  string[]  $referenciasUrl  fotos do modelo já hospedadas (ver enviarArquivo)
     */
    public static function gerarDemonstracao(string $prompt, array $referenciasUrl, bool $video = false): array
    {
        if (! config('higgsfield.habilitado')) {
            throw new RuntimeException('Geração via Higgsfield desligada (HIGGSFIELD_HABILITADO=false)');
        }
        if ($referenciasUrl === []) {
            throw new RuntimeException('É preciso ao menos uma foto de referência');
        }

        $padroes = config('higgsfield.padroes');
        $endpoints = config('higgsfield.endpoints');
        $soulId = config('higgsfield.soul_id');

        if ($video) {
            // reference-to-video aceita várias referências de uma vez, o que dá
            // identidade melhor que uma foto só.
            $caminho = $endpoints['video_referencia'];
            $corpo = [
                'prompt' => $prompt,
                'image_urls' => array_values($referenciasUrl),
                'resolution' => '720',
                'aspect_ratio' => '9:16',
                'duration' => '4',
                'generate_audio' => false,
            ];
        } elseif ($soulId) {
            // Soul ID treinado pelo site: melhor consistência de identidade.
            $caminho = '/higgsfield-ai/soul/character';
            $corpo = [
                'prompt' => $prompt,
                'custom_reference_id' => $soulId,
                'custom_reference_strength' => 0.8,
                'aspect_ratio' => $padroes['aspect_ratio'],
                'resolution' => $padroes['resolution'],
                'batch_size' => $padroes['batch_size'],
            ];
        } else {
            $caminho = $endpoints['imagem_referencia'];
            $corpo = [
                'prompt' => $prompt,
                'image_reference_url' => $referenciasUrl[0],
                'aspect_ratio' => $padroes['aspect_ratio'],
                'resolution' => $padroes['resolution'],
                'batch_size' => $padroes['batch_size'],
            ];
        }

        return self::http()->post(self::url($caminho), $corpo)->throw()->json();
    }

    /** Consulta uma requisição uma única vez. */
    public static function status(string $requestId): array
    {
        return self::http()->get(self::url("/requests/{$requestId}/status"))->throw()->json();
    }

    /**
     * Consulta até chegar num estado terminal. Devolve a resposta final —
     * inclusive quando falha, pra quem chamou decidir o que fazer (o comando
     * de geração registra o motivo em vez de abortar o lote inteiro).
     */
    public static function aguardar(string $requestId, ?callable $aoEsperar = null): array
    {
        $intervalo = (int) config('higgsfield.poll.intervalo_segundos');
        $maximo = (int) config('higgsfield.poll.tentativas_max');

        for ($tentativa = 1; $tentativa <= $maximo; $tentativa++) {
            $resposta = self::status($requestId);
            if (in_array($resposta['status'] ?? '', self::STATUS_TERMINAIS, true)) {
                return $resposta;
            }
            if ($aoEsperar) {
                $aoEsperar($tentativa, $resposta['status'] ?? '?');
            }
            sleep($intervalo);
        }

        throw new RuntimeException("Requisição {$requestId} não terminou em ".($maximo * $intervalo).'s');
    }

    /** URL do arquivo pronto — serve tanto pra imagem quanto pra vídeo. */
    public static function urlDoProduto(array $resposta): ?string
    {
        return $resposta['images'][0]['url']
            ?? $resposta['video']['url']
            ?? null;
    }

    /**
     * Baixa o arquivo gerado pro nosso storage público e devolve o caminho
     * relativo pra gravar em exercises.image_url / video_url.
     *
     * Passo obrigatório, não otimização: a URL do CDN deles expira em ~7 dias.
     */
    public static function baixarProduto(string $url, string $nomeBase, string $extensao): string
    {
        $conteudo = Http::timeout(180)->get($url)->throw()->body();

        $caminho = "exercise-demos/{$nomeBase}.{$extensao}";
        Storage::disk('public')->put($caminho, $conteudo);

        return '/storage/'.$caminho;
    }
}
