<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

class TreinoAcademiaAprovadoRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'treino_academia_aprovado';
    }

    public function avaliar(): array
    {
        $rows = DB::table('gym_workout_recommendations as r')
            ->join('gym_media_submissions as sub', 'sub.id', '=', 'r.submission_id')
            ->join('students as s', 's.id', '=', 'sub.student_id')
            ->where('r.approval_status', 'approved')
            ->where('r.approved_at', '>=', now()->subDay())
            ->select('r.id as recomendacao_id', 'r.name as treino', 's.id as student_id', 's.professional_id', 's.invite_token')
            ->get();

        return $rows->map(fn ($r) => new NotificacaoCandidato(
            recipient: Student::find($r->student_id),
            professionalId: $r->professional_id,
            studentId: $r->student_id,
            dedupKey: "treino_academia_aprovado:{$r->recomendacao_id}",
            contexto: $r->recomendacao_id,
            titulo: 'Treino de academia aprovado',
            corpo: "Seu professor aprovou o treino \"{$r->treino}\" baseado na sua academia.",
            url: "/aluno/{$r->invite_token}",
        ))->all();
    }
}
