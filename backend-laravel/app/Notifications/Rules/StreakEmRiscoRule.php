<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use App\Support\Gamification;
use Illuminate\Support\Facades\DB;

/** Aluno com sequência de dias treinando ainda não quebrada, mas que some se ele não treinar hoje. */
class StreakEmRiscoRule implements NotificacaoRule
{
    private const STREAK_MINIMO = 3;

    public function chave(): string
    {
        return 'streak_em_risco';
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

        $candidatos = [];
        foreach ($rows as $r) {
            $datas = DB::table('training_sessions')
                ->where('student_id', $r->id)->where('status', 'completed')
                ->pluck('finished_at')->all();

            $streak = Gamification::calcularStreak($datas);
            if ($streak < self::STREAK_MINIMO) {
                continue;
            }

            $student = Student::find($r->id);
            $candidatos[] = new NotificacaoCandidato(
                recipient: $student,
                professionalId: $r->professional_id,
                studentId: $r->id,
                dedupKey: "streak_em_risco:{$r->id}:{$hoje}",
                contexto: (string) $streak,
                titulo: "Sua sequência de {$streak} dias está em risco",
                corpo: 'Treine hoje pra manter sua sequência viva.',
                url: "/aluno/{$r->invite_token}",
            );
        }

        return $candidatos;
    }
}
