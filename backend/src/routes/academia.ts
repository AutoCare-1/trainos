import { Router, Response } from 'express'
import { pool } from '../db/pool'
import { asyncHandler } from '../middleware/asyncHandler'
import { AuthedRequest, requireAuth } from '../middleware/auth'
import {
  GymAnalysisResult,
  GymMediaAsset,
  GymMediaSubmission,
  GymWorkoutRecommendation,
  RecommendedItem,
  Workout,
} from '../types'

const router = Router()
router.use(requireAuth)

interface ItemAprovado {
  exercise_id: string
  sets: number
  reps: string
  rest_seconds?: number
  notes?: string
}

// GET / — submissões dos alunos do profissional, mais recente primeiro (filtro opcional ?status=pending)
router.get('/', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const statusFiltro = req.query.status as string | undefined

  const { rows } = await pool.query(
    `select sub.*, s.name as student_name, s.photo_url as student_photo_url,
            r.id as recommendation_id, r.name as recommendation_name, r.approval_status
     from gym_media_submissions sub
     join students s on s.id = sub.student_id
     left join gym_workout_recommendations r on r.submission_id = sub.id
     where sub.professional_id = $1
       and ($2::text is null or r.approval_status = $2)
     order by sub.created_at desc`,
    [req.professionalId, statusFiltro ?? null]
  )
  res.json({ submissions: rows })
}))

// GET /:submissionId — detalhe completo (mídia, análise, recomendação com dados dos exercícios)
router.get('/:submissionId', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rows: submissionRows } = await pool.query(
    `select sub.*, s.name as student_name, s.photo_url as student_photo_url, s.objective, s.health_notes, s.par_q_answers
     from gym_media_submissions sub
     join students s on s.id = sub.student_id
     where sub.id = $1 and sub.professional_id = $2`,
    [req.params.submissionId, req.professionalId]
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
  const recommendation = recommendationRows[0] ?? null

  let items: (RecommendedItem & { muscle_group?: string; image_url?: string | null })[] = []
  if (recommendation) {
    const ids = recommendation.recommended_items.map((i) => i.exercise_id)
    const { rows: exerciseRows } = await pool.query(
      'select id, muscle_group, image_url from exercises where id = any($1::uuid[])',
      [ids]
    )
    const porId = new Map(exerciseRows.map((e) => [e.id, e]))
    items = recommendation.recommended_items.map((item) => ({
      ...item,
      muscle_group: porId.get(item.exercise_id)?.muscle_group,
      image_url: porId.get(item.exercise_id)?.image_url ?? null,
    }))
  }

  res.json({
    submission,
    assets,
    analysis: analysisRows[0] ?? null,
    recommendation: recommendation ? { ...recommendation, recommended_items: items } : null,
  })
}))

// PATCH /:submissionId/aprovar — cria o treino real do aluno a partir da recomendação (com possíveis edições)
router.patch('/:submissionId/aprovar', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { items, notes } = req.body as { items?: ItemAprovado[]; notes?: string }

  const { rows: submissionRows } = await pool.query<GymMediaSubmission>(
    'select * from gym_media_submissions where id = $1 and professional_id = $2',
    [req.params.submissionId, req.professionalId]
  )
  const submission = submissionRows[0]
  if (!submission) {
    res.status(404).json({ error: 'Submissão não encontrada' })
    return
  }

  const { rows: recommendationRows } = await pool.query<GymWorkoutRecommendation>(
    'select * from gym_workout_recommendations where submission_id = $1',
    [submission.id]
  )
  const recommendation = recommendationRows[0]
  if (!recommendation) {
    res.status(404).json({ error: 'Recomendação não encontrada' })
    return
  }
  if (recommendation.approval_status !== 'pending') {
    res.status(400).json({ error: 'Esta recomendação já foi decidida' })
    return
  }

  const itensFinais = items?.length ? items : recommendation.recommended_items
  if (!itensFinais.length) {
    res.status(400).json({ error: 'Nenhum exercício pra aprovar' })
    return
  }

  const client = await pool.connect()
  try {
    await client.query('begin')

    const { rows: workoutRows } = await client.query<Workout>(
      `insert into workouts (professional_id, student_id, name) values ($1, $2, $3) returning *`,
      [req.professionalId, submission.student_id, recommendation.name]
    )
    const workout = workoutRows[0]!

    let orderIndex = 0
    for (const item of itensFinais) {
      await client.query(
        `insert into workout_exercises (workout_id, exercise_id, order_index, sets, reps, rest_seconds, notes)
         values ($1, $2, $3, $4, $5, $6, $7)`,
        [workout.id, item.exercise_id, orderIndex++, item.sets, item.reps, item.rest_seconds ?? null, item.notes ?? null]
      )
    }

    await client.query(
      `update gym_workout_recommendations
       set approval_status = 'approved', approved_workout_id = $1, professional_notes = $2, approved_at = now()
       where id = $3`,
      [workout.id, notes ?? null, recommendation.id]
    )

    await client.query('commit')
    res.json({ workout })
  } catch (err) {
    await client.query('rollback')
    throw err
  } finally {
    client.release()
  }
}))

// PATCH /:submissionId/rejeitar — descarta a recomendação, sem criar treino
router.patch('/:submissionId/rejeitar', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { notes } = req.body as { notes?: string }

  const { rows } = await pool.query<GymWorkoutRecommendation>(
    `update gym_workout_recommendations r
     set approval_status = 'rejected', professional_notes = $1, approved_at = now()
     from gym_media_submissions sub
     where r.submission_id = sub.id
       and sub.id = $2
       and sub.professional_id = $3
       and r.approval_status = 'pending'
     returning r.*`,
    [notes ?? null, req.params.submissionId, req.professionalId]
  )
  if (rows.length === 0) {
    res.status(404).json({ error: 'Recomendação não encontrada ou já decidida' })
    return
  }
  res.json({ recommendation: rows[0] })
}))

export default router
