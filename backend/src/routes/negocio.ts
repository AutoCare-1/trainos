import { Router, Response } from 'express'
import { pool } from '../db/pool'
import { asyncHandler } from '../middleware/asyncHandler'
import { AuthedRequest, requireAuth } from '../middleware/auth'

const router = Router()
router.use(requireAuth)

const DIAS_SEM_TREINAR = 7
const DIAS_SEM_CHECKIN = 7
const DIAS_ALUNO_NOVO = 14
const TREINOS_MINIMOS_ALUNO_NOVO = 3

interface KpisRow {
  total_alunos: string
  novos_no_mes: string
  inativos: string
}

interface RetencaoRow {
  denominador: string
  numerador: string
}

interface RiscoRow {
  id: string
  name: string
  motivo_codigo: 'novo_sem_treinos' | 'sem_treinar' | 'sem_checkin'
  dias_desde_cadastro: number
  sessoes_concluidas: number
  dias_sem_treinar: number | null
  dias_sem_checkin: number | null
}

function formatarMotivo(r: RiscoRow): string {
  if (r.motivo_codigo === 'novo_sem_treinos') {
    return `Cadastrado há ${r.dias_desde_cadastro}d, ainda com só ${r.sessoes_concluidas} treino${r.sessoes_concluidas === 1 ? '' : 's'} concluído${r.sessoes_concluidas === 1 ? '' : 's'}`
  }
  if (r.motivo_codigo === 'sem_treinar') {
    return r.dias_sem_treinar === null ? 'Nunca completou um treino' : `Sem treinar há ${r.dias_sem_treinar}d`
  }
  return r.dias_sem_checkin === null ? 'Nunca fez um check-in' : `Sem check-in há ${r.dias_sem_checkin}d`
}

// GET / — visão geral do negócio do personal: KPIs de base de alunos + quem está em risco de abandono
router.get('/', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const professionalId = req.professionalId

  const { rows: kpisRows } = await pool.query<KpisRow>(
    `select
       count(*) as total_alunos,
       count(*) filter (where s.created_at >= date_trunc('month', now())) as novos_no_mes,
       count(*) filter (
         where existe_treino_enviado.enviado
           and (ultima_sessao.finished_at is null or ultima_sessao.finished_at < now() - interval '${DIAS_SEM_TREINAR} days')
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
     where s.professional_id = $1`,
    [professionalId]
  )
  const kpis = kpisRows[0]!

  const { rows: retencaoRows } = await pool.query<RetencaoRow>(
    `with antigos as (
       select id from students
       where professional_id = $1 and created_at < date_trunc('month', now())
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
       ) as numerador`,
    [professionalId]
  )
  const retencao = retencaoRows[0]!
  const denominador = Number(retencao.denominador)
  const retencaoPct = denominador > 0 ? Math.round((Number(retencao.numerador) / denominador) * 100) : null

  const { rows: risco } = await pool.query<RiscoRow>(
    `with base as (
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
       where s.professional_id = $1
     ),
     classificado as (
       select
         id, name, dias_desde_cadastro, sessoes_concluidas, created_at,
         case when ultima_sessao_em is null then null
              else extract(day from now() - ultima_sessao_em)::int end as dias_sem_treinar,
         case when ultimo_checkin_em is null then null
              else (current_date - ultimo_checkin_em)::int end as dias_sem_checkin,
         case
           when created_at > now() - interval '${DIAS_ALUNO_NOVO} days' and sessoes_concluidas < ${TREINOS_MINIMOS_ALUNO_NOVO}
             then 'novo_sem_treinos'
           when tem_treino_enviado and (ultima_sessao_em is null or ultima_sessao_em < now() - interval '${DIAS_SEM_TREINAR} days')
             then 'sem_treinar'
           when (ultimo_checkin_em is null and created_at < now() - interval '${DIAS_SEM_CHECKIN} days')
             or (ultimo_checkin_em is not null and ultimo_checkin_em < current_date - interval '${DIAS_SEM_CHECKIN} days')
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
       created_at desc`,
    [professionalId]
  )

  res.json({
    financeiro: { status: 'em_breve' },
    kpis: {
      total_alunos: Number(kpis.total_alunos),
      novos_no_mes: Number(kpis.novos_no_mes),
      inativos: Number(kpis.inativos),
      retencao_pct: retencaoPct,
    },
    alunos_em_risco: risco.map((r) => ({
      id: r.id,
      name: r.name,
      prioridade: r.motivo_codigo === 'novo_sem_treinos' ? 'alta' : 'media',
      motivo: formatarMotivo(r),
    })),
  })
}))

export default router
