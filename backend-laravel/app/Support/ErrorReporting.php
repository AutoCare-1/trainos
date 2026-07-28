<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Ponto único de captura de falha dos 7 pipelines de IA — mantém o
 * `Log::error()` que já era o padrão do projeto, e além disso reporta pro
 * Sentry (se configurado; sem DSN o SDK vira no-op sozinho) com contexto
 * suficiente pra debug, mas NUNCA o conteúdo em si (mensagem do aluno, prompt,
 * foto) — só ids e o nome do pipeline.
 */
class ErrorReporting
{
    /** @param array<string, mixed> $contexto Só ids/metadados (ex: student_id, professional_id) — nunca texto de mensagem, foto ou prompt. */
    public static function capturarFalhaIa(string $pipeline, \Throwable $e, array $contexto = []): void
    {
        Log::error("[IA:{$pipeline}] Falha no pipeline: ".$e->getMessage(), $contexto);

        \Sentry\withScope(function (\Sentry\State\Scope $scope) use ($pipeline, $contexto, $e): void {
            $scope->setTag('ia_pipeline', $pipeline);
            if ($contexto !== []) {
                $scope->setContext('ia_pipeline_contexto', $contexto);
            }
            \Sentry\captureException($e);
        });
    }
}
