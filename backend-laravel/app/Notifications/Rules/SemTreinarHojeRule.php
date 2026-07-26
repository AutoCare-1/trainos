<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

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

        $rows = DB::table('students as s')
            ->select('s.id', 's.professional_id', 's.invite_token')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('workouts as w')
                ->whereColumn('w.student_id', 's.id')->where('w.status', 'sent'))
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
                titulo: 'Bora treinar hoje?',
                corpo: 'Ainda dá tempo de fazer seu treino de hoje.',
                url: "/aluno/{$r->invite_token}",
            );
        })->all();
    }
}
