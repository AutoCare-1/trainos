<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NegocioController extends Controller
{
    private const DIAS_SEM_TREINAR = 7;

    private const DIAS_SEM_CHECKIN = 7;

    private const DIAS_ALUNO_NOVO = 14;

    private const TREINOS_MINIMOS_ALUNO_NOVO = 3;

    private static function formatarMotivo(object $r): string
    {
        if ($r->motivo_codigo === 'novo_sem_treinos') {
            $sessoes = (int) $r->sessoes_concluidas;

            return "Cadastrado há {$r->dias_desde_cadastro}d, ainda com só {$sessoes} treino".($sessoes === 1 ? '' : 's').' concluído'.($sessoes === 1 ? '' : 's');
        }
        if ($r->motivo_codigo === 'sem_treinar') {
            return is_null($r->dias_sem_treinar) ? 'Nunca completou um treino' : "Sem treinar há {$r->dias_sem_treinar}d";
        }

        return is_null($r->dias_sem_checkin) ? 'Nunca fez um check-in' : "Sem check-in há {$r->dias_sem_checkin}d";
    }

    // GET / — visão geral do negócio do personal: KPIs de base de alunos + quem está em risco de abandono
    public function index(Request $request): JsonResponse
    {
        $professionalId = $request->user()->id;
        $diasSemTreinar = self::DIAS_SEM_TREINAR;
        $diasSemCheckin = self::DIAS_SEM_CHECKIN;
        $diasAlunoNovo = self::DIAS_ALUNO_NOVO;
        $treinosMinimosAlunoNovo = self::TREINOS_MINIMOS_ALUNO_NOVO;

        $kpis = DB::selectOne(
            <<<SQL
            select
               count(*) as total_alunos,
               count(*) filter (where s.created_at >= date_trunc('month', now())) as novos_no_mes,
               count(*) filter (
                 where existe_treino_enviado.enviado
                   and (ultima_sessao.finished_at is null or ultima_sessao.finished_at < now() - interval '{$diasSemTreinar} days')
               ) as inativos
             from students s
             left join lateral (
               select exists(select 1 from workouts w where w.student_id = s.id and w.status = 'sent') as enviado
             ) existe_treino_enviado on true
             left join lateral (
               select max(ts.finished_at) as finished_at
               from training_sessions ts
               where ts.student_id = s.id and ts.status = 'completed'
             ) ultima_sessao on true
             where s.professional_id = ?
            SQL,
            [$professionalId]
        );

        $retencao = DB::selectOne(
            <<<'SQL'
            with antigos as (
               select id from students
               where professional_id = ? and created_at < date_trunc('month', now())
             )
             select
               (select count(*) from antigos) as denominador,
               (select count(*) from antigos a
                where exists (
                  select 1 from training_sessions ts
                  where ts.student_id = a.id and ts.status = 'completed' and ts.finished_at >= date_trunc('month', now())
                ) or exists (
                  select 1 from checkins c
                  where c.student_id = a.id and c.checkin_date >= date_trunc('month', now())::date
                )
               ) as numerador
            SQL,
            [$professionalId]
        );
        $denominador = (int) $retencao->denominador;
        $retencaoPct = $denominador > 0 ? (int) round(((int) $retencao->numerador / $denominador) * 100) : null;

        $risco = DB::select(
            <<<SQL
            with base as (
               select
                 s.id, s.name, s.created_at,
                 extract(day from now() - s.created_at)::int as dias_desde_cadastro,
                 coalesce(sessoes.total, 0)::int as sessoes_concluidas,
                 existe_treino_enviado.enviado as tem_treino_enviado,
                 ultima_sessao.finished_at as ultima_sessao_em,
                 ultimo_checkin.checkin_date as ultimo_checkin_em
               from students s
               left join lateral (
                 select count(*) as total from training_sessions ts
                 where ts.student_id = s.id and ts.status = 'completed'
               ) sessoes on true
               left join lateral (
                 select exists(select 1 from workouts w where w.student_id = s.id and w.status = 'sent') as enviado
               ) existe_treino_enviado on true
               left join lateral (
                 select max(ts.finished_at) as finished_at
                 from training_sessions ts
                 where ts.student_id = s.id and ts.status = 'completed'
               ) ultima_sessao on true
               left join lateral (
                 select max(c.checkin_date) as checkin_date from checkins c where c.student_id = s.id
               ) ultimo_checkin on true
               where s.professional_id = ?
             ),
             classificado as (
               select
                 id, name, dias_desde_cadastro, sessoes_concluidas, created_at,
                 case when ultima_sessao_em is null then null
                      else extract(day from now() - ultima_sessao_em)::int end as dias_sem_treinar,
                 case when ultimo_checkin_em is null then null
                      else (current_date - ultimo_checkin_em)::int end as dias_sem_checkin,
                 case
                   when created_at > now() - interval '{$diasAlunoNovo} days' and sessoes_concluidas < {$treinosMinimosAlunoNovo}
                     then 'novo_sem_treinos'
                   when tem_treino_enviado and (ultima_sessao_em is null or ultima_sessao_em < now() - interval '{$diasSemTreinar} days')
                     then 'sem_treinar'
                   when (ultimo_checkin_em is null and created_at < now() - interval '{$diasSemCheckin} days')
                     or (ultimo_checkin_em is not null and ultimo_checkin_em < current_date - interval '{$diasSemCheckin} days')
                     then 'sem_checkin'
                   else null
                 end as motivo_codigo
               from base
             )
             select id, name, dias_desde_cadastro, sessoes_concluidas, dias_sem_treinar, dias_sem_checkin, motivo_codigo
             from classificado
             where motivo_codigo is not null
             order by
               case motivo_codigo when 'novo_sem_treinos' then 0 when 'sem_treinar' then 1 else 2 end,
               created_at desc
            SQL,
            [$professionalId]
        );

        return response()->json([
            'financeiro' => ['status' => 'em_breve'],
            'kpis' => [
                'total_alunos' => (int) $kpis->total_alunos,
                'novos_no_mes' => (int) $kpis->novos_no_mes,
                'inativos' => (int) $kpis->inativos,
                'retencao_pct' => $retencaoPct,
            ],
            'alunos_em_risco' => array_map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'prioridade' => $r->motivo_codigo === 'novo_sem_treinos' ? 'alta' : 'media',
                'motivo' => self::formatarMotivo($r),
            ], $risco),
        ]);
    }
}
