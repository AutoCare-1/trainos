<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Mesma comparação de AlunoController::contarEstagnacaoPorAluno (carga máxima da
 * última sessão vs. da penúltima), só que aqui por exercício individual, pra virar
 * push. dedup inclui o id da última sessão: se o aluno treinar de novo e continuar
 * estagnado, isso conta como um novo evento (sessão nova), não reenvio do mesmo.
 */
class EstagnacaoDetectadaRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'estagnacao_detectada';
    }

    public function avaliar(): array
    {
        $rows = DB::select(
            <<<'SQL'
            with cargas as (
              select ts.student_id, we.exercise_id, ts.id as session_id, ts.finished_at,
                     max(se.load_kg_done) as carga_max
              from session_entries se
              join training_sessions ts on ts.id = se.training_session_id
              join workout_exercises we on we.id = se.workout_exercise_id
              where ts.status = 'completed' and se.load_kg_done is not null
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
                     max(case when rn = 1 then session_id end) as ultima_session_id,
                     max(case when rn = 1 then finished_at end) as ultima_finished_at
              from ranked
              where rn <= 2
              group by student_id, exercise_id
              having max(case when rn = 2 then carga_max end) is not null
            )
            select c.student_id, c.exercise_id, c.ultima_session_id, e.name as exercicio,
                   s.professional_id, s.invite_token
            from comparacao c
            join students s on s.id = c.student_id
            join exercises e on e.id = c.exercise_id
            where c.ultima <= c.anterior
              and c.ultima_finished_at >= ?
            SQL,
            [now()->subDay()]
        );

        return collect($rows)->map(fn ($r) => new NotificacaoCandidato(
            recipient: Student::find($r->student_id),
            professionalId: $r->professional_id,
            studentId: $r->student_id,
            dedupKey: "estagnacao_detectada:{$r->student_id}:{$r->exercise_id}:{$r->ultima_session_id}",
            contexto: $r->exercise_id,
            titulo: 'Hora de ajustar a carga?',
            corpo: "Sua carga em {$r->exercicio} não aumentou nas últimas sessões. Vale conversar com seu professor.",
            url: "/aluno/{$r->invite_token}",
        ))->all();
    }
}
