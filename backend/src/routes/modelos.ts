import { Router, Response } from 'express'
import { pool } from '../db/pool'
import { asyncHandler } from '../middleware/asyncHandler'
import { AuthedRequest, requireAuth } from '../middleware/auth'
import { WorkoutTemplate } from '../types'

const router = Router()
router.use(requireAuth)

interface ItemModelo {
  exercise_id: string
  sets: number
  reps: string
  load_kg?: number
  rest_seconds?: number
  notes?: string
  structure_type?: string
  group_label?: string
}

// GET / — lista modelos do profissional, com quantidade de exercícios
router.get('/', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rows } = await pool.query(
    `select t.*,
            (select count(*) from workout_template_exercises wte where wte.template_id = t.id) as total_exercicios
     from workout_templates t
     where t.professional_id = $1
     order by t.created_at desc`,
    [req.professionalId]
  )
  res.json({ templates: rows })
}))

// POST / — cria um modelo de treino
router.post('/', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { name, items } = req.body as { name?: string; items?: ItemModelo[] }
  if (!name?.trim() || !items?.length) {
    res.status(400).json({ error: 'name e items (não vazio) são obrigatórios' })
    return
  }

  const client = await pool.connect()
  try {
    await client.query('begin')

    const { rows: templateRows } = await client.query<WorkoutTemplate>(
      `insert into workout_templates (professional_id, name) values ($1, $2) returning *`,
      [req.professionalId, name.trim()]
    )
    const template = templateRows[0]

    let orderIndex = 0
    for (const item of items) {
      await client.query(
        `insert into workout_template_exercises
           (template_id, exercise_id, order_index, sets, reps, load_kg, rest_seconds, notes, structure_type, group_label)
         values ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)`,
        [
          template.id,
          item.exercise_id,
          orderIndex++,
          item.sets,
          item.reps,
          item.load_kg ?? null,
          item.rest_seconds ?? null,
          item.notes ?? null,
          item.structure_type?.trim() || 'tradicional',
          item.group_label?.trim() || null,
        ]
      )
    }

    await client.query('commit')
    res.status(201).json({ template })
  } catch (err) {
    await client.query('rollback')
    throw err
  } finally {
    client.release()
  }
}))

// GET /:id — detalhe do modelo com exercícios
router.get('/:id', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rows: templateRows } = await pool.query<WorkoutTemplate>(
    'select * from workout_templates where id = $1 and professional_id = $2',
    [req.params.id, req.professionalId]
  )
  const template = templateRows[0]
  if (!template) {
    res.status(404).json({ error: 'Modelo não encontrado' })
    return
  }

  const { rows: exercises } = await pool.query(
    `select wte.*, e.name as exercise_name, e.muscle_group, e.image_url, e.image_credit
     from workout_template_exercises wte
     join exercises e on e.id = wte.exercise_id
     where wte.template_id = $1
     order by wte.order_index`,
    [template.id]
  )

  res.json({ template, exercises })
}))

// DELETE /:id — remove um modelo
router.delete('/:id', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rows } = await pool.query(
    'delete from workout_templates where id = $1 and professional_id = $2 returning id',
    [req.params.id, req.professionalId]
  )
  if (rows.length === 0) {
    res.status(404).json({ error: 'Modelo não encontrado' })
    return
  }
  res.status(204).send()
}))

export default router
