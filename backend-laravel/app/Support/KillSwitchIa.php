<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Checagem de ligado/desligado por pipeline de IA, uma linha no início de cada
 * controller, antes de qualquer chamada à API da Anthropic. Ver config/ia_pipelines.php
 * pra mapear pipeline -> env var.
 */
class KillSwitchIa
{
    /** @return JsonResponse|null null se o pipeline está ativo; resposta 503 pronta se está desligado. */
    public static function verificar(string $pipeline): ?JsonResponse
    {
        if (config("ia_pipelines.{$pipeline}") === false) {
            return response()->json([
                'error' => 'Essa funcionalidade está temporariamente indisponível, tente novamente mais tarde.',
            ], 503);
        }

        return null;
    }
}
