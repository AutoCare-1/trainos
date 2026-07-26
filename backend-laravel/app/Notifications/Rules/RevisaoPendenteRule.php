<?php

namespace App\Notifications\Rules;

use App\Models\Professional;
use Illuminate\Support\Facades\DB;

/**
 * Só análise de academia tem fluxo de aprovação hoje (gym_workout_recommendations.approval_status)
 * — análise de forma manda feedback direto pro aluno, sem revisão do personal.
 * dedup por dia: nudge no máximo 1x/dia enquanto ficar pendente, não a cada 15min.
 */
class RevisaoPendenteRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'revisao_pendente';
    }

    public function avaliar(): array
    {
        $hoje = now()->toDateString();

        $rows = DB::table('gym_workout_recommendations as r')
            ->join('gym_media_submissions as sub', 'sub.id', '=', 'r.submission_id')
            ->join('students as s', 's.id', '=', 'sub.student_id')
            ->where('r.approval_status', 'pending')
            ->select('r.id as recomendacao_id', 's.name as aluno', 's.professional_id')
            ->get();

        return $rows->map(fn ($r) => new NotificacaoCandidato(
            recipient: Professional::find($r->professional_id),
            professionalId: $r->professional_id,
            studentId: null,
            dedupKey: "revisao_pendente:{$r->recomendacao_id}:{$hoje}",
            contexto: $r->recomendacao_id,
            titulo: 'Análise de academia aguardando revisão',
            corpo: "A recomendação de treino de {$r->aluno} está esperando sua aprovação.",
            url: '/academia',
        ))->all();
    }
}
