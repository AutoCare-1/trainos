<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Fonte única de verdade pra "estagnação de carga": compara a carga máxima da
 * sessão mais recente com a de `janela` sessões atrás, por aluno/exercício —
 * ignorando o que aconteceu nas sessões intermediárias. Usada tanto pelo
 * alerta in-app (AlunoController::contarEstagnacaoPorAluno / alertasEstagnacao,
 * no perfil do aluno visto pelo personal) quanto pela notificação push
 * (EstagnacaoDetectadaRule) — consolidado aqui pra nunca dar veredito
 * divergente entre os dois lugares.
 *
 * Janela ampliada de 2 pra 4 sessões (segunda rodada de revisão). O motivo
 * não é só "suavizar" — é corrigir um falso positivo específico: com janela=2
 * (última vs penúltima), um pico isolado de desempenho (ex: um dia
 * excepcionalmente bom 1 sessão atrás) fazia a sessão seguinte, mesmo normal,
 * parecer "estagnada" só por não superar aquele pico atípico. Comparando com
 * `janela` sessões atrás (não com a intermediária), um pico isolado no meio
 * do caminho deixa de distorcer o veredito — o que importa é se, de ponta a
 * ponta da janela, o aluno progrediu. Como é a mesma fonte usada nos dois
 * lugares, a mudança propaga automaticamente pro push e pro in-app.
 */
class Estagnacao
{
    public const JANELA_PADRAO = 4;

    /**
     * @return array{student_id: string, exercise_id: string, session_id: string, finished_at: string, ultima: float, anterior: float}[]
     */
    public static function compararUltimasSessoes(?string $professionalId = null, int $janela = self::JANELA_PADRAO): array
    {
        $rows = DB::select(
            <<<SQL
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
                     max(case when rn = 1 then session_id end) as session_id,
                     max(case when rn = 1 then finished_at end) as finished_at,
                     max(case when rn = {$janela} then carga_max end) as anterior,
                     count(*) as total_sessoes
              from ranked
              where rn <= {$janela}
              group by student_id, exercise_id
              -- precisa de `janela` sessões completas pra avaliar — com menos
              -- histórico que isso não dá pra dizer se estagnou de verdade.
              having count(*) = {$janela}
            )
            select student_id, exercise_id, session_id, finished_at, ultima, anterior
            from comparacao
            SQL,
            [$professionalId, $professionalId]
        );

        return array_map(fn ($r) => (array) $r, $rows);
    }
}
