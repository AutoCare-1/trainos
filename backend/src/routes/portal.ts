import { Router, Request, Response } from 'express'
import { pool } from '../db/pool'
import { asyncHandler } from '../middleware/asyncHandler'
import { ContextoAluno, responderComoPersonal } from '../services/chat'
import { calcularBadges, calcularStreak } from '../services/gamification'
import { criarUploader } from '../middleware/upload'
import { Message, Student, Workout } from '../types'

const router = Router()
const uploadFoto = criarUploader('student-photos', 'image/', 10 * 1024 * 1024)

async function buscarAlunoPorToken(token: string): Promise<Student | null> {
  const { rows } = await pool.query<Student>('select * from students where invite_token = $1', [token])
  return rows[0] ?? null
}

// GET /:token — dados do aluno + treino mais recente enviado
router.get('/:token', asyncHandler(async (req: Request, res: Response): Promise<void> => {
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
      `select we.*, e.name as exercise_name, e.muscle_group, e.instructions,
              coalesce(emo.video_url, e.video_url) as video_url, e.image_url, e.image_credit
       from workout_exercises we
       join exercises e on e.id = we.exercise_id
       left join exercise_media_overrides emo on emo.exercise_id = e.id and emo.professional_id = $2
       where we.workout_id = $1
       order by we.order_index`,
      [workout.id, student.professional_id]
    )
    exercises = rows

    const { rows: sessionRows } = await pool.query(
      `select id from training_sessions where workout_id = $1 and student_id = $2 and status = 'in_progress' limit 1`,
      [workout.id, student.id]
    )
    activeSession = sessionRows[0] ?? null
  }

  // Se o aluno já tinha uma sessão em andamento (ex: fechou o app no meio do treino
  // e reabriu o link depois), devolve quantas séries já foram registradas por
  // exercício — sem isso, o app reiniciaria a contagem do zero e duplicaria séries
  // já salvas ao registrar de novo.
  let registeredCounts: Record<string, number> = {}
  if (activeSession) {
    const { rows: countRows } = await pool.query<{ workout_exercise_id: string; total: string }>(
      `select workout_exercise_id, count(*) as total
       from session_entries
       where training_session_id = $1
       group by workout_exercise_id`,
      [activeSession.id]
    )
    registeredCounts = Object.fromEntries(countRows.map((r) => [r.workout_exercise_id, Number(r.total)]))
  }

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

  const { rows: desafioRows } = await pool.query(
    `select c.* from challenges c
     join challenge_participants cp on cp.challenge_id = c.id
     where cp.student_id = $1 and current_date between c.start_date and c.end_date
     order by c.start_date desc limit 1`,
    [student.id]
  )
  const desafioAtivo = desafioRows[0] ?? null

  let leaderboard: { student_id: string; name: string; photo_url: string | null; pontos: string }[] = []
  if (desafioAtivo) {
    const { rows } = await pool.query(
      `select s.id as student_id, s.name, s.photo_url,
              count(ts.id) filter (
                where ts.status = 'completed'
                and ts.finished_at::date between $2 and $3
              ) as pontos
       from challenge_participants cp
       join students s on s.id = cp.student_id
       left join workouts w on w.student_id = s.id
       left join training_sessions ts on ts.workout_id = w.id and ts.student_id = s.id
       where cp.challenge_id = $1
       group by s.id, s.name, s.photo_url
       order by pontos desc, s.name`,
      [desafioAtivo.id, desafioAtivo.start_date, desafioAtivo.end_date]
    )
    leaderboard = rows
  }

  res.json({
    student: { id: student.id, name: student.name, objective: student.objective, photo_url: student.photo_url },
    workout,
    exercises,
    activeSessionId: activeSession?.id ?? null,
    registeredCounts,
    measurements,
    gamificacao,
    desafio: desafioAtivo ? { ...desafioAtivo, leaderboard } : null,
    onboardingCompleted: student.onboarding_completed_at !== null,
  })
}))

// POST /:token/avaliacao — o próprio aluno responde a avaliação de saúde (PAR-Q) no primeiro acesso
router.post('/:token/avaliacao', asyncHandler(async (req: Request, res: Response): Promise<void> => {
  const student = await buscarAlunoPorToken(req.params.token as string)
  if (!student) {
    res.status(404).json({ error: 'Link inválido' })
    return
  }

  const { par_q_answers, health_notes } = req.body as {
    par_q_answers?: { cardiaco: boolean; tontura: boolean; articular: boolean; pressao_medicacao: boolean }
    health_notes?: string
  }
  if (!par_q_answers) {
    res.status(400).json({ error: 'par_q_answers é obrigatório' })
    return
  }

  await pool.query(
    `update students set par_q_answers = $1, health_notes = $2, onboarding_completed_at = now() where id = $3`,
    [JSON.stringify(par_q_answers), health_notes?.trim() || null, student.id]
  )

  res.status(201).json({ onboardingCompleted: true })
}))

