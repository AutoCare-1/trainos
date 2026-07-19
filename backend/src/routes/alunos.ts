import { Router, Response } from 'express'
import { nanoid } from 'nanoid'
import { pool } from '../db/pool'
import { AuthedRequest, requireAuth } from '../middleware/auth'
import { Student } from '../types'

const router = Router()
router.use(requireAuth)

// GET / — lista alunos do profissional autenticado, com último treino e status
router.get('/', async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rows } = await pool.query(
    `select s.*,
            (select w.name from workouts w where w.student_id = s.id order by w.created_at desc limit 1) as ultimo_treino,
            (select count(*) from training_sessions ts join workouts w on w.id = ts.workout_id where w.student_id = s.id and ts.status = 'completed') as sessoes_concluidas
     from students s
     where s.professional_id = $1
     order by s.created_at desc`,
    [req.professionalId]
  )
  res.json({ students: rows })
})

// POST / — cadastra aluno e gera link de convite (token)
router.post('/', async (req: AuthedRequest, res: Response): Promise<void> => {
  const { name, email, phone, objective } = req.body as { name?: string; email?: string; phone?: string; objective?: string }
  if (!name?.trim()) {
    res.status(400).json({ error: 'name é obrigatório' })
    return
  }

  const inviteToken = nanoid(14)
  const { rows } = await pool.query<Student>(
    `insert into students (professional_id, name, email, phone, objective, invite_token)
     values ($1, $2, $3, $4, $5, $6) returning *`,
    [req.professionalId, name.trim(), email?.trim() || null, phone?.trim() || null, objective?.trim() || null, inviteToken]
  )
  res.status(201).json({ student: rows[0] })
})

// GET /:id — perfil do aluno + treinos
router.get('/:id', async (req: AuthedRequest, res: Response): Promise<void> => {
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

  res.json({ student, workouts })
})

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
router.get('/:id/mensagens', async (req: AuthedRequest, res: Response): Promise<void> => {
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
})

// POST /:id/mensagens — profissional responde manualmente
router.post('/:id/mensagens', async (req: AuthedRequest, res: Response): Promise<void> => {
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
})

// PATCH /:id/autopilot — liga/desliga a resposta automática da IA
router.patch('/:id/autopilot', async (req: AuthedRequest, res: Response): Promise<void> => {
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
})

export default router
