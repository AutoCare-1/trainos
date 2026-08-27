<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Checagem de ligado/desligado por pipeline de IA, uma linha no início de cada
 * controller, antes de qualquer chamada à API da Anthropic. Ver config/ia_pipelines.php
 * pra mapear pipeline -> env var.
 *
 * Cobre também o teto de gasto diário: o kill-switch sozinho é global — a única
 * reação possível a uma conta abusando era derrubar a feature pra todos os
 * clientes. O teto corta só quem estourou.
 */
class KillSwitchIa
{
    /**
     * @param  string|null  $professionalId  dono da chamada, pra aplicar o teto por personal;
     *                                       null (portal do aluno, comando) cai no teto global
     * @return JsonResponse|null null se pode chamar a IA; resposta 503 pronta se não pode.
     */
    public static function verificar(string $pipeline, ?string $professionalId = null): ?JsonResponse
    {
        if (config("ia_pipelines.{$pipeline}") === false) {
            return self::indisponivel();
        }

        if (self::estourouTetoDiario($pipeline, $professionalId)) {
            return self::indisponivel();
        }

        return null;
    }

    private static function estourouTetoDiario(string $pipeline, ?string $professionalId): bool
    {
        $chaveTeto = $professionalId !== null ? 'teto_diario_usd_por_personal' : 'teto_diario_usd_global';
        $teto = (float) config("ia_pipelines.{$chaveTeto}");
        if ($teto <= 0) {
            return false;
        }

        $gasto = IaUsage::gastoDeHojeUsd($professionalId);
        if ($gasto < $teto) {
            return false;
        }

        Log::warning('Teto diário de gasto com IA atingido', [
            'pipeline' => $pipeline,
            'professional_id' => $professionalId,
            'gasto_usd' => $gasto,
            'teto_usd' => $teto,
        ]);

        return true;
    }

    private static function indisponivel(): JsonResponse
    {
        return response()->json([
            'error' => 'Essa funcionalidade está temporariamente indisponível, tente novamente mais tarde.',
        ], 503);
    }
}
