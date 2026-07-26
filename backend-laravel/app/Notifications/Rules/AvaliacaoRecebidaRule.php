<?php

namespace App\Notifications\Rules;

use App\Models\Professional;
use Illuminate\Support\Facades\DB;

/** students.onboarding_completed_at só é setado uma vez, no primeiro PAR-Q respondido pelo aluno (PortalController::avaliacao). */
class AvaliacaoRecebidaRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'avaliacao_recebida';
    }

    public function avaliar(): array
    {
        $rows = DB::table('students')
            ->whereNotNull('onboarding_completed_at')
            ->where('onboarding_completed_at', '>=', now()->subDay())
            ->select('id', 'name', 'professional_id')
            ->get();

        return $rows->map(fn ($r) => new NotificacaoCandidato(
            recipient: Professional::find($r->professional_id),
            professionalId: $r->professional_id,
            studentId: $r->id,
            dedupKey: "avaliacao_recebida:{$r->id}",
            contexto: null,
            titulo: 'Nova avaliação recebida',
            corpo: "{$r->name} acabou de responder a avaliação de saúde (PAR-Q).",
            url: "/alunos/{$r->id}",
        ))->all();
    }
}
