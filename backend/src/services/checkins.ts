import { pool } from '../db/pool'

// Toda a matemática de data roda dentro do Postgres (date_trunc, generate_series)
// em vez de em JS, pra evitar bugs de fuso horário na hora de decidir "que dia é
// hoje" — o mesmo dia que already foi usado pra gravar o check-in (current_date
// do banco), sem depender do timezone do processo Node.

const LABELS_DIA = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']

export interface DiaSemana {
  date: string
  label: string
  checked: boolean
  comment: string | null
}

export interface ResumoSemana {
  inicio: string
  fim: string
  dias_com_checkin: number
  total_dias: number
  grid: DiaSemana[]
}

export interface ResumoMes {
  ano: number
  mes: number
  dias_com_checkin: number
  total_dias_mes: number
  dias_marcados: number[]
}

export interface ResumoAno {
  ano: number
  dias_com_checkin: number
}

export async function calcularResumoSemana(studentId: string, ref: string | null): Promise<ResumoSemana> {
  const { rows } = await pool.query<{ date: string; checked: boolean; comment: string | null }>(
    `select gs::date::text as date,
            c.id is not null as checked,
            c.comment as comment
     from generate_series(
       date_trunc('week', coalesce($2::date, current_date)),
       date_trunc('week', coalesce($2::date, current_date)) + interval '6 days',
       interval '1 day'
     ) as gs
     left join checkins c on c.student_id = $1 and c.checkin_date = gs::date
     order by gs`,
    [studentId, ref]
  )

  const grid: DiaSemana[] = rows.map((r, i) => ({
    date: r.date,
    label: LABELS_DIA[i],
    checked: r.checked,
    comment: r.comment,
  }))
  return {
    inicio: grid[0]?.date,
    fim: grid[6]?.date,
    dias_com_checkin: grid.filter((d) => d.checked).length,
    total_dias: 7,
    grid,
  }
}

export async function calcularResumoMes(studentId: string, ref: string | null): Promise<ResumoMes> {
  const { rows } = await pool.query<{
    ano: number
    mes: number
    total_dias_mes: number
    dias_com_checkin: string
    dias_marcados: number[]
  }>(
    `select
       extract(year from date_trunc('month', coalesce($2::date, current_date)))::int as ano,
       extract(month from date_trunc('month', coalesce($2::date, current_date)))::int as mes,
       extract(day from (
         date_trunc('month', coalesce($2::date, current_date)) + interval '1 month' - interval '1 day'
       ))::int as total_dias_mes,
       count(c.id) as dias_com_checkin,
       coalesce(array_agg(extract(day from c.checkin_date)::int order by c.checkin_date) filter (where c.id is not null), '{}') as dias_marcados
     from (select coalesce($2::date, current_date) as ref) base
     left join checkins c
       on c.student_id = $1
       and c.checkin_date >= date_trunc('month', base.ref)
       and c.checkin_date < date_trunc('month', base.ref) + interval '1 month'
     group by base.ref`,
    [studentId, ref]
  )

  const linha = rows[0]
  return {
    ano: linha.ano,
    mes: linha.mes,
    total_dias_mes: linha.total_dias_mes,
    dias_com_checkin: Number(linha.dias_com_checkin),
    dias_marcados: linha.dias_marcados,
  }
}

export async function calcularResumoAno(studentId: string, ref: string | null): Promise<ResumoAno> {
  const { rows } = await pool.query<{ ano: number; dias_com_checkin: string }>(
    `select
       extract(year from date_trunc('year', coalesce($2::date, current_date)))::int as ano,
       count(c.id) as dias_com_checkin
     from (select coalesce($2::date, current_date) as ref) base
     left join checkins c
       on c.student_id = $1
       and c.checkin_date >= date_trunc('year', base.ref)
       and c.checkin_date < date_trunc('year', base.ref) + interval '1 year'
     group by base.ref`,
    [studentId, ref]
  )

  const linha = rows[0]
  return { ano: linha.ano, dias_com_checkin: Number(linha.dias_com_checkin) }
}

export async function existeCheckinHoje(studentId: string): Promise<boolean> {
  const { rows } = await pool.query('select 1 from checkins where student_id = $1 and checkin_date = current_date', [
    studentId,
  ])
  return rows.length > 0
}
