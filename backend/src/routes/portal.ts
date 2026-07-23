import { Router, Request, Response } from 'express'
import fs from 'fs'
import path from 'path'
import { pool } from '../db/pool'
import { asyncHandler } from '../middleware/asyncHandler'
import { ContextoAluno, responderComoPersonal } from '../services/chat'
import { calcularBadges, calcularStreak } from '../services/gamification'
import { comentarPrimeiraFoto, compararEvolucaoFisica } from '../services/evolucaoFisica'
import {
  calcularResumoAno,
  calcularResumoMes,
  calcularResumoSemana,
  existeCheckinHoje,
  listarCheckinsPeriodo,
} from '../services/checkins'
import { analisarMidiaAcademia } from '../services/academiaAnalyzer'
import { ExercicioDisponivel, recomendarTreino } from '../services/academiaRecomendador'
import { extrairFramesDeVideo } from '../services/videoProcessor'
import { criarUploader, criarUploaderPrivadoPorChave, PRIVATE_UPLOADS_ROOT } from '../middleware/upload'
import {
  BodyPhoto,
  CheckIn,
  GymAnalysisResult,
  GymMediaAsset,
  GymMediaSubmission,
  GymSubmissionType,
  GymWorkoutRecommendation,
  Message,
  ParQAnswers,
  Student,
  Workout,
} from '../types'

const router = Router()
const uploadFoto = criarUploader('student-photos', 'image/', 10 * 1024 * 1024)
const uploadFotoEvolucao = criarUploaderPrivadoPorChave('token', 'body-photos', 'image/', 15 * 1024 * 1024)
const uploadCheckin = criarUploaderPrivadoPorChave('token', 'checkins', 'image/', 10 * 1024 * 1024)
const uploadMidiaAcademia = criarUploader('gym-media', ['image/', 'video/'], 100 * 1024 * 1024)

const GYM_MEDIA_DIR = path.join(__dirname, '..', '..', 'uploads', 'gym-media')

const LABEL_PAR_Q: Record<keyof ParQAnswers, string> = {
  cardiaco: 'histórico cardíaco',
  tontura: 'tontura/equilíbrio',
  articular: 'problema articular',
  pressao_medicacao: 'pressão alta ou uso de medicação contínua',
}

function limitacoesParQ(respostas: ParQAnswers | null): string[] {
  if (!respostas) return []
  return (Object.keys(LABEL_PAR_Q) as Array<keyof ParQAnswers>).filter((k) => respostas[k]).map((k) => LABEL_PAR_Q[k])
}

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

// ─────────────────────────────────────────────
// Evolução física por fotos (lado do aluno)
// ─────────────────────────────────────────────

// GET /:token/body-photos — galeria de fotos de evolução do aluno, mais recente primeiro
router.get('/:token/body-photos', asyncHandler(async (req: Request, res: Response): Promise<void> => {
  const student = await buscarAlunoPorToken(req.params.token as string)
  if (!student) {
    res.status(404).json({ error: 'Link inválido' })
    return
  }

  const { rows } = await pool.query<BodyPhoto>(
    `select id, student_id, taken_at, ai_feedback, compared_to_photo_id, created_at
     from body_photos where student_id = $1 order by taken_at desc`,
    [student.id]
  )
  res.json({ photos: rows })
}))

// POST /:token/body-photos — o aluno registra uma nova foto; dispara comentário da Coach IA
router.post(
  '/:token/body-photos',
  uploadFotoEvolucao.single('foto'),
  asyncHandler(async (req: Request, res: Response): Promise<void> => {
    const student = await buscarAlunoPorToken(req.params.token as string)
    if (!student) {
      res.status(404).json({ error: 'Link inválido' })
      return
    }
    if (!req.file) {
      res.status(400).json({ error: 'Arquivo de imagem é obrigatório' })
      return
    }

    const filePath = `body-photos/${req.params.token}/${req.file.filename}`
    const caminhoAbsolutoNova = path.join(PRIVATE_UPLOADS_ROOT, filePath)

    const { rows: anteriorRows } = await pool.query<BodyPhoto>(
      'select id, file_path from body_photos where student_id = $1 order by taken_at desc limit 1',
      [student.id]
    )
    const anterior = anteriorRows[0] ?? null

    let aiFeedback: string
    try {
      aiFeedback = anterior
        ? await compararEvolucaoFisica(
            student.name,
            path.join(PRIVATE_UPLOADS_ROOT, anterior.file_path),
            caminhoAbsolutoNova
          )
        : await comentarPrimeiraFoto(student.name, caminhoAbsolutoNova)
    } catch (err) {
      // A IA fora do ar não pode impedir o registro da foto — o comentário
      // fica em branco e o aluno vê a foto normalmente.
      console.error('[Evolução física] Falha ao gerar comentário da IA:', err)
      aiFeedback = anterior
        ? 'Foto registrada! Em breve o comentário da Coach IA aparece por aqui.'
        : 'Primeira foto registrada! Esse é o seu ponto de partida — daqui pra frente dá pra acompanhar sua evolução de verdade.'
    }

    const { rows } = await pool.query<BodyPhoto>(
      `insert into body_photos (student_id, file_path, ai_feedback, compared_to_photo_id)
       values ($1, $2, $3, $4)
       returning id, student_id, taken_at, ai_feedback, compared_to_photo_id, created_at`,
      [student.id, filePath, aiFeedback, anterior?.id ?? null]
    )
    res.status(201).json({ photo: rows[0] })
  })
)

