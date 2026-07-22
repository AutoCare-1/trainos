import { Router, Response } from 'express'
import { pool } from '../db/pool'
import { asyncHandler } from '../middleware/asyncHandler'
import { AuthedRequest, requireAuth } from '../middleware/auth'
import { Workout } from '../types'

const router = Router()
router.use(requireAuth)

interface ItemTreino {
  exercise_id: string
  sets: number
  reps: string
  load_kg?: number
  rest_seconds?: number
  notes?: string
}

// POST / — cria um treino (rascunho) com seus exercícios
router.post('/', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { student_id, name, items } = req.body as { student_id?: string; name?: string; items?: ItemTreino[] }
  if (!student_id || !name?.trim() || !items?.length) {
    res.status(400).json({ error: 'student_id, name e items (não vazio) são obrigatórios' })
    return
  }

  const { rows: studentRows } = await pool.query(
    'select id from students where id = $1 and professional_id = $2',
    [student_id, req.professionalId]
  )
  if (studentRows.length === 0) {
    res.status(404).json({ error: 'Aluno não encontrado' })
    return
  }

  const client = await pool.connect()
  try {
    await client.query('begin')

    const { rows: workoutRows } = await client.query<Workout>(
      `insert into workouts (professional_id, student_id, name) values ($1, $2, $3) returning *`,
      [req.professionalId, student_id, name.trim()]
    )
    const workout = workoutRows[0]

    let orderIndex = 0
    for (const item of items) {
      await client.query(
        `insert into workout_exercises (workout_id, exercise_id, order_index, sets, reps, load_kg, rest_seconds, notes)
         values ($1, $2, $3, $4, $5, $6, $7, $8)`,
        [workout.id, item.exercise_id, orderIndex++, item.sets, item.reps, item.load_kg ?? null, item.rest_seconds ?? null, item.notes ?? null]
      )
    }

    await client.query('commit')
    res.status(201).json({ workout })
  } catch (err) {
    await client.query('rollback')
    throw err
  } finally {
    client.release()
  }
}))

// GET /:id — detalhe do treino com exercícios
router.get('/:id', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rows: workoutRows } = await pool.query<Workout>(
    'select * from workouts where id = $1 and professional_id = $2',
    [req.params.id, req.professionalId]
  )
  const workout = workoutRows[0]
  if (!workout) {
    res.status(404).json({ error: 'Treino não encontrado' })
    return
  }

  const { rows: exercises } = await pool.query(
    `select we.*, e.name as exercise_name, e.muscle_group, e.instructions,
            coalesce(emo.video_url, e.video_url) as video_url, e.image_url, e.image_credit
     from workout_exercises we
     join exercises e on e.id = we.exercise_id
     left join exercise_media_overrides emo on emo.exercise_id = e.id and emo.professional_id = $2
     where we.workout_id = $1
     order by we.order_index`,
    [workout.id, req.professionalId]
  )

  res.json({ workout, exercises })
}))

// POST /:id/enviar — publica o treino para o aluno
router.post('/:id/enviar', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rows } = await pool.query<Workout>(
    `update workouts set status = 'sent', sent_at = now(), updated_at = now()
     where id = $1 and professional_id = $2 returning *`,
    [req.params.id, req.professionalId]
  )
  if (rows.length === 0) {
    res.status(404).json({ error: 'Treino não encontrado' })
    return
  }
  res.json({ workout: rows[0] })
}))

export default router
