<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

/** TreinoController::enviar sempre atualiza sent_at, mesmo em reenvio — cada sent_at novo é um evento novo. */
class NovoTreinoEnviadoRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'novo_treino_enviado';
    }

    public function avaliar(): array
    {
        $rows = DB::table('workouts as w')
            ->join('students as s', 's.id', '=', 'w.student_id')
            ->where('w.status', 'sent')
            ->where('w.sent_at', '>=', now()->subDay())
            ->select('w.id as workout_id', 'w.sent_at', 'w.name as treino', 's.id as student_id', 's.professional_id', 's.invite_token')
            ->get();

        return $rows->map(fn ($r) => new NotificacaoCandidato(
            recipient: Student::find($r->student_id),
            professionalId: $r->professional_id,
            studentId: $r->student_id,
            dedupKey: "novo_treino_enviado:{$r->workout_id}:{$r->sent_at}",
            contexto: $r->workout_id,
            titulo: 'Novo treino disponível',
            corpo: "Seu professor enviou o treino \"{$r->treino}\".",
            url: "/aluno/{$r->invite_token}",
        ))->all();
    }
}
