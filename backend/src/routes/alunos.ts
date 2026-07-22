import { Router, Response } from 'express'
import { nanoid } from 'nanoid'
import { pool } from '../db/pool'
import { asyncHandler } from '../middleware/asyncHandler'
import { AuthedRequest, requireAuth } from '../middleware/auth'
import { Student } from '../types'

const router = Router()
router.use(requireAuth)

// GET / — lista alunos do profissional autenticado, com último treino e status
router.get('/', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rows } = await pool.query(
    `select s.*,
            (select w.name from workouts w where w.student_id = s.id order by w.created_at desc limit 1) as ultimo_treino,
            (select count(*) from training_sessions ts join workouts w on w.id = ts.workout_id where w.student_id = s.id and ts.status = 'completed') as sessoes_concluidas,
            (select max(ts.finished_at) from training_sessions ts join workouts w on w.id = ts.workout_id where w.student_id = s.id and ts.status = 'completed') as ultima_sessao_em,
            exists(select 1 from workouts w where w.student_id = s.id and w.status = 'sent') as tem_treino_enviado
     from students s
     where s.professional_id = $1
     order by s.created_at desc`,
    [req.professionalId]
  )
  res.json({ students: rows })
}))

// POST / — cadastra aluno e gera link de convite (token)
router.post('/', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { name, email, phone, objective, weight_kg, height_cm } = req.body as {
    name?: string
    email?: string
    phone?: string
    objective?: string
    weight_kg?: number
    height_cm?: number
  }
  if (!name?.trim()) {
    res.status(400).json({ error: 'name é obrigatório' })
    return
  }

  const inviteToken = nanoid(14)
  const { rows } = await pool.query<Student>(
    `insert into students (professional_id, name, email, phone, objective, weight_kg, height_cm, invite_token)
     values ($1, $2, $3, $4, $5, $6, $7, $8) returning *`,
    [
      req.professionalId,
      name.trim(),
      email?.trim() || null,
      phone?.trim() || null,
      objective?.trim() || null,
      weight_kg || null,
      height_cm || null,
      inviteToken,
    ]
  )
  res.status(201).json({ student: rows[0] })
}))

// GET /:id — perfil do aluno + treinos
router.get('/:id', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rows: studentRows } = await pool.query<Student>(
    'select * from students where id = $1 and professional_id = $2',
    [req.params.id, req.professionalId]
  )
  const student = studentRows[0]
  if (!student) {
    res.status(404).json({ error: 'Aluno não encontrado' })
    return
  }

  const { rows: workouts } = await pool.query(
    'select * from workouts where student_id = $1 order by created_at desc',
    [student.id]
  )

  const { rows: measurements } = await pool.query(
    'select * from body_measurements where student_id = $1 order by recorded_at asc',
    [student.id]
  )

  res.json({ student, workouts, measurements })
}))

// ─────────────────────────────────────────────
// Evolução física
// ─────────────────────────────────────────────

// POST /:id/medicoes — registra uma nova medição (peso) do aluno
router.post('/:id/medicoes', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rows: studentRows } = await pool.query(
    'select id from students where id = $1 and professional_id = $2',
    [req.params.id, req.professionalId]
  )
  if (studentRows.length === 0) {
    res.status(404).json({ error: 'Aluno não encontrado' })
    return
  }

  const { weight_kg, recorded_at, notes } = req.body as { weight_kg?: number; recorded_at?: string; notes?: string }
  if (!weight_kg) {
    res.status(400).json({ error: 'weight_kg é obrigatório' })
    return
  }

  const { rows } = await pool.query(
    `insert into body_measurements (student_id, recorded_at, weight_kg, notes)
     values ($1, coalesce($2, current_date), $3, $4) returning *`,
    [req.params.id, recorded_at || null, weight_kg, notes?.trim() || null]
  )
  res.status(201).json({ measurement: rows[0] })
}))

// ─────────────────────────────────────────────
// Chat (lado do profissional)
// ─────────────────────────────────────────────

async function alunoDoProfissional(studentId: string, professionalId: string): Promise<Student | null> {
  const { rows } = await pool.query<Student>(
    'select * from students where id = $1 and professional_id = $2',
    [studentId, professionalId]
  )
  return rows[0] ?? null
}

// GET /:id/mensagens — histórico do chat
router.get('/:id/mensagens', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const student = await alunoDoProfissional(req.params.id as string, req.professionalId as string)
  if (!student) {
    res.status(404).json({ error: 'Aluno não encontrado' })
    return
  }
  const { rows } = await pool.query(
    'select * from messages where student_id = $1 order by created_at',
    [student.id]
  )
  res.json({ messages: rows, ai_autopilot: student.ai_autopilot })
}))

// POST /:id/mensagens — profissional responde manualmente
router.post('/:id/mensagens', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const student = await alunoDoProfissional(req.params.id as string, req.professionalId as string)
  if (!student) {
    res.status(404).json({ error: 'Aluno não encontrado' })
    return
  }
  const { content } = req.body as { content?: string }
  if (!content?.trim()) {
    res.status(400).json({ error: 'content é obrigatório' })
    return
  }
  const { rows } = await pool.query(
    `insert into messages (student_id, professional_id, sender, content)
     values ($1, $2, 'professional', $3) returning *`,
    [student.id, req.professionalId, content.trim()]
  )
  res.status(201).json({ message: rows[0] })
}))

// PATCH /:id/autopilot — liga/desliga a resposta automática da IA
router.patch('/:id/autopilot', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const student = await alunoDoProfissional(req.params.id as string, req.professionalId as string)
  if (!student) {
    res.status(404).json({ error: 'Aluno não encontrado' })
    return
  }
  const { enabled } = req.body as { enabled?: boolean }
  if (typeof enabled !== 'boolean') {
    res.status(400).json({ error: 'enabled (boolean) é obrigatório' })
    return
  }
  const { rows } = await pool.query<Student>(
    'update students set ai_autopilot = $1 where id = $2 returning *',
    [enabled, student.id]
  )
  res.json({ student: rows[0] })
}))

export default router
