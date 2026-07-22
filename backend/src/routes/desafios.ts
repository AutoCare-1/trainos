import { Router, Response } from 'express'
import { pool } from '../db/pool'
import { asyncHandler } from '../middleware/asyncHandler'
import { AuthedRequest, requireAuth } from '../middleware/auth'

const router = Router()
router.use(requireAuth)

// GET / — lista desafios do profissional, com contagem de participantes e status calculado
router.get('/', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rows } = await pool.query(
    `select c.*,
            (select count(*) from challenge_participants cp where cp.challenge_id = c.id) as total_participantes,
            case
              when current_date < c.start_date then 'agendado'
              when current_date > c.end_date then 'encerrado'
              else 'ativo'
            end as status
     from challenges c
     where c.professional_id = $1
     order by c.start_date desc`,
    [req.professionalId]
  )
  res.json({ challenges: rows })
}))

// POST / — cria um desafio e adiciona os alunos participantes
router.post('/', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { name, description, start_date, end_date, student_ids } = req.body as {
    name?: string
    description?: string
    start_date?: string
    end_date?: string
    student_ids?: string[]
  }
  if (!name?.trim() || !start_date || !end_date || !student_ids?.length) {
    res.status(400).json({ error: 'name, start_date, end_date e student_ids (não vazio) são obrigatórios' })
    return
  }

  const client = await pool.connect()
  try {
    await client.query('begin')

    const { rows: challengeRows } = await client.query(
      `insert into challenges (professional_id, name, description, start_date, end_date)
       values ($1, $2, $3, $4, $5) returning *`,
      [req.professionalId, name.trim(), description?.trim() || null, start_date, end_date]
    )
    const challenge = challengeRows[0]

    for (const studentId of student_ids) {
      await client.query(
        `insert into challenge_participants (challenge_id, student_id)
         select $1, id from students where id = $2 and professional_id = $3
         on conflict do nothing`,
        [challenge.id, studentId, req.professionalId]
      )
    }

    await client.query('commit')
    res.status(201).json({ challenge })
  } catch (err) {
    await client.query('rollback')
    throw err
  } finally {
    client.release()
  }
}))

// GET /:id — detalhe do desafio com quadro de destaques (pontos = treinos concluídos no período)
router.get('/:id', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rows: challengeRows } = await pool.query(
    'select * from challenges where id = $1 and professional_id = $2',
    [req.params.id, req.professionalId]
  )
  const challenge = challengeRows[0]
  if (!challenge) {
    res.status(404).json({ error: 'Desafio não encontrado' })
    return
  }

  const { rows: leaderboard } = await pool.query(
    `select s.id as student_id, s.name,
            count(ts.id) filter (
              where ts.status = 'completed'
              and ts.finished_at::date between $2 and $3
            ) as pontos
     from challenge_participants cp
     join students s on s.id = cp.student_id
     left join workouts w on w.student_id = s.id
     left join training_sessions ts on ts.workout_id = w.id and ts.student_id = s.id
     where cp.challenge_id = $1
     group by s.id, s.name
     order by pontos desc, s.name`,
    [challenge.id, challenge.start_date, challenge.end_date]
  )

  res.json({ challenge, leaderboard })
}))

// DELETE /:id — remove o desafio
router.delete('/:id', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rowCount } = await pool.query(
    'delete from challenges where id = $1 and professional_id = $2',
    [req.params.id, req.professionalId]
  )
  if (rowCount === 0) {
    res.status(404).json({ error: 'Desafio não encontrado' })
    return
  }
  res.status(204).end()
}))

export default router
