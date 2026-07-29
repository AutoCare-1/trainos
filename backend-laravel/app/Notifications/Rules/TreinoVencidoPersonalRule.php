<?php

namespace App\Notifications\Rules;

use App\Models\Professional;
use Illuminate\Support\Facades\DB;

/**
 * Mesmo evento de TreinoVencidoAlunoRule, mas pro personal — regra separada
 * (não a mesma Rule pros dois destinatários) porque cada público tem seu próprio
 * toggle de preferência e sua própria chave de deduplicação.
 */
class TreinoVencidoPersonalRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'treino_vencido_personal';
    }

    public function avaliar(): array
    {
        $hoje = now()->toDateString();

        $rows = DB::table('workouts as w')
            ->join('students as s', 's.id', '=', 'w.student_id')
            ->where('w.status', 'sent')
            ->whereNull('w.archived_at')
            ->whereNotNull('w.expires_at')
            ->where('w.expires_at', '<', $hoje)
            ->select('w.id as workout_id', 'w.name as treino', 's.id as student_id', 's.name as aluno', 's.professional_id')
            ->get();

        return $rows->map(fn ($r) => new NotificacaoCandidato(
            recipient: Professional::find($r->professional_id),
            professionalId: $r->professional_id,
            studentId: $r->student_id,
            dedupKey: "treino_vencido_personal:{$r->workout_id}",
            contexto: $r->workout_id,
            titulo: NotificacaoCandidato::TITULO_PERSONAL_GENERICO,
            corpo: NotificacaoCandidato::CORPO_PERSONAL_GENERICO,
            url: "/alunos/{$r->student_id}",
        ))->all();
    }
}
