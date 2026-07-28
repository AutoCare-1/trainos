<?php

namespace App\Notifications\Rules;

use App\Models\Student;
use App\Support\Gamification;
use Illuminate\Support\Facades\DB;

/**
 * Badges (App\Support\Gamification) são calculados na hora, não persistidos —
 * aqui a gente detecta "acabou de bater o marco": total de sessões concluídas
 * bate exatamente no limiar (1/10/30) ou a sequência de hoje bate exatamente 7/30.
 */
class MedalhaConquistadaRule implements NotificacaoRule
{
    private const MARCOS_TOTAL = [
        1 => ['primeiro_treino', '🥉 Primeiro treino concluído!', 'Você deu o primeiro passo. Bora manter o ritmo.'],
        10 => ['dez_treinos', '💪 10 treinos concluídos!', 'Já são 10 treinos na conta. Consistência de verdade.'],
        30 => ['trinta_treinos', '🏆 30 treinos concluídos!', 'Marco de 30 treinos batido. Isso já é um hábito.'],
    ];

    private const MARCOS_STREAK = [
        7 => ['sequencia_7', '🔥 Sequência de 7 dias!', 'Uma semana treinando sem parar. Muito bom.'],
        30 => ['sequencia_30', '⚡ Sequência de 30 dias!', 'Um mês inteiro de sequência. Impressionante.'],
    ];

    public function chave(): string
    {
        return 'medalha_conquistada';
    }

    public function avaliar(): array
    {
        $candidatos = [];

        foreach (self::MARCOS_TOTAL as $limiar => [$badgeId, $titulo, $corpo]) {
            // Filtra em PHP em vez de HAVING sobre a coluna computada: HAVING
            // sem GROUP BY funciona no MySQL (produção) mas quebra no SQLite
            // (suite de testes) com "HAVING clause on a non-aggregate query".
            $rows = DB::table('students as s')
                ->select('s.id', 's.professional_id', 's.invite_token')
                ->selectRaw(
                    '(select count(*) from training_sessions ts where ts.student_id = s.id and ts.status = ?) as total',
                    ['completed']
                )
                ->get()
                ->filter(fn ($r) => (int) $r->total === $limiar);

            foreach ($rows as $r) {
                $candidatos[] = $this->montar($r, $badgeId, $titulo, $corpo);
            }
        }

        // Streak só faz sentido pra quem treinou hoje (senão calcularStreak já não conta mais a sequência).
        $treinaramHoje = DB::table('students as s')
            ->select('s.id', 's.professional_id', 's.invite_token')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('training_sessions as ts')
                ->whereColumn('ts.student_id', 's.id')
                ->where('ts.status', 'completed')
                ->whereDate('ts.finished_at', now()->toDateString()))
            ->get();

        foreach ($treinaramHoje as $r) {
            $datas = DB::table('training_sessions')
                ->where('student_id', $r->id)->where('status', 'completed')
                ->pluck('finished_at')->all();
            $streak = Gamification::calcularStreak($datas);

            if (isset(self::MARCOS_STREAK[$streak])) {
                [$badgeId, $titulo, $corpo] = self::MARCOS_STREAK[$streak];
                $candidatos[] = $this->montar($r, $badgeId, $titulo, $corpo);
            }
        }

        return $candidatos;
    }

    private function montar(object $r, string $badgeId, string $titulo, string $corpo): NotificacaoCandidato
    {
        return new NotificacaoCandidato(
            recipient: Student::find($r->id),
            professionalId: $r->professional_id,
            studentId: $r->id,
            dedupKey: "medalha_conquistada:{$r->id}:{$badgeId}",
            contexto: $badgeId,
            titulo: $titulo,
            corpo: $corpo,
            url: "/aluno/{$r->invite_token}",
        );
    }
}
