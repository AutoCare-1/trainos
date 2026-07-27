<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * O app não tem conceito de "dia de descanso programado" — não existe agenda
 * semanal nem campo de dias de treino, só um treino ativo (workouts.status)
 * que vale enquanto o personal não trocar. Então não tem como saber "hoje era
 * dia de treino ou de descanso" nesse schema. O que dá pra garantir (e não
 * estava garantido antes): não cobrar no MESMO dia em que o treino foi
 * prescrito — sem isso, um aluno que recebe o treino às 19h já levava um
 * "sem treinar hoje" minutos depois, o que é uma cobrança claramente indevida.
 */
class SemTreinarHojeRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'sem_treinar_hoje';
    }

    public function avaliar(): array
    {
        if ((int) now()->format('G') < config('notificacoes.hora_sem_treinar_hoje')) {
            return [];
        }

        $hoje = now()->toDateString();
        $inicioHoje = now()->startOfDay();

        $rows = DB::table('students as s')
            ->select('s.id', 's.professional_id', 's.invite_token')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('workouts as w')
                ->whereColumn('w.student_id', 's.id')->where('w.status', 'sent')
                ->where('w.sent_at', '<', $inicioHoje))
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('training_sessions as ts')
                ->whereColumn('ts.student_id', 's.id')
                ->where('ts.status', 'completed')
                ->whereDate('ts.finished_at', $hoje))
            ->get();

        return $rows->map(function ($r) use ($hoje) {
            $student = Student::find($r->id);

            return new NotificacaoCandidato(
                recipient: $student,
                professionalId: $r->professional_id,
                studentId: $r->id,
                dedupKey: "sem_treinar_hoje:{$r->id}:{$hoje}",
                contexto: null,
                titulo: NotificacaoCandidato::TITULO_ALUNO_GENERICO,
                corpo: NotificacaoCandidato::CORPO_ALUNO_GENERICO,
                url: "/aluno/{$r->invite_token}",
            );
        })->all();
    }
}
