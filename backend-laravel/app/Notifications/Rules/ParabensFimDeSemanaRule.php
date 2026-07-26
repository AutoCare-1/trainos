<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use Illuminate\Support\Facades\DB;

/** Toda segunda de manhã, parabeniza quem treinou acima do limiar combinado nos últimos 7 dias. */
class ParabensFimDeSemanaRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'parabens_fim_semana';
    }

    public function avaliar(): array
    {
        if (! now()->isMonday() || (int) now()->format('G') < 8) {
            return [];
        }

        $limiar = config('notificacoes.treinos_minimos_parabens_fim_semana');
        $inicio = now()->subDays(7)->startOfDay();
        $fim = now()->subDay()->endOfDay();
        $hoje = now()->toDateString();

        $rows = DB::table('students as s')
            ->select('s.id', 's.professional_id', 's.invite_token')
            ->selectRaw(
                '(select count(*) from training_sessions ts where ts.student_id = s.id and ts.status = ? and ts.finished_at between ? and ?) as total',
                ['completed', $inicio, $fim]
            )
            ->havingRaw('total >= ?', [$limiar])
            ->get();

        return $rows->map(fn ($r) => new NotificacaoCandidato(
            recipient: Student::find($r->id),
            professionalId: $r->professional_id,
            studentId: $r->id,
            dedupKey: "parabens_fim_semana:{$r->id}:{$hoje}",
            contexto: (string) $r->total,
            titulo: 'Semana e tanto!',
            corpo: "Você treinou {$r->total} vezes na última semana. Parabéns pela consistência!",
            url: "/aluno/{$r->invite_token}",
        ))->all();
    }
}
