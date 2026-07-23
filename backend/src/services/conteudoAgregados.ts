import { pool } from '../db/pool'

/**
 * Monta um resumo agregado e 100% anônimo da base de alunos do personal —
 * só contagens/padrões, nunca nome, foto ou qualquer dado que identifique um
 * aluno específico. Vira o "conteúdo real" que a IA funde com a tendência de
 * formato pra sugerir ideias de post/story/reels.
 */
export async function montarResumoAgregadoAlunos(professionalId: string): Promise<string> {
  const { rows: totalRows } = await pool.query<{ total: string }>(
    'select count(*) as total from students where professional_id = $1',
    [professionalId]
  )
  const totalAlunos = Number(totalRows[0]?.total ?? 0)

  // Conta quantos registros de carga, nos últimos 7 dias, superaram o máximo
  // anterior do aluno naquele exercício — a mesma regra de "recorde pessoal"
  // já usada no portal do aluno (maxAnterior = 0 quando é o primeiro registro).
  const { rows: prRows } = await pool.query<{ total_prs: string }>(
    `with entradas as (
       select se.load_kg_done, se.created_at, ts.student_id, we.exercise_id
       from session_entries se
       join training_sessions ts on ts.id = se.training_session_id
       join workout_exercises we on we.id = se.workout_exercise_id
       join students s on s.id = ts.student_id
       where s.professional_id = $1 and se.load_kg_done is not null
     ),
     com_max_anterior as (
       select *,
         max(load_kg_done) over (
           partition by student_id, exercise_id
           order by created_at
           rows between unbounded preceding and 1 preceding
         ) as max_anterior
       from entradas
     )
     select count(*) as total_prs
     from com_max_anterior
     where created_at >= now() - interval '7 days'
       and load_kg_done > coalesce(max_anterior, 0)`,
    [professionalId]
  )
  const totalPrs = Number(prRows[0]?.total_prs ?? 0)

  // Consistência de check-in: quantos alunos fizeram check-in na última semana,
  // e quantos desses mantiveram pelo menos 3 dos 7 dias (padrão de constância).
  const { rows: checkinRows } = await pool.query<{ ativos: string; consistentes: string }>(
    `select count(*) as ativos, count(*) filter (where dias_semana >= 3) as consistentes
     from (
       select s.id, count(*) as dias_semana
       from students s
       join checkins c on c.student_id = s.id
       where s.professional_id = $1 and c.checkin_date >= current_date - interval '6 days'
       group by s.id
     ) por_aluno`,
    [professionalId]
  )
  const alunosComCheckin = Number(checkinRows[0]?.ativos ?? 0)
  const alunosConsistentes = Number(checkinRows[0]?.consistentes ?? 0)

  // Uso da aba de evolução física (frequência da feature, nunca o conteúdo das fotos).
  const { rows: evolucaoRows } = await pool.query<{ total: string }>(
    `select count(distinct bp.student_id) as total
     from body_photos bp
     join students s on s.id = bp.student_id
     where s.professional_id = $1 and bp.taken_at >= date_trunc('month', current_date)`,
    [professionalId]
  )
  const alunosComEvolucao = Number(evolucaoRows[0]?.total ?? 0)

  return `Dados agregados e 100% anônimos da base de alunos deste personal (nenhum nome, foto ou dado identificável — só padrões e contagens):
- ${totalAlunos} aluno${totalAlunos === 1 ? '' : 's'} cadastrado${totalAlunos === 1 ? '' : 's'} no total.
- ${totalPrs} recorde${totalPrs === 1 ? '' : 's'} pessoal${totalPrs === 1 ? '' : 'is'} (PR) batido${totalPrs === 1 ? '' : 's'} pelos alunos nos últimos 7 dias.
- ${alunosComCheckin} aluno${alunosComCheckin === 1 ? '' : 's'} fez${alunosComCheckin === 1 ? '' : 'eram'} check-in de treino na última semana, sendo ${alunosConsistentes} deles com pelo menos 3 dos 7 dias marcados (consistência de frequência).
- ${alunosComEvolucao} aluno${alunosComEvolucao === 1 ? '' : 's'} registrou${alunosComEvolucao === 1 ? '' : 'aram'} foto de evolução física esse mês.`
}
