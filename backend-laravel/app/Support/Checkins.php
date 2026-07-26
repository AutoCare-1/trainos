<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Espelha backend/src/services/checkins.ts do Node.
 *
 * O original fazia toda a matemática de data dentro do Postgres (date_trunc,
 * generate_series) pra evitar bugs de fuso horário. MySQL não tem equivalente
 * a nenhuma das duas funções, então a matemática de data (início/fim de
 * semana-mês-ano) foi movida pra PHP via Carbon — mas usando sempre
 * config('app.timezone') = UTC (mesma convenção já usada no resto do app,
 * ver Middleware/NormalizeTimestampsToIso8601), então o resultado é o mesmo
 * "hoje" que o banco usaria. `checkin_date` é coluna DATE pura (sem hora),
 * comparada sempre por string 'Y-m-d', sem ambiguidade de timezone.
 */
class Checkins
{
    private const LABELS_DIA = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];

    /** @return array{inicio: string, fim: string, dias_com_checkin: int, total_dias: int, grid: array} */
    public static function calcularResumoSemana(string $studentId, ?string $ref): array
    {
        $inicio = self::referencia($ref)->startOfWeek(Carbon::MONDAY);
        $fim = $inicio->copy()->addDays(6);

        $rows = DB::select(
            'select checkin_date, comment from checkins where student_id = ? and checkin_date between ? and ?',
            [$studentId, $inicio->toDateString(), $fim->toDateString()]
        );
        $porData = [];
        foreach ($rows as $r) {
            $porData[(string) $r->checkin_date] = $r->comment;
        }

        $grid = [];
        for ($i = 0; $i < 7; $i++) {
            $data = $inicio->copy()->addDays($i)->toDateString();
            $checked = array_key_exists($data, $porData);
            $grid[] = [
                'date' => $data,
                'label' => self::LABELS_DIA[$i],
                'checked' => $checked,
                'comment' => $checked ? $porData[$data] : null,
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
        $inicioMes = self::referencia($ref)->startOfMonth();
        $fimMes = $inicioMes->copy()->endOfMonth();

        $rows = DB::select(
            'select checkin_date from checkins where student_id = ? and checkin_date between ? and ? order by checkin_date',
            [$studentId, $inicioMes->toDateString(), $fimMes->toDateString()]
        );

        return [
            'ano' => $inicioMes->year,
            'mes' => $inicioMes->month,
            'total_dias_mes' => $inicioMes->daysInMonth,
            'dias_com_checkin' => count($rows),
            'dias_marcados' => array_map(fn ($r) => Carbon::parse($r->checkin_date)->day, $rows),
        ];
    }

    /** @return array{ano: int, dias_com_checkin: int} */
    public static function calcularResumoAno(string $studentId, ?string $ref): array
    {
        $inicioAno = self::referencia($ref)->startOfYear();
        $fimAno = $inicioAno->copy()->endOfYear();

        $diasComCheckin = DB::table('checkins')
            ->where('student_id', $studentId)
            ->whereBetween('checkin_date', [$inicioAno->toDateString(), $fimAno->toDateString()])
            ->count();

        return ['ano' => $inicioAno->year, 'dias_com_checkin' => $diasComCheckin];
    }

    /**
     * Lista as fotos (com data e comentário) do período — pra galeria navegável
     * por semana/mês/ano, mais recente primeiro.
     *
     * @return array<int, array{id: string, checkin_date: string, comment: ?string}>
     */
    public static function listarCheckinsPeriodo(string $studentId, string $period, ?string $ref): array
    {
        if (! in_array($period, ['week', 'month', 'year'], true)) {
            throw new InvalidArgumentException("period inválido: {$period}");
        }

        $refDate = self::referencia($ref);
        [$inicio, $fim] = match ($period) {
            'week' => [$refDate->copy()->startOfWeek(Carbon::MONDAY), $refDate->copy()->startOfWeek(Carbon::MONDAY)->addDays(6)],
            'month' => [$refDate->copy()->startOfMonth(), $refDate->copy()->endOfMonth()],
            'year' => [$refDate->copy()->startOfYear(), $refDate->copy()->endOfYear()],
        };

        $rows = DB::select(
            'select id, checkin_date, comment from checkins where student_id = ? and checkin_date between ? and ? order by checkin_date desc',
            [$studentId, $inicio->toDateString(), $fim->toDateString()]
        );

        return array_map(fn ($r) => (array) $r, $rows);
    }

    public static function existeCheckinHoje(string $studentId): bool
    {
        $row = DB::selectOne(
            'select 1 as one from checkins where student_id = ? and checkin_date = curdate()',
            [$studentId]
        );

        return $row !== null;
    }

    private static function referencia(?string $ref): Carbon
    {
        return $ref ? Carbon::parse($ref) : Carbon::now();
    }
}
