<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Espelha backend/src/services/consultorFerramentas.ts do Node.
 *
 * Todas as funções abaixo são as ÚNICAS formas da IA do Consultor acessar dado
 * de aluno — cada uma recebe $professionalId (vindo do JWT, nunca do input do
 * usuário/IA) e filtra por ele em toda query, com bind parameters. A IA nunca
 * gera SQL nem acessa o banco diretamente.
 */
class ConsultorFerramentas
{
    /** @return array{id: string, name: string}|null */
    private static function encontrarAluno(string $professionalId, string $nomeOuId): ?array
    {
        $nomeOuId = trim($nomeOuId);
        $pareceUuid = (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $nomeOuId);

        if ($pareceUuid) {
            $row = DB::selectOne(
                'select id, name from students where id = ? and professional_id = ?',
                [$nomeOuId, $professionalId]
            );

            return $row ? (array) $row : null;
        }

        $row = DB::selectOne(
            'select id, name from students where professional_id = ? and name ilike ? order by name limit 1',
            [$professionalId, "%{$nomeOuId}%"]
        );

        return $row ? (array) $row : null;
    }

    public static function buscarResumoAluno(string $professionalId, string $nomeOuId): array
    {
        $aluno = self::encontrarAluno($professionalId, $nomeOuId);
        if (! $aluno) {
            return [
                'encontrado' => false,
                'mensagem' => "Nenhum aluno chamado \"{$nomeOuId}\" encontrado na base deste personal.",
            ];
        }

        $semana = DB::selectOne(
            <<<'SQL'
            select count(*) as dias_com_checkin, 7 as total_dias
            from checkins
            where student_id = ?
              and checkin_date >= date_trunc('week', current_date)
              and checkin_date < date_trunc('week', current_date) + interval '7 days'
            SQL,
            [$aluno['id']]
        );

        $prs = DB::select(
            <<<'SQL'
            with entradas as (
              select se.load_kg_done, se.created_at, we.exercise_id
              from session_entries se
              join training_sessions ts on ts.id = se.training_session_id
              join workout_exercises we on we.id = se.workout_exercise_id
              where ts.student_id = ? and se.load_kg_done is not null
            ),
            com_max_anterior as (
              select *,
                max(load_kg_done) over (
                  partition by exercise_id order by created_at rows between unbounded preceding and 1 preceding
                ) as max_anterior
              from entradas
            )
            select e.name as exercise_name, c.load_kg_done, c.created_at
            from com_max_anterior c
            join exercises e on e.id = c.exercise_id
            where c.created_at >= now() - interval '14 days'
              and c.load_kg_done > coalesce(c.max_anterior, 0)
            order by c.created_at desc
            SQL,
            [$aluno['id']]
        );

        $evolucao = DB::selectOne(
            'select ai_feedback, taken_at from body_photos where student_id = ? order by taken_at desc limit 1',
            [$aluno['id']]
        );

        return [
            'encontrado' => true,
            'nome' => $aluno['name'],
            'checkins_semana_atual' => ($semana->dias_com_checkin ?? 0).' de '.($semana->total_dias ?? 7).' dias',
            'prs_ultimos_14_dias' => array_map(fn ($r) => [
                'exercicio' => $r->exercise_name,
                'carga_kg' => $r->load_kg_done,
                'data' => $r->created_at,
            ], $prs),
            'ultimo_comentario_evolucao_fisica' => $evolucao
                ? ['comentario' => $evolucao->ai_feedback, 'data' => $evolucao->taken_at]
                : null,
        ];
    }

    public static function listarAlunosSemCheckin(string $professionalId, int $dias): array
    {
        $rows = DB::select(
            <<<'SQL'
            select s.name
            from students s
            where s.professional_id = ?
              and not exists (
                select 1 from checkins c
                where c.student_id = s.id and c.checkin_date >= current_date - (?::int - 1)
              )
            order by s.name
            SQL,
            [$professionalId, $dias]
        );

        return ['periodo_dias' => $dias, 'alunos_sem_checkin' => array_map(fn ($r) => $r->name, $rows)];
    }

