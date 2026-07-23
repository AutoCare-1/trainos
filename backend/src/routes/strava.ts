import { Router, Request, Response } from 'express'
import { pool } from '../db/pool'
import { asyncHandler } from '../middleware/asyncHandler'
import { montarUrlAutorizacao, sincronizarAtividades, trocarCodigoPorToken } from '../services/strava'
import { Student } from '../types'

const router = Router()

const FRONTEND_URL = process.env.FRONTEND_URL ?? 'http://localhost:3101'
const BACKEND_URL = process.env.BACKEND_URL ?? 'http://localhost:3002'

async function buscarAlunoPorToken(token: string): Promise<Student | null> {
  const { rows } = await pool.query<Student>('select * from students where invite_token = $1', [token])
  return rows[0] ?? null
}

// GET /strava/conectar/:token — redireciona o aluno para a tela de autorização do Strava
router.get('/conectar/:token', asyncHandler(async (req: Request, res: Response): Promise<void> => {
  const student = await buscarAlunoPorToken(req.params.token as string)
  if (!student) {
    res.status(404).send('Link inválido')
    return
  }
  const redirectUri = `${BACKEND_URL}/strava/callback`
  const url = montarUrlAutorizacao(redirectUri, req.params.token as string)
  res.redirect(url)
}))

// GET /strava/callback — o Strava chama esta rota depois que o aluno autoriza
router.get('/callback', asyncHandler(async (req: Request, res: Response): Promise<void> => {
  const { code, state, error } = req.query as { code?: string; state?: string; error?: string }
  const voltarPara = (status: 'conectado' | 'erro') =>
    res.redirect(`${FRONTEND_URL}/aluno/${encodeURIComponent(state ?? '')}?strava=${status}`)

  if (error || !code || !state) {
    voltarPara('erro')
    return
  }

  const student = await buscarAlunoPorToken(state)
  if (!student) {
    res.status(404).send('Link inválido')
    return
  }

  const dados = await trocarCodigoPorToken(code)

  await pool.query(
    `insert into device_connections (student_id, provider, provider_athlete_id, access_token, refresh_token, expires_at, scope)
     values ($1, 'strava', $2, $3, $4, to_timestamp($5), $6)
     on conflict (student_id, provider) do update set
       provider_athlete_id = excluded.provider_athlete_id,
       access_token = excluded.access_token,
       refresh_token = excluded.refresh_token,
       expires_at = excluded.expires_at,
       scope = excluded.scope`,
    [student.id, String(dados.athlete?.id ?? ''), dados.access_token, dados.refresh_token, dados.expires_at, 'activity:read_all']
  )

  voltarPara('conectado')
}))

// GET /strava/:token/status — status da conexão + atividades sincronizadas
router.get('/:token/status', asyncHandler(async (req: Request, res: Response): Promise<void> => {
  const student = await buscarAlunoPorToken(req.params.token as string)
  if (!student) {
    res.status(404).json({ error: 'Link inválido' })
    return
  }

  const { rows: conexoes } = await pool.query(
    `select provider, connected_at from device_connections where student_id = $1`,
    [student.id]
  )
  const { rows: atividades } = await pool.query(
    `select * from external_activities where student_id = $1 order by started_at desc limit 20`,
    [student.id]
  )

  res.json({ conectado: conexoes.length > 0, conexoes, atividades })
}))

// POST /portal-strava/:token/sincronizar — busca atividades recentes do Strava
router.post('/:token/sincronizar', asyncHandler(async (req: Request, res: Response): Promise<void> => {
  const student = await buscarAlunoPorToken(req.params.token as string)
  if (!student) {
    res.status(404).json({ error: 'Link inválido' })
    return
  }

  try {
    const novas = await sincronizarAtividades(student.id)
    res.json({ novas })
  } catch (err) {
    res.status(400).json({ error: err instanceof Error ? err.message : 'Erro ao sincronizar' })
  }
}))

export default router