// GET /:token/body-photos/:photoId/imagem — serve o arquivo (autenticado pelo token do aluno)
router.get(
  '/:token/body-photos/:photoId/imagem',
  asyncHandler(async (req: Request, res: Response): Promise<void> => {
    const student = await buscarAlunoPorToken(req.params.token as string)
    if (!student) {
      res.status(404).json({ error: 'Link inválido' })
      return
    }

    const { rows } = await pool.query<{ file_path: string }>(
      'select file_path from body_photos where id = $1 and student_id = $2',
      [req.params.photoId, student.id]
    )
    if (!rows[0]) {
      res.status(404).json({ error: 'Foto não encontrada' })
      return
    }

    res.sendFile(path.join(PRIVATE_UPLOADS_ROOT, rows[0].file_path))
  })
)

// ─────────────────────────────────────────────
// Check-in de frequência (foto do dia, separado da Evolução física)
// ─────────────────────────────────────────────

// GET /:token/checkins/summary — marcadores semanal/mensal/anual + grid da semana atual
router.get(
  '/:token/checkins/summary',
  asyncHandler(async (req: Request, res: Response): Promise<void> => {
    const student = await buscarAlunoPorToken(req.params.token as string)
    if (!student) {
      res.status(404).json({ error: 'Link inválido' })
      return
    }

    const [semana, mes, ano, checkinHoje] = await Promise.all([
      calcularResumoSemana(student.id, null),
      calcularResumoMes(student.id, null),
      calcularResumoAno(student.id, null),
      existeCheckinHoje(student.id),
    ])

    res.json({ semana, mes, ano, checkinHoje })
  })
)

// GET /:token/checkins?period=week|month|year&ref=YYYY-MM-DD — histórico navegável
// (grid/contagem do período + galeria de fotos com comentário, mais recente primeiro)
router.get(
  '/:token/checkins',
  asyncHandler(async (req: Request, res: Response): Promise<void> => {
    const student = await buscarAlunoPorToken(req.params.token as string)
    if (!student) {
      res.status(404).json({ error: 'Link inválido' })
      return
    }

    const ref = typeof req.query.ref === 'string' ? req.query.ref : null
    const period: 'week' | 'month' | 'year' =
      req.query.period === 'month' ? 'month' : req.query.period === 'year' ? 'year' : 'week'

    const fotos = await listarCheckinsPeriodo(student.id, period, ref)

    if (period === 'month') {
      res.json({ period, mes: await calcularResumoMes(student.id, ref), fotos })
    } else if (period === 'year') {
      res.json({ period, ano: await calcularResumoAno(student.id, ref), fotos })
    } else {
      res.json({ period, semana: await calcularResumoSemana(student.id, ref), fotos })
    }
  })
)

// POST /:token/checkins — marca o treino de hoje (substitui a foto se já tiver check-in hoje)
router.post(
  '/:token/checkins',
  uploadCheckin.single('foto'),
  asyncHandler(async (req: Request, res: Response): Promise<void> => {
    const student = await buscarAlunoPorToken(req.params.token as string)
    if (!student) {
      res.status(404).json({ error: 'Link inválido' })
      return
    }
    if (!req.file) {
      res.status(400).json({ error: 'Arquivo de imagem é obrigatório' })
      return
    }

    const filePath = `checkins/${req.params.token}/${req.file.filename}`
    const comment = typeof req.body.comment === 'string' ? req.body.comment.trim() || null : null

    const { rows: existenteRows } = await pool.query<{ file_path: string }>(
      'select file_path from checkins where student_id = $1 and checkin_date = current_date',
      [student.id]
    )
    const arquivoAnterior = existenteRows[0]?.file_path ?? null

    const { rows } = await pool.query<CheckIn>(
      `insert into checkins (student_id, file_path, comment)
       values ($1, $2, $3)
       on conflict (student_id, checkin_date) do update set file_path = excluded.file_path, comment = excluded.comment
       returning *`,
      [student.id, filePath, comment]
    )

    // Se já existia check-in hoje, a foto antiga foi substituída — remove o
    // arquivo órfão do disco (sem travar a resposta se der erro).
    if (arquivoAnterior && arquivoAnterior !== filePath) {
      fs.unlink(path.join(PRIVATE_UPLOADS_ROOT, arquivoAnterior), () => {})
    }

    res.status(201).json({ checkin: rows[0] })
  })
)

