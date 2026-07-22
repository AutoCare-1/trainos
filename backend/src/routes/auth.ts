import { Router, Request, Response } from 'express'
import { pool } from '../db/pool'
import { asyncHandler } from '../middleware/asyncHandler'
import { AuthedRequest, requireAuth } from '../middleware/auth'
import { gerarToken, hashPassword, verificarSenha } from '../services/auth'
import { Professional } from '../types'

const router = Router()

// GET /me — dados do profissional autenticado (pra exibir nome no menu, etc.)
router.get('/me', requireAuth, asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rows } = await pool.query<Professional>(
    'select id, name, email from professionals where id = $1',
    [req.professionalId]
  )
  if (rows.length === 0) {
    res.status(404).json({ error: 'Profissional não encontrado' })
    return
  }
  res.json({ professional: rows[0] })
}))

router.post('/signup', asyncHandler(async (req: Request, res: Response): Promise<void> => {
  const { name, email, password } = req.body as { name?: string; email?: string; password?: string }
  if (!name?.trim() || !email?.trim() || !password || password.length < 6) {
    res.status(400).json({ error: 'name, email e password (mín. 6 caracteres) são obrigatórios' })
    return
  }

  const existente = await pool.query('select id from professionals where email = $1', [email.trim().toLowerCase()])
  if (existente.rows.length > 0) {
    res.status(409).json({ error: 'Já existe um profissional com este e-mail' })
    return
  }

  const passwordHash = await hashPassword(password)
  const { rows } = await pool.query<Professional>(
    `insert into professionals (name, email, password_hash) values ($1, $2, $3) returning id, name, email, created_at`,
    [name.trim(), email.trim().toLowerCase(), passwordHash]
  )

  const professional = rows[0]
  res.status(201).json({ token: gerarToken(professional.id), professional })
}))

router.post('/login', asyncHandler(async (req: Request, res: Response): Promise<void> => {
  const { email, password } = req.body as { email?: string; password?: string }
  if (!email?.trim() || !password) {
    res.status(400).json({ error: 'email e password são obrigatórios' })
    return
  }

  const { rows } = await pool.query<Professional>('select * from professionals where email = $1', [email.trim().toLowerCase()])
  const professional = rows[0]
  if (!professional || !(await verificarSenha(password, professional.password_hash))) {
    res.status(401).json({ error: 'E-mail ou senha inválidos' })
    return
  }

  res.json({
    token: gerarToken(professional.id),
    professional: { id: professional.id, name: professional.name, email: professional.email },
  })
}))

export default router
