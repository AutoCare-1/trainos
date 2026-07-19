import { Router, Request, Response } from 'express'
import { pool } from '../db/pool'
import { Student, Workout } from '../types'

const router = Router()

async function buscarAlunoPorToken(token: string): Promise<Student | null> {
  const { rows } = await pool.query<Student>('select * from students where invite_token = $1', [token])
  return rows[0] ?? null
}

// GET /:token — dados do aluno + treino mais recente enviado
router.get('/:token', async (req: Request, res: Response): Promise<void> => {
  const student = await buscarAlunoPorToken(req.params.token as string)
  if (!student) {
    res.status(404).json({ error: 'Link inválido' })
    return
  }

  const { rows: workoutRows } = await pool.query<Workout>(
    `select * from workouts where student_id = $1 and status = 'sent' order by sent_at desc limit 1`,
    [student.id]
  )
  const workout = workoutRows[0] ?? null

  let exercises: unknown[] = []
  let activeSession: { id: string } | null = null

  if (workout) {
    const { rows } = await pool.query(
      `select we.*, e.name as exercise_name, e.muscle_group, e.instructions, e.video_url
       from workout_exercises we
       join exercises e on e.id = we.exercise_id
       where we.workout_id = $1
       order by we.order_index`,
      [workout.id]
    )
    exercises = rows

    const { rows: sessionRows } = await pool.query(
      `select id from training_sessions where workout_id = $1 and student_id = $2 and status = 'in_progress' limit 1`,
      [workout.id, student.id]
    )
    activeSession = sessionRows[0] ?? null
  }

  res.json({
    student: { id: student.id, name: student.name, objective: student.objective },
    workout,
    exercises,
    activeSessionId: activeSession?.id ?? null,
  })
})

// POST /:token/sessoes — inicia (ou retoma) uma sessão de execução do treino
router.post('/:token/sessoes', async (req: Request, res: Response): Promise<void> => {
  const student = await buscarAlunoPorToken(req.params.token as string)
  if (!student) {
    res.status(404).json({ error: 'Link inválido' })
    return
  }

  const { workout_id } = req.body as { workout_id?: string }
  if (!workout_id) {
    res.status(400).json({ error: 'workout_id é obrigatório' })
    return
  }

  const { rows: existente } = await pool.query(
    `select * from training_sessions where workout_id = $1 and student_id = $2 and status = 'in_progress' limit 1`,
    [workout_id, student.id]
  )
  if (existente[0]) {
    res.json({ session: existente[0] })
    return
  }

  const { rows } = await pool.query(
    `insert into training_sessions (workout_id, student_id) values ($1, $2) returning *`,
    [workout_id, student.id]
  )
  res.status(201).json({ session: rows[0] })
})

// POST /:token/sessoes/:sessionId/registros — registra uma série executada
router.post('/:token/sessoes/:sessionId/registros', async (req: Request, res: Response): Promise<void> => {
  const student = await buscarAlunoPorToken(req.params.token as string)
  if (!student) {
    res.status(404).json({ error: 'Link inválido' })
    return
  }

  const { workout_exercise_id, set_number, reps_done, load_kg_done, notes } = req.body as {
    workout_exercise_id?: string
    set_number?: number
    reps_done?: number
    load_kg_done?: number
    notes?: string
  }
  if (!workout_exercise_id || !set_number) {
    res.status(400).json({ error: 'workout_exercise_id e set_number são obrigatórios' })
    return
  }

  const { rows: sessionRows } = await pool.query(
    'select id from training_sessions where id = $1 and student_id = $2',
    [req.params.sessionId, student.id]
  )
  if (sessionRows.length === 0) {
    res.status(404).json({ error: 'Sessão não encontrada' })
    return
  }

  const { rows } = await pool.query(
    `insert into session_entries (training_session_id, workout_exercise_id, set_number, reps_done, load_kg_done, notes)
     values ($1, $2, $3, $4, $5, $6) returning *`,
    [req.params.sessionId, workout_exercise_id, set_number, reps_done ?? null, load_kg_done ?? null, notes ?? null]
  )
  res.status(201).json({ entry: rows[0] })
})

// POST /:token/sessoes/:sessionId/concluir — finaliza a sessão + feedback pós-treino
router.post('/:token/sessoes/:sessionId/concluir', async (req: Request, res: Response): Promise<void> => {
  const student = await buscarAlunoPorToken(req.params.token as string)
  if (!student) {
    res.status(404).json({ error: 'Link inválido' })
    return
  }

  const { effort_rpe, satisfaction, discomfort, comment } = req.body as {
    effort_rpe?: number
    satisfaction?: number
    discomfort?: string
    comment?: string
  }

  const { rows: sessionRows } = await pool.query(
    `update training_sessions set status = 'completed', finished_at = now()
     where id = $1 and student_id = $2 returning *`,
    [req.params.sessionId, student.id]
  )
  if (sessionRows.length === 0) {
    res.status(404).json({ error: 'Sessão não encontrada' })
    return
  }

  await pool.query(
    `insert into feedbacks (training_session_id, effort_rpe, satisfaction, discomfort, comment)
     values ($1, $2, $3, $4, $5)
     on conflict (training_session_id) do update
       set effort_rpe = excluded.effort_rpe, satisfaction = excluded.satisfaction,
           discomfort = excluded.discomfort, comment = excluded.comment`,
    [req.params.sessionId, effort_rpe ?? null, satisfaction ?? null, discomfort?.trim() || null, comment?.trim() || null]
  )

  res.json({ session: sessionRows[0] })
})

export default router
