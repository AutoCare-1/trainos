<?php

namespace App\Notifications\Rules;

use App\Models\Professional;
use Illuminate\Support\Facades\DB;

/** Toda segunda, resumo pro personal — mesma classificação de risco de NegocioController::index, só contando. */
class ResumoSemanalRiscoRule implements NotificacaoRule
{
    private const DIAS_SEM_TREINAR = 7;

    private const DIAS_ALUNO_NOVO = 14;

    private const TREINOS_MINIMOS_ALUNO_NOVO = 3;

    public function chave(): string
    {
        return 'resumo_semanal_risco';
    }

    public function avaliar(): array
    {
        if (! now()->isMonday() || (int) now()->format('G') < 8) {
            return [];
        }

        $hoje = now()->toDateString();
        $limiteSemTreinar = now()->subDays(self::DIAS_SEM_TREINAR)->toDateTimeString();
        $limiteAlunoNovo = now()->subDays(self::DIAS_ALUNO_NOVO)->toDateTimeString();
        $treinosMinimos = self::TREINOS_MINIMOS_ALUNO_NOVO;

        $rows = DB::select(
            <<<SQL
            with base as (
              select
                s.professional_id,
                coalesce(sessoes.total, 0) as sessoes_concluidas,
                s.created_at,
                exists(select 1 from workouts w where w.student_id = s.id and w.status = 'sent') as tem_treino_enviado,
                ultima_sessao.finished_at as ultima_sessao_em
              from students s
              left join (
                select ts.student_id, count(*) as total from training_sessions ts
                where ts.status = 'completed' group by ts.student_id
              ) sessoes on sessoes.student_id = s.id
              left join (
                select ts.student_id, max(ts.finished_at) as finished_at from training_sessions ts
                where ts.status = 'completed' group by ts.student_id
              ) ultima_sessao on ultima_sessao.student_id = s.id
            )
            select professional_id, count(*) as total_em_risco
            from base
            where (created_at > ? and sessoes_concluidas < {$treinosMinimos})
               or (tem_treino_enviado and (ultima_sessao_em is null or ultima_sessao_em < ?))
            group by professional_id
            SQL,
            [$limiteAlunoNovo, $limiteSemTreinar]
        );

        return collect($rows)->filter(fn ($r) => (int) $r->total_em_risco > 0)->map(fn ($r) => new NotificacaoCandidato(
            recipient: Professional::find($r->professional_id),
            professionalId: $r->professional_id,
            studentId: null,
            dedupKey: "resumo_semanal_risco:{$r->professional_id}:{$hoje}",
            contexto: null,
            // Item 9 da revisão: risco de abandono é informação de negócio sensível, não vai na tela de bloqueio.
            titulo: NotificacaoCandidato::TITULO_PERSONAL_GENERICO,
            corpo: NotificacaoCandidato::CORPO_PERSONAL_GENERICO,
            url: '/negocio',
        ))->all();
    }
}
