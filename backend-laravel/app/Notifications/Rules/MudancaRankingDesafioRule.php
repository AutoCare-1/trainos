<?php

namespace App\Notifications\Rules;

use App\Models\ChallengeParticipant;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Leaderboard do desafio (DesafioController::show) é sempre calculado na hora —
 * compara com a última posição notificada pra saber se subiu/desceu. Sem
 * posição anterior = primeira observação, não conta como mudança (só grava a
 * posição inicial como base de comparação futura). Guardada direto em
 * challenge_participants.ultima_posicao_notificada (item 12 de revisão externa
 * — antes vivia numa tabela challenge_rank_snapshots dedicada só pra isso, que
 * não se justificava já existindo a tabela de participação aluno-desafio).
 */
class MudancaRankingDesafioRule implements NotificacaoRule
{
    public function chave(): string
    {
        return 'mudanca_ranking_desafio';
    }

    public function avaliar(): array
    {
        $desafiosAtivos = DB::table('challenges')
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->get(['id', 'professional_id', 'start_date', 'end_date']);

        $candidatos = [];

        foreach ($desafiosAtivos as $desafio) {
            $leaderboard = DB::table('challenge_participants as cp')
                ->join('students as s', 's.id', '=', 'cp.student_id')
                ->leftJoin('workouts as w', 'w.student_id', '=', 's.id')
                ->leftJoin('training_sessions as ts', function ($join) {
                    $join->on('ts.workout_id', '=', 'w.id')->on('ts.student_id', '=', 's.id');
                })
                ->where('cp.challenge_id', $desafio->id)
                ->groupBy('cp.id', 's.id', 's.invite_token', 'cp.ultima_posicao_notificada')
                ->orderByDesc('pontos')
                ->select('cp.id as participant_id', 's.id as student_id', 's.invite_token', 'cp.ultima_posicao_notificada')
                ->selectRaw(
                    "sum(case when ts.status = 'completed' and date(ts.finished_at) between ? and ? then 1 else 0 end) as pontos",
                    [$desafio->start_date, $desafio->end_date]
                )
                ->get()
                ->values();

            foreach ($leaderboard as $index => $participante) {
                $posicaoAtual = $index + 1;
                $anterior = $participante->ultima_posicao_notificada;

                if ($anterior !== null && (int) $anterior !== $posicaoAtual) {
                    $subiu = $posicaoAtual < (int) $anterior;
                    $candidatos[] = new NotificacaoCandidato(
                        recipient: Student::find($participante->student_id),
                        professionalId: $desafio->professional_id,
                        studentId: $participante->student_id,
                        dedupKey: "mudanca_ranking_desafio:{$desafio->id}:{$participante->student_id}:{$posicaoAtual}:".now()->toDateString(),
                        contexto: "{$desafio->id}:{$posicaoAtual}",
                        titulo: $subiu ? 'Você subiu no ranking!' : 'Sua posição no ranking mudou',
                        corpo: "Agora você está em {$posicaoAtual}º lugar no desafio.",
                        url: "/aluno/{$participante->invite_token}",
                    );
                }

                ChallengeParticipant::where('id', $participante->participant_id)
                    ->update(['ultima_posicao_notificada' => $posicaoAtual]);
            }
        }

        return $candidatos;
    }
}
