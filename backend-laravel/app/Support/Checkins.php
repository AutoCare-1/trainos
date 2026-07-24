<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Espelha backend/src/services/checkins.ts do Node.
 *
 * Toda a matemática de data roda dentro do Postgres (date_trunc, generate_series)
 * em vez de em PHP, pra evitar bugs de fuso horário na hora de decidir "que dia é
 * hoje" — o mesmo dia que já foi usado pra gravar o check-in (current_date do
 * banco), sem depender do timezone do processo PHP.
 */
class Checkins
{
    private const LABELS_DIA = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

    /** @return array{inicio: string, fim: string, dias_com_checkin: int, total_dias: int, grid: array} */
    public static function calcularResumoSemana(string $studentId, ?string $ref): array
    {
        $rows = DB::select(
            <<<'SQL'
            select gs::date::text as date,
                   c.id is not null as checked,
                   c.comment as comment
            from generate_series(
              date_trunc('week', coalesce(?::date, current_date)),
              date_trunc('week', coalesce(?::date, current_date)) + interval '6 days',
              interval '1 day'
            ) as gs
            left join checkins c on c.student_id = ? and c.checkin_date = gs::date
            order by gs
            SQL,
            [$ref, $ref, $studentId]
        );

        $grid = [];
        foreach ($rows as $i => $r) {
            $grid[] = [
                'date' => $r->date,
                'label' => self::LABELS_DIA[$i],
                'checked' => (bool) $r->checked,
                'comment' => $r->comment,
            ];
        }

        return [
            'inicio' => $grid[0]['date'] ?? null,
            'fim' => $grid[6]['date'] ?? null,
            'dias_com_checkin' => count(array_filter($grid, fn ($d) => $d['checked'])),
            'total_dias' => 7,
            'grid' => $grid,
        ];
    }

    /** @return array{ano: int, mes: int, dias_com_checkin: int, total_dias_mes: int, dias_marcados: array} */
    public static function calcularResumoMes(string $studentId, ?string $ref): array
    {
        $linha = DB::selectOne(
            <<<'SQL'
            select
               extract(year from date_trunc('month', coalesce(?::date, current_date)))::int as ano,
               extract(month from date_trunc('month', coalesce(?::date, current_date)))::int as mes,
               extract(day from (
                 date_trunc('month', coalesce(?::date, current_date)) + interval '1 month' - interval '1 day'
               ))::int as total_dias_mes,
               count(c.id) as dias_com_checkin,
               coalesce(array_agg(extract(day from c.checkin_date)::int order by c.checkin_date) filter (where c.id is not null), '{}') as dias_marcados
            from (select coalesce(?::date, current_date) as ref) base
            left join checkins c
              on c.student_id = ?
              and c.checkin_date >= date_trunc('month', base.ref)
              and c.checkin_date < date_trunc('month', base.ref) + interval '1 month'
            group by base.ref
            SQL,
            [$ref, $ref, $ref, $ref, $studentId]
        );

        return [
            'ano' => $linha->ano,
            'mes' => $linha->mes,
            'total_dias_mes' => $linha->total_dias_mes,
            'dias_com_checkin' => (int) $linha->dias_com_checkin,
            'dias_marcados' => self::parsePgIntArray($linha->dias_marcados),
        ];
    }

    /** @return array{ano: int, dias_com_checkin: int} */
    public static function calcularResumoAno(string $studentId, ?string $ref): array
    {
        $linha = DB::selectOne(
            <<<'SQL'
            select
               extract(year from date_trunc('year', coalesce(?::date, current_date)))::int as ano,
               count(c.id) as dias_com_checkin
            from (select coalesce(?::date, current_date) as ref) base
            left join checkins c
              on c.student_id = ?
              and c.checkin_date >= date_trunc('year', base.ref)
              and c.checkin_date < date_trunc('year', base.ref) + interval '1 year'
            group by base.ref
            SQL,
            [$ref, $ref, $studentId]
        );

        return ['ano' => $linha->ano, 'dias_com_checkin' => (int) $linha->dias_com_checkin];
    }

    /**
     * Lista as fotos (com data e comentário) do período — pra galeria navegável
     * por semana/mês/ano, mais recente primeiro.
     *
     * @return array<int, array{id: string, checkin_date: string, comment: ?string}>
     */
    public static function listarCheckinsPeriodo(string $studentId, string $period, ?string $ref): array
    {
        // period já vem tipado pelo controller, mas o valor é interpolado direto na query
        // (date_trunc não aceita parâmetro para a unidade) — trava aqui pra qualquer
        // chamador futuro não abrir injection.
        if (! in_array($period, ['week', 'month', 'year'], true)) {
            throw new InvalidArgumentException("period inválido: {$period}");
        }
        $unidade = $period;
        $intervalo = match ($period) {
            'week' => '7 days',
            'month' => '1 month',
            'year' => '1 year',
        };

        $rows = DB::select(
            <<<SQL
            select id, checkin_date::text as checkin_date, comment
            from checkins
            where student_id = ?
              and checkin_date >= date_trunc('{$unidade}', coalesce(?::date, current_date))
              and checkin_date < date_trunc('{$unidade}', coalesce(?::date, current_date)) + interval '{$intervalo}'
            order by checkin_date desc
            SQL,
            [$studentId, $ref, $ref]
        );

        return array_map(fn ($r) => (array) $r, $rows);
    }

    public static function existeCheckinHoje(string $studentId): bool
    {
        $row = DB::selectOne(
            'select 1 as one from checkins where student_id = ? and checkin_date = current_date',
            [$studentId]
        );

        return $row !== null;
    }

    private static function parsePgIntArray(?string $raw): array
    {
        if (! $raw || $raw === '{}') {
            return [];
        }

        return array_map('intval', explode(',', trim($raw, '{}')));
    }
}
