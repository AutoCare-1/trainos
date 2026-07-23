import { pool } from '../db/pool'

// Todas as funções abaixo são as ÚNICAS formas da IA do Consultor acessar dado
// de aluno — cada uma recebe professionalId (vindo do JWT, nunca do input do
// usuário/IA) e filtra por ele em toda query. A IA nunca gera SQL nem acessa o
// banco diretamente.

interface AlunoEncontrado {
  id: string
  name: string
}

async function encontrarAluno(professionalId: string, nomeOuId: string): Promise<AlunoEncontrado | null> {
  const pareceUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(nomeOuId.trim())

  if (pareceUuid) {
    const { rows } = await pool.query<AlunoEncontrado>(
      'select id, name from students where id = $1 and professional_id = $2',
      [nomeOuId.trim(), professionalId]
    )
    return rows[0] ?? null
  }

  const { rows } = await pool.query<AlunoEncontrado>(
    'select id, name from students where professional_id = $1 and name ilike $2 order by name limit 1',
    [professionalId, `%${nomeOuId.trim()}%`]
  )
  return rows[0] ?? null
}

export async function buscarResumoAluno(professionalId: string, nomeOuId: string): Promise<object> {
  const aluno = await encontrarAluno(professionalId, nomeOuId)
  if (!aluno) {
    return { encontrado: false, mensagem: `Nenhum aluno chamado "${nomeOuId}" encontrado na base deste personal.` }
  }

  const { rows: semanaRows } = await pool.query<{ dias_com_checkin: string; total_dias: number }>(
    `select count(*) as dias_com_checkin, 7 as total_dias
     from checkins
     where student_id = $1
       and checkin_date >= date_trunc('week', current_date)
       and checkin_date < date_trunc('week', current_date) + interval '7 days'`,
    [aluno.id]
  )

  const { rows: prsRows } = await pool.query<{ exercise_name: string; load_kg_done: string; created_at: string }>(
    `with entradas as (
       select se.load_kg_done, se.created_at, we.exercise_id
       from session_entries se
       join training_sessions ts on ts.id = se.training_session_id
       join workout_exercises we on we.id = se.workout_exercise_id
       where ts.student_id = $1 and se.load_kg_done is not null
     ),
     com_max_anterior as (
       select *,
         max(load_kg_done) over (
           partition by exercise_id order by created_at rows between unbounded preceding and 1 preceding
         ) as max_anterior
       from entradas
     )
     select e.name as exercise_name, c.load_kg_done, c.created_at
     from com_max_anterior c
     join exercises e on e.id = c.exercise_id
     where c.created_at >= now() - interval '14 days'
       and c.load_kg_done > coalesce(c.max_anterior, 0)
     order by c.created_at desc`,
    [aluno.id]
  )

  const { rows: evolucaoRows } = await pool.query<{ ai_feedback: string | null; taken_at: string }>(
    'select ai_feedback, taken_at from body_photos where student_id = $1 order by taken_at desc limit 1',
    [aluno.id]
  )

  return {
    encontrado: true,
    nome: aluno.name,
    checkins_semana_atual: `${semanaRows[0]?.dias_com_checkin ?? 0} de ${semanaRows[0]?.total_dias ?? 7} dias`,
    prs_ultimos_14_dias: prsRows.map((r) => ({
      exercicio: r.exercise_name,
      carga_kg: r.load_kg_done,
      data: r.created_at,
    })),
    ultimo_comentario_evolucao_fisica: evolucaoRows[0]
      ? { comentario: evolucaoRows[0].ai_feedback, data: evolucaoRows[0].taken_at }
      : null,
  }
}

export async function listarAlunosSemCheckin(professionalId: string, dias: number): Promise<object> {
  const { rows } = await pool.query<{ name: string }>(
    `select s.name
     from students s
     where s.professional_id = $1
       and not exists (
         select 1 from checkins c
         where c.student_id = s.id and c.checkin_date >= current_date - ($2::int - 1)
       )
     order by s.name`,
    [professionalId, dias]
  )
  return { periodo_dias: dias, alunos_sem_checkin: rows.map((r) => r.name) }
}