// POST /:token/foto — o próprio aluno envia sua foto de perfil
router.post('/:token/foto', uploadFoto.single('foto'), asyncHandler(async (req: Request, res: Response): Promise<void> => {
  const student = await buscarAlunoPorToken(req.params.token as string)
  if (!student) {
    res.status(404).json({ error: 'Link inválido' })
    return
  }
  if (!req.file) {
    res.status(400).json({ error: 'Arquivo de imagem é obrigatório' })
    return
  }

  const photoUrl = `/uploads/student-photos/${req.file.filename}`
  await pool.query('update students set photo_url = $1 where id = $2', [photoUrl, student.id])

  res.status(201).json({ photoUrl })
}))

// POST /:token/sessoes — inicia (ou retoma) uma sessão de execução do treino
router.post('/:token/sessoes', asyncHandler(async (req: Request, res: Response): Promise<void> => {
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
}))

// POST /:token/sessoes/:sessionId/registros — registra uma série executada
router.post('/:token/sessoes/:sessionId/registros', asyncHandler(async (req: Request, res: Response): Promise<void> => {
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
}))

// POST /:token/sessoes/:sessionId/concluir — finaliza a sessão + feedback pós-treino
router.post('/:token/sessoes/:sessionId/concluir', asyncHandler(async (req: Request, res: Response): Promise<void> => {
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
}))

// ─────────────────────────────────────────────
// Chat (lado do aluno)
// ─────────────────────────────────────────────

async function montarContextoAluno(student: Student): Promise<ContextoAluno> {
  const { rows: workoutRows } = await pool.query<Workout>(
    `select * from workouts where student_id = $1 and status = 'sent' order by sent_at desc limit 1`,
    [student.id]
  )
  const workout = workoutRows[0] ?? null

  let exercicios: string[] = []
  if (workout) {
    const { rows } = await pool.query<{ name: string }>(
      `select e.name from workout_exercises we join exercises e on e.id = we.exercise_id
       where we.workout_id = $1 order by we.order_index`,
      [workout.id]
    )
    exercicios = rows.map((r) => r.name)
  }

  const { rows: statsRows } = await pool.query<{ concluidas: string }>(
    `select count(*) as concluidas from training_sessions where student_id = $1 and status = 'completed'`,
    [student.id]
  )

  const { rows: rpeRows } = await pool.query<{ effort_rpe: number | null }>(
    `select f.effort_rpe from feedbacks f
     join training_sessions ts on ts.id = f.training_session_id
     where ts.student_id = $1 order by f.created_at desc limit 1`,
    [student.id]
  )

  return {
    nome: student.name,
    objetivo: student.objective,
    pesoKg: student.weight_kg,
    alturaCm: student.height_cm,
    treinoAtual: workout?.name ?? null,
    exerciciosAtuais: exercicios,
    sessoesConcluidas: Number(statsRows[0]?.concluidas ?? 0),
    ultimoRpe: rpeRows[0]?.effort_rpe ?? null,
  }
}

// GET /:token/mensagens — histórico do chat do aluno
router.get('/:token/mensagens', asyncHandler(async (req: Request, res: Response): Promise<void> => {
  const student = await buscarAlunoPorToken(req.params.token as string)
  if (!student) {
    res.status(404).json({ error: 'Link inválido' })
    return
  }
  const { rows } = await pool.query(
    'select * from messages where student_id = $1 order by created_at',
    [student.id]
  )
  res.json({ messages: rows })
}))

// POST /:token/mensagens — aluno envia mensagem; IA responde se o autopilot estiver ligado
router.post('/:token/mensagens', asyncHandler(async (req: Request, res: Response): Promise<void> => {
  const student = await buscarAlunoPorToken(req.params.token as string)
  if (!student) {
    res.status(404).json({ error: 'Link inválido' })
    return
  }

  const { content } = req.body as { content?: string }
  if (!content?.trim()) {
    res.status(400).json({ error: 'content é obrigatório' })
    return
  }

  const { rows: inseridas } = await pool.query<Message>(
    `insert into messages (student_id, professional_id, sender, content)
     values ($1, $2, 'student', $3) returning *`,
    [student.id, student.professional_id, content.trim()]
  )
  const mensagemAluno = inseridas[0]

  let respostaIa: Message | null = null
  if (student.ai_autopilot) {
    try {
      const { rows: historico } = await pool.query<Message>(
        'select * from messages where student_id = $1 order by created_at',
        [student.id]
      )
      const contexto = await montarContextoAluno(student)
      const texto = await responderComoPersonal(historico, contexto)
      const { rows: iaRows } = await pool.query<Message>(
        `insert into messages (student_id, professional_id, sender, content)
         values ($1, $2, 'ai', $3) returning *`,
        [student.id, student.professional_id, texto]
      )
      respostaIa = iaRows[0]
    } catch (err) {
      // IA fora do ar não pode impedir o registro da mensagem do aluno —
      // o profissional responde manualmente depois.
      console.error('[Chat IA] Falha ao gerar resposta automática:', err)
    }
  }

  res.status(201).json({ message: mensagemAluno, aiReply: respostaIa })
}))

export default router
