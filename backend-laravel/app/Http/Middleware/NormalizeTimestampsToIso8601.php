<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Normaliza timestamps em toda resposta JSON pro mesmo formato ISO 8601 que o
 * driver `pg` do Node sempre entrega (e que os $casts do Eloquent já produzem
 * nas respostas que passam por model). Sem isso, qualquer resposta montada com
 * DB::table()/DB::select() puro (sem passar pelos casts) devolve timestamps no
 * formato nativo do Postgres ("2026-07-24 21:49:21", sem T/Z) — inconsistente
 * com o resto da API e potencialmente mal interpretado por new Date() no browser.
 *
 * Deliberadamente NÃO mexe em strings de data pura ("YYYY-MM-DD", sem hora) —
 * o Node às vezes faz `::text` explícito nessas colunas (ex: checkin_date em
 * checkins.ts) pra manter a data como string simples, e não dá pra distinguir
 * isso de uma coluna date "normal" só pelo formato. Ver Support/Checkins.php.
 */
class NormalizeTimestampsToIso8601
{
    // timestamp do Postgres, com ou sem fração de segundo/timezone, ou já em ISO 8601.
    private const TIMESTAMP_PATTERN = '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}(:?\d{2})?)?$/';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $response->setData(self::normalize($response->getData(true)));
        }

        return $response;
    }

    private static function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([self::class, 'normalize'], $value);
        }

        if (is_string($value) && preg_match(self::TIMESTAMP_PATTERN, $value)) {
            // Timestamps sem offset explícito vêm de colunas `timestamp without time
            // zone` armazenadas em UTC (config/app.php timezone => UTC, sem conversão
            // de sessão no banco) — mesma convenção que os $casts do Eloquent já usam.
            return Carbon::parse($value, 'UTC')->utc()->format('Y-m-d\TH:i:s.u\Z');
        }

        return $value;
    }
}
