<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Fonte única de verdade pra "estagnação de carga": compara a carga máxima da
 * última sessão concluída com a da penúltima, por aluno/exercício. Usada tanto
 * pelo alerta in-app (AlunoController::contarEstagnacaoPorAluno, no perfil do
 * aluno visto pelo personal) quanto pela notificação push
 * (EstagnacaoDetectadaRule) — item 5/6 de uma revisão externa apontou o risco
 * de existir um "veredito" divergente entre os dois lugares se cada um tivesse
 * sua própria cópia da lógica; consolidado aqui pra nunca mais divergir.
 *
 * Compara só as duas últimas sessões de propósito (não ampliei pra 3-4 sessões
 * como uma sugestão da revisão pedia): mudar esse critério mudaria o
 * comportamento do alerta in-app já existente, que não foi apontado como
 * problema em si — é uma decisão de produto separada, não uma correção de bug
 * da notificação push.
 */
class Estagnacao
{
    /**
     * @return array{student_id: string, exercise_id: string, session_id: string, finished_at: string, ultima: float, anterior: float}[]
     */
    public static function compararUltimasDuasSessoes(?string $professionalId = null): array
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
              where ts.status = 'completed' and se.load_kg_done is not null
                and (? is null or s.professional_id = ?)
              group by ts.student_id, we.exercise_id, ts.id, ts.finished_at
            ),
            ranked as (
              select *, row_number() over (partition by student_id, exercise_id order by finished_at desc) as rn
              from cargas
            ),
            comparacao as (
              select student_id, exercise_id,
                     max(case when rn = 1 then carga_max end) as ultima,
                     max(case when rn = 2 then carga_max end) as anterior,
                     max(case when rn = 1 then session_id end) as session_id,
                     max(case when rn = 1 then finished_at end) as finished_at
              from ranked
              where rn <= 2
              group by student_id, exercise_id
              having max(case when rn = 2 then carga_max end) is not null
            )
            select student_id, exercise_id, session_id, finished_at, ultima, anterior
            from comparacao
            SQL,
            [$professionalId, $professionalId]
        );

        return array_map(fn ($r) => (array) $r, $rows);
    }
}
