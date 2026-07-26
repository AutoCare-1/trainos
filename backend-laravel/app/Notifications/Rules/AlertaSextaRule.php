<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

/** Toda sexta, mensagem de cuidado/moderação pro fim de semana — pra todo aluno ativo (com treino enviado). */
class AlertaSextaRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'alerta_sexta';
    }

    public function avaliar(): array
    {
        if (! now()->isFriday() || (int) now()->format('G') < 8) {
            return [];
        }

        $hoje = now()->toDateString();

        $rows = DB::table('students as s')
            ->select('s.id', 's.professional_id', 's.invite_token')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('workouts as w')
                ->whereColumn('w.student_id', 's.id')->where('w.status', 'sent'))
            ->get();

        return $rows->map(fn ($r) => new NotificacaoCandidato(
            recipient: Student::find($r->id),
            professionalId: $r->professional_id,
            studentId: $r->id,
            dedupKey: "alerta_sexta:{$r->id}:{$hoje}",
            contexto: null,
            titulo: 'Chegou a sexta!',
            corpo: 'Aproveite o fim de semana com equilíbrio — descanso e moderação também fazem parte do progresso.',
            url: "/aluno/{$r->invite_token}",
        ))->all();
    }
}