    public static function listarPrsRecentes(string $professionalId, int $dias): array
    {
        $rows = DB::select(
            <<<'SQL'
            with entradas as (
              select se.load_kg_done, se.created_at, we.exercise_id, ts.student_id
              from session_entries se
              join training_sessions ts on ts.id = se.training_session_id
              join workout_exercises we on we.id = se.workout_exercise_id
              join students s on s.id = ts.student_id
              where s.professional_id = ? and se.load_kg_done is not null
            ),
            com_max_anterior as (
              select *,
                max(load_kg_done) over (
                  partition by student_id, exercise_id order by created_at rows between unbounded preceding and 1 preceding
                ) as max_anterior
              from entradas
            )
            select s.name as aluno, e.name as exercicio, c.load_kg_done as carga_kg, c.created_at as data
            from com_max_anterior c
            join students s on s.id = c.student_id
            join exercises e on e.id = c.exercise_id
            where c.created_at >= now() - (?::int * interval '1 day')
              and c.load_kg_done > coalesce(c.max_anterior, 0)
            order by c.created_at desc
            SQL,
            [$professionalId, $dias]
        );

        return ['periodo_dias' => $dias, 'prs' => $rows];
    }

    public static function listarEstagnados(string $professionalId): array
    {
        $rows = DB::select(
            <<<'SQL'
            with cargas as (
              select ts.student_id, we.exercise_id, ts.id as session_id, ts.finished_at,
                     max(se.load_kg_done) as carga_max
              from session_entries se
              join training_sessions ts on ts.id = se.training_session_id
              join workout_exercises we on we.id = se.workout_exercise_id
              join students s on s.id = ts.student_id
              where s.professional_id = ? and ts.status = 'completed' and se.load_kg_done is not null
              group by ts.student_id, we.exercise_id, ts.id, ts.finished_at
            ),
            ranked as (
              select *, row_number() over (partition by student_id, exercise_id order by finished_at desc) as rn
              from cargas
            ),
            comparacao as (
              select student_id, exercise_id,
                     max(case when rn = 1 then carga_max end) as ultima,
                     max(case when rn = 2 then carga_max end) as anterior
              from ranked
              where rn <= 2
              group by student_id, exercise_id
              having max(case when rn = 2 then carga_max end) is not null
            )
            select s.name as aluno, e.name as exercicio, c.ultima as ultima_carga, c.anterior as carga_anterior
            from comparacao c
            join students s on s.id = c.student_id
            join exercises e on e.id = c.exercise_id
            where c.ultima <= c.anterior
            order by s.name, e.name
            SQL,
            [$professionalId]
        );

        return ['alunos_estagnados' => $rows];
    }

    public static function listarAlunosMaisConsistentes(string $professionalId): array
    {
        $rows = DB::select(
            <<<'SQL'
            select s.name as aluno,
                   count(c.id) filter (
                     where c.checkin_date >= date_trunc('week', current_date)
                     and c.checkin_date < date_trunc('week', current_date) + interval '7 days'
                   ) as dias_semana_atual,
                   count(c.id) filter (
                     where c.checkin_date >= date_trunc('month', current_date)
                     and c.checkin_date < date_trunc('month', current_date) + interval '1 month'
                   ) as dias_mes_atual
            from students s
            left join checkins c on c.student_id = s.id
            where s.professional_id = ?
            group by s.id, s.name
            having count(c.id) filter (
              where c.checkin_date >= date_trunc('month', current_date)
              and c.checkin_date < date_trunc('month', current_date) + interval '1 month'
            ) > 0
            order by dias_mes_atual desc, dias_semana_atual desc, s.name
            SQL,
            [$professionalId]
        );

        return [
            'ranking' => array_map(fn ($r) => [
                'aluno' => $r->aluno,
                'dias_com_checkin_semana_atual' => (int) $r->dias_semana_atual,
                'dias_com_checkin_mes_atual' => (int) $r->dias_mes_atual,
            ], $rows),
        ];
    }
}