// GET /:token/checkins/:checkinId/imagem — serve o arquivo (autenticado pelo token do aluno)
router.get(
  '/:token/checkins/:checkinId/imagem',
  asyncHandler(async (req: Request, res: Response): Promise<void> => {
    const student = await buscarAlunoPorToken(req.params.token as string)
    if (!student) {
      res.status(404).json({ error: 'Link inválido' })
      return
    }

    const { rows } = await pool.query<{ file_path: string }>(
      'select file_path from checkins where id = $1 and student_id = $2',
      [req.params.checkinId, student.id]
    )
    if (!rows[0]) {
      res.status(404).json({ error: 'Check-in não encontrado' })
      return
    }

    res.sendFile(path.join(PRIVATE_UPLOADS_ROOT, rows[0].file_path))
  })
)

// ─────────────────────────────────────────────
// Análise de academia por mídia (foto, vídeo ou álbum)
// ─────────────────────────────────────────────

// GET /:token/academia — histórico de submissões do aluno, mais recente primeiro
router.get('/:token/academia', asyncHandler(async (req: Request, res: Response): Promise<void> => {
  const student = await buscarAlunoPorToken(req.params.token as string)
  if (!student) {
    res.status(404).json({ error: 'Link inválido' })
    return
  }

  const { rows } = await pool.query(
    `select s.*, r.approval_status, r.name as recommendation_name
     from gym_media_submissions s
     left join gym_workout_recommendations r on r.submission_id = s.id
     where s.student_id = $1
     order by s.created_at desc`,
    [student.id]
  )
  res.json({ submissions: rows })
}))

// GET /:token/academia/:submissionId — detalhe de uma submissão (mídia, análise e recomendação)
router.get('/:token/academia/:submissionId', asyncHandler(async (req: Request, res: Response): Promise<void> => {
  const student = await buscarAlunoPorToken(req.params.token as string)
  if (!student) {
    res.status(404).json({ error: 'Link inválido' })
    return
  }

  const { rows: submissionRows } = await pool.query<GymMediaSubmission>(
    'select * from gym_media_submissions where id = $1 and student_id = $2',
    [req.params.submissionId, student.id]
  )
  const submission = submissionRows[0]
  if (!submission) {
    res.status(404).json({ error: 'Submissão não encontrada' })
    return
  }

  const { rows: assets } = await pool.query<GymMediaAsset>(
    'select * from gym_media_assets where submission_id = $1 order by frame_index nulls first',
    [submission.id]
  )
  const { rows: analysisRows } = await pool.query<GymAnalysisResult>(
    'select * from gym_analysis_results where submission_id = $1',
    [submission.id]
  )
  const { rows: recommendationRows } = await pool.query<GymWorkoutRecommendation>(
    'select * from gym_workout_recommendations where submission_id = $1',
    [submission.id]
  )

  res.json({
    submission,
    assets,
    analysis: analysisRows[0] ?? null,
    recommendation: recommendationRows[0] ?? null,
  })
}))

