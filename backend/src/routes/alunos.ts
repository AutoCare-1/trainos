import { Router, Response } from 'express'
import path from 'path'
import { nanoid } from 'nanoid'
import { pool } from '../db/pool'
import { asyncHandler } from '../middleware/asyncHandler'
import { AuthedRequest, requireAuth } from '../middleware/auth'
import { calcularBadges, calcularStreak } from '../services/gamification'
import { criarUploader, PRIVATE_UPLOADS_ROOT } from '../middleware/upload'
import { BodyPhoto, Student } from '../types'

const router = Router()
router.use(requireAuth)

const uploadFoto = criarUploader('student-photos', 'image/', 10 * 1024 * 1024)

// Conta, por aluno, quantos exercícios não tiveram a carga máxima aumentada
// entre as duas últimas sessões concluídas em que ele registrou peso.
async function contarEstagnacaoPorAluno(professionalId: string): Promise<Record<string, number>> {
  const { rows } = await pool.query<{ student_id: string; estagnados: string }>(
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
     select student_id, count(*) filter (where ultima <= anterior) as estagnados
     from comparacao
     group by student_id`,
    [professionalId]
  )
  return Object.fromEntries(rows.map((r) => [r.student_id, Number(r.estagnados)]))
}

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

  const estagnacao = await contarEstagnacaoPorAluno(req.professionalId as string)
  const students = rows.map((s) => ({ ...s, exercicios_sem_progresso: estagnacao[s.id] ?? 0 }))

  res.json({ students })
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

  const { rows: sessoesConcluidas } = await pool.query<{ finished_at: Date }>(
    `select ts.finished_at from training_sessions ts
     join workouts w on w.id = ts.workout_id
     where w.student_id = $1 and ts.status = 'completed'`,
    [student.id]
  )
  const datas = sessoesConcluidas.map((s) => new Date(s.finished_at))
  const streak = calcularStreak(datas)
  const gamificacao = { total_sessoes: datas.length, streak, badges: calcularBadges(datas.length, streak) }

  const { rows: alertasEstagnacao } = await pool.query(
    `with cargas as (
       select we.exercise_id, ts.id as session_id, ts.finished_at,
              max(se.load_kg_done) as carga_max
       from session_entries se
       join training_sessions ts on ts.id = se.training_session_id
       join workout_exercises we on we.id = se.workout_exercise_id
       where ts.student_id = $1 and ts.status = 'completed' and se.load_kg_done is not null
       group by we.exercise_id, ts.id, ts.finished_at
     ),
     ranked as (
       select *, row_number() over (partition by exercise_id order by finished_at desc) as rn
       from cargas
     ),
     comparacao as (
       select exercise_id,
              max(case when rn = 1 then carga_max end) as ultima,
              max(case when rn = 2 then carga_max end) as anterior
       from ranked
       where rn <= 2
       group by exercise_id
       having max(case when rn = 2 then carga_max end) is not null
     )
     select c.exercise_id, e.name as exercise_name, c.ultima, c.anterior
     from comparacao c
     join exercises e on e.id = c.exercise_id
     where c.ultima <= c.anterior
     order by e.name`,
    [student.id]
  )

  res.json({ student, workouts, measurements, gamificacao, alertasEstagnacao })
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

  const { weight_kg, waist_cm, hip_cm, body_fat_pct, recorded_at, notes } = req.body as {
    weight_kg?: number
    waist_cm?: number
    hip_cm?: number
    body_fat_pct?: number
    recorded_at?: string
    notes?: string
  }
  if (!weight_kg) {
    res.status(400).json({ error: 'weight_kg é obrigatório' })
    return
  }

  const { rows } = await pool.query(
    `insert into body_measurements (student_id, recorded_at, weight_kg, waist_cm, hip_cm, body_fat_pct, notes)
     values ($1, coalesce($2, current_date), $3, $4, $5, $6, $7) returning *`,
    [
      req.params.id,
      recorded_at || null,
      weight_kg,
      waist_cm || null,
      hip_cm || null,
      body_fat_pct || null,
      notes?.trim() || null,
    ]
  )
  res.status(201).json({ measurement: rows[0] })
}))

// GET /:id/body-photos — profissional vê a galeria de evolução física do aluno (só leitura)
router.get('/:id/body-photos', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rows: studentRows } = await pool.query(
    'select id from students where id = $1 and professional_id = $2',
    [req.params.id, req.professionalId]
  )
  if (studentRows.length === 0) {
    res.status(404).json({ error: 'Aluno não encontrado' })
    return
  }

  const { rows } = await pool.query<BodyPhoto>(
    `select id, student_id, taken_at, ai_feedback, compared_to_photo_id, created_at
     from body_photos where student_id = $1 order by taken_at desc`,
    [req.params.id]
  )
  res.json({ photos: rows })
}))

// GET /:id/body-photos/:photoId/imagem — serve o arquivo (autenticado por JWT + dono do aluno)
router.get(
  '/:id/body-photos/:photoId/imagem',
  asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
    const { rows: studentRows } = await pool.query(
      'select id from students where id = $1 and professional_id = $2',
      [req.params.id, req.professionalId]
    )
    if (studentRows.length === 0) {
      res.status(404).json({ error: 'Aluno não encontrado' })
      return
    }

    const { rows } = await pool.query<{ file_path: string }>(
      'select file_path from body_photos where id = $1 and student_id = $2',
      [req.params.photoId, req.params.id]
    )
    if (!rows[0]) {
      res.status(404).json({ error: 'Foto não encontrada' })
      return
    }

    res.sendFile(path.join(PRIVATE_UPLOADS_ROOT, rows[0].file_path))
  })
)

// ─────────────────────────────────────────────
// Avaliação física (anamnese/PAR-Q)
// ─────────────────────────────────────────────

// PATCH /:id/avaliacao — atualiza anamnese de saúde (PAR-Q resumido) do aluno
router.patch('/:id/avaliacao', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rows: studentRows } = await pool.query(
    'select id from students where id = $1 and professional_id = $2',
    [req.params.id, req.professionalId]
  )
  if (studentRows.length === 0) {
    res.status(404).json({ error: 'Aluno não encontrado' })
    return
  }

  const { par_q_answers, health_notes } = req.body as {
    par_q_answers?: { cardiaco: boolean; tontura: boolean; articular: boolean; pressao_medicacao: boolean }
    health_notes?: string
  }

  const { rows } = await pool.query<Student>(
    `update students set par_q_answers = $1, health_notes = $2 where id = $3 returning *`,
    [par_q_answers ? JSON.stringify(par_q_answers) : null, health_notes?.trim() || null, req.params.id]
  )
  res.json({ student: rows[0] })
}))

// POST /:id/foto — profissional envia a foto do aluno (fallback, caso o aluno não tenha feito isso)
router.post('/:id/foto', uploadFoto.single('foto'), asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rows: studentRows } = await pool.query(
    'select id from students where id = $1 and professional_id = $2',
    [req.params.id, req.professionalId]
  )
  if (studentRows.length === 0) {
    res.status(404).json({ error: 'Aluno não encontrado' })
    return
  }
  if (!req.file) {
    res.status(400).json({ error: 'Arquivo de imagem é obrigatório' })
    return
  }

  const photoUrl = `/uploads/student-photos/${req.file.filename}`
  const { rows } = await pool.query<Student>(
    'update students set photo_url = $1 where id = $2 returning *',
    [photoUrl, req.params.id]
  )
  res.json({ student: rows[0] })
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
