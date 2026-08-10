<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Registra o consumo da API da Anthropic por chamada, pra que o CRM saiba
 * quanto o produto gasta com IA. Uma linha por chamada em ia_usage_logs, com o
 * custo em USD já congelado (ver config/ia_precos.php).
 *
 * Chamado logo depois de cada `messages->create()` nos Support de IA. É
 * deliberadamente à prova de falha: se registrar der errado por qualquer motivo,
 * loga e segue — contabilidade interna nunca pode derrubar uma resposta que o
 * usuário já recebeu.
 */
class IaUsage
{
    /**
     * @param  string  $pipeline  chave do pipeline (ver config/ia_pipelines.php)
     * @param  object  $response  resposta do SDK (precisa ter ->usage)
     * @param  string|null  $professionalId  dono da chamada; omitido = resolve pelo request
     * @param  int  $webSearches  buscas da tool web_search, cobradas por busca
     */
    public static function registrar(
        string $pipeline,
        object $response,
        ?string $professionalId = null,
        int $webSearches = 0,
    ): void {
        try {
            $usage = $response->usage ?? null;
            if (! $usage) {
                return;
            }

            $professionalId ??= self::profissionalDoRequest();

            $model = $response->model ?? config('ia_precos.modelo_padrao');

            $input = (int) ($usage->inputTokens ?? 0);
            $output = (int) ($usage->outputTokens ?? 0);
            $cacheWrite = (int) ($usage->cacheCreationInputTokens ?? 0);
            $cacheRead = (int) ($usage->cacheReadInputTokens ?? 0);

            DB::table('ia_usage_logs')->insert([
                'id' => (string) Str::uuid(),
                'professional_id' => $professionalId,
                'pipeline' => $pipeline,
                'model' => $model,
                'input_tokens' => $input,
                'output_tokens' => $output,
                'cache_creation_input_tokens' => $cacheWrite,
                'cache_read_input_tokens' => $cacheRead,
                'web_searches' => $webSearches,
                'custo_usd' => self::calcularCustoUsd($model, $input, $output, $cacheWrite, $cacheRead, $webSearches),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Nunca propaga: a resposta da IA já foi produzida e entregue.
            Log::warning('Falha ao registrar consumo de IA', [
                'pipeline' => $pipeline,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dono da chamada, quando ela nasce de uma rota autenticada por JWT.
     * JwtAuthenticate injeta um Professional "leve" via setUserResolver; nas
     * rotas do portal do aluno (autenticadas por invite_token) não há resolver,
     * então devolve null — o custo continua sendo contabilizado, só sem dono.
     */
    private static function profissionalDoRequest(): ?string
    {
        try {
            $id = request()?->user()?->id;

            return is_string($id) ? $id : null;
        } catch (\Throwable) {
            // Sem request no contexto (comando artisan, fila, teste unitário).
            return null;
        }
    }

    /**
     * Custo em USD de uma chamada. Preços são por milhão de tokens; cache de
     * escrita e leitura têm multiplicadores próprios sobre o preço de entrada.
     *
     * Modelo sem preço cadastrado devolve 0 — o CRM mostra esse caso
     * explicitamente (ver Crm::modelosSemPreco) em vez de chutar um valor.
     */
    public static function calcularCustoUsd(
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $cacheWriteTokens = 0,
        int $cacheReadTokens = 0,
        int $webSearches = 0,
    ): float {
        $precos = config("ia_precos.modelos.{$model}");
        if (! $precos) {
            return 0.0;
        }

        $porMilhao = 1_000_000;

        $custo = ($inputTokens / $porMilhao) * $precos['input']
            + ($outputTokens / $porMilhao) * $precos['output']
            + ($cacheWriteTokens / $porMilhao) * $precos['input'] * $precos['cache_write']
            + ($cacheReadTokens / $porMilhao) * $precos['input'] * $precos['cache_read']
            + $webSearches * (float) config('ia_precos.web_search_por_busca');

        return round($custo, 8);
    }
}