// POST /:token/academia — aluno envia foto(s) ou vídeo da academia; dispara o pipeline completo
router.post(
  '/:token/academia',
  uploadMidiaAcademia.array('media', 10),
  asyncHandler(async (req: Request, res: Response): Promise<void> => {
    const student = await buscarAlunoPorToken(req.params.token as string)
    if (!student) {
      res.status(404).json({ error: 'Link inválido' })
      return
    }

    const files = (req.files as Express.Multer.File[] | undefined) ?? []
    if (files.length === 0) {
      res.status(400).json({ error: 'Envie ao menos uma foto ou um vídeo' })
      return
    }

    const videos = files.filter((f) => f.mimetype.startsWith('video/'))
    if (videos.length > 1 || (videos.length === 1 && files.length > 1)) {
      res.status(400).json({ error: 'Envie um vídeo por vez, ou uma ou mais fotos' })
      return
    }

    const daysPerWeek = Math.min(6, Math.max(2, parseInt(String(req.body.days_per_week), 10) || 3))
    const submissionType: GymSubmissionType = videos.length === 1 ? 'video' : files.length > 1 ? 'album' : 'photo'

    const { rows: submissionRows } = await pool.query<GymMediaSubmission>(
      `insert into gym_media_submissions (student_id, professional_id, submission_type, days_per_week)
       values ($1, $2, $3, $4) returning *`,
      [student.id, student.professional_id, submissionType, daysPerWeek]
    )
    const submission = submissionRows[0]

    try {
      let caminhosParaAnalise: { caminhoAbsoluto: string; asset: { asset_type: 'photo' | 'video_frame'; file_path: string; frame_index: number | null } }[]

      if (submissionType === 'video') {
        const dirFrames = path.join(GYM_MEDIA_DIR, `${submission.id}-frames`)
        const frames = await extrairFramesDeVideo(videos[0]!.path, dirFrames)
        caminhosParaAnalise = frames.map((caminho, i) => ({
          caminhoAbsoluto: caminho,
          asset: {
            asset_type: 'video_frame',
            file_path: `/uploads/gym-media/${submission.id}-frames/${path.basename(caminho)}`,
            frame_index: i,
          },
        }))
      } else {
        caminhosParaAnalise = files.map((f) => ({
          caminhoAbsoluto: f.path,
          asset: { asset_type: 'photo', file_path: `/uploads/gym-media/${f.filename}`, frame_index: null },
        }))
      }

      for (const item of caminhosParaAnalise) {
        await pool.query(
          `insert into gym_media_assets (submission_id, asset_type, file_path, frame_index) values ($1, $2, $3, $4)`,
          [submission.id, item.asset.asset_type, item.asset.file_path, item.asset.frame_index]
        )
      }

      const analise = await analisarMidiaAcademia(caminhosParaAnalise.map((c) => c.caminhoAbsoluto))

      const { rows: analysisRows } = await pool.query<GymAnalysisResult>(
        `insert into gym_analysis_results
           (submission_id, machines_json, zones_identified, total_unique_machines, coverage_estimate, gaps, notes)
         values ($1, $2, $3, $4, $5, $6, $7) returning *`,
        [
          submission.id,
          JSON.stringify(analise.machines),
          analise.zones_identified,
          analise.machines.machines.length,
          analise.coverage_estimate,
          analise.gaps,
          analise.notes,
        ]
      )
      const analysisResult = analysisRows[0]!

      const { rows: exerciseRows } = await pool.query<ExercicioDisponivel>(
        'select id, name, muscle_group, equipment from exercises order by muscle_group, name'
      )

      const recomendacao = await recomendarTreino(analise.machines, exerciseRows, {
        nome: student.name,
        objective: student.objective,
        healthNotes: student.health_notes,
        limitacoesParQ: limitacoesParQ(student.par_q_answers),
        daysPerWeek,
      })

      const { rows: recommendationRows } = await pool.query<GymWorkoutRecommendation>(
        `insert into gym_workout_recommendations
           (submission_id, analysis_result_id, name, split_type, reasoning, recommended_items)
         values ($1, $2, $3, $4, $5, $6) returning *`,
        [
          submission.id,
          analysisResult.id,
          recomendacao.name,
          recomendacao.split_type,
          recomendacao.reasoning,
          JSON.stringify(recomendacao.items),
        ]
      )

      await pool.query(`update gym_media_submissions set status = 'completed' where id = $1`, [submission.id])

      res.status(201).json({
        submission: { ...submission, status: 'completed' },
        analysis: analysisResult,
        recommendation: recommendationRows[0],
      })
    } catch (err) {
      console.error('[Análise de academia] Falha no pipeline:', err)
      const mensagem = err instanceof Error ? err.message : 'Erro desconhecido'
      await pool.query(`update gym_media_submissions set status = 'failed', error_message = $2 where id = $1`, [
        submission.id,
        mensagem,
      ])
      res.status(201).json({
        submission: { ...submission, status: 'failed', error_message: mensagem },
        analysis: null,
        recommendation: null,
      })
    }
  })
)

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

  // Checa recorde ANTES de inserir a nova série, comparando com o maior peso
  // já registrado pelo aluno nesse exercício (em qualquer treino/sessão).
  let isPr = false
  if (load_kg_done) {
    const { rows: maxRows } = await pool.query<{ max_anterior: string | null }>(
      `select max(se.load_kg_done) as max_anterior
       from session_entries se
       join workout_exercises we on we.id = se.workout_exercise_id
       join training_sessions ts on ts.id = se.training_session_id
       where ts.student_id = $1
         and we.exercise_id = (select exercise_id from workout_exercises where id = $2)`,
      [student.id, workout_exercise_id]
    )
    const maxAnterior = maxRows[0]?.max_anterior ? Number(maxRows[0].max_anterior) : 0
    isPr = load_kg_done > maxAnterior
  }

  const { rows } = await pool.query(
    `insert into session_entries (training_session_id, workout_exercise_id, set_number, reps_done, load_kg_done, notes)
     values ($1, $2, $3, $4, $5, $6) returning *`,
    [req.params.sessionId, workout_exercise_id, set_number, reps_done ?? null, load_kg_done ?? null, notes ?? null]
  )
  res.status(201).json({ entry: rows[0], isPr })
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