export async function listarPrsRecentes(professionalId: string, dias: number): Promise<object> {
  const { rows } = await pool.query<{
    aluno: string
    exercicio: string
    carga_kg: string
    data: string
  }>(
    `with entradas as (
       select se.load_kg_done, se.created_at, we.exercise_id, ts.student_id
       from session_entries se
       join training_sessions ts on ts.id = se.training_session_id
       join workout_exercises we on we.id = se.workout_exercise_id
       join students s on s.id = ts.student_id
       where s.professional_id = $1 and se.load_kg_done is not null
     ),
     com_max_anterior as (
       select *,
         max(load_kg_done) over (
           partition by student_id, exercise_id order by created_at rows between unbounded preceding and 1 preceding
         ) as max_anterior
       from entradas
     )
     select s.name as aluno, e.name as exercicio, c.load_kg_done as carga_kg, c.created_at as data
     from com_max_anterior c
     join students s on s.id = c.student_id
     join exercises e on e.id = c.exercise_id
     where c.created_at >= now() - ($2::int * interval '1 day')
       and c.load_kg_done > coalesce(c.max_anterior, 0)
     order by c.created_at desc`,
    [professionalId, dias]
  )
  return { periodo_dias: dias, prs: rows }
}

export async function listarEstagnados(professionalId: string): Promise<object> {
  const { rows } = await pool.query<{
    aluno: string
    exercicio: string
    ultima_carga: string
    carga_anterior: string
  }>(
    `with cargas as (
       select ts.student_id, we.exercise_id, ts.id as session_id, ts.finished_at,
              max(se.load_kg_done) as carga_max
       from session_entries se
       join training_sessions ts on ts.id = se.training_session_id
       join workout_exercises we on we.id = se.workout_exercise_id
       join students s on s.id = ts.student_id
       where s.professional_id = $1 and ts.status = 'completed' and se.load_kg_done is not null
       group by ts.student_id, we.exercise_id, ts.id, ts.finished_at
     ),
     ranked as (
       select *, row_number() over (partition by student_id, exercise_id order by finished_at desc) as rn
       from cargas
     ),
     comparacao as (
       select student_id, exercise_id,
              max(case when rn = 1 then carga_max end) as ultima,
              max(case when rn = 2 then carga_max end) as anterior
       from ranked
       where rn <= 2
       group by student_id, exercise_id
       having max(case when rn = 2 then carga_max end) is not null
     )
     select s.name as aluno, e.name as exercicio, c.ultima as ultima_carga, c.anterior as carga_anterior
     from comparacao c
     join students s on s.id = c.student_id
     join exercises e on e.id = c.exercise_id
     where c.ultima <= c.anterior
     order by s.name, e.name`,
    [professionalId]
  )
  return { alunos_estagnados: rows }
}

export async function listarAlunosMaisConsistentes(professionalId: string): Promise<object> {
  const { rows } = await pool.query<{ aluno: string; dias_semana_atual: string; dias_mes_atual: string }>(
    `select s.name as aluno,
            count(c.id) filter (
              where c.checkin_date >= date_trunc('week', current_date)
              and c.checkin_date < date_trunc('week', current_date) + interval '7 days'
            ) as dias_semana_atual,
            count(c.id) filter (
              where c.checkin_date >= date_trunc('month', current_date)
              and c.checkin_date < date_trunc('month', current_date) + interval '1 month'
            ) as dias_mes_atual
     from students s
     left join checkins c on c.student_id = s.id
     where s.professional_id = $1
     group by s.id, s.name
     having count(c.id) filter (
       where c.checkin_date >= date_trunc('month', current_date)
       and c.checkin_date < date_trunc('month', current_date) + interval '1 month'
     ) > 0
     order by dias_mes_atual desc, dias_semana_atual desc, s.name`,
    [professionalId]
  )
  return {
    ranking: rows.map((r) => ({
      aluno: r.aluno,
      dias_com_checkin_semana_atual: Number(r.dias_semana_atual),
      dias_com_checkin_mes_atual: Number(r.dias_mes_atual),
    })),
  }
}
