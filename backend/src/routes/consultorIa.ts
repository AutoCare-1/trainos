import { Router, Response } from 'express'
import { pool } from '../db/pool'
import { asyncHandler } from '../middleware/asyncHandler'
import { AuthedRequest, requireAuth } from '../middleware/auth'
import { responderConsultor } from '../services/consultorIa'
import { ConsultorIaMessage } from '../types'

const router = Router()
router.use(requireAuth)

// GET / — histórico do chat, em ordem cronológica
router.get('/', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rows } = await pool.query<ConsultorIaMessage>(
    'select * from consultor_ia_messages where professional_id = $1 order by created_at',
    [req.professionalId]
  )
  res.json({ messages: rows })
}))

// POST /chat — envia uma pergunta e recebe a resposta da IA (com tool use)
router.post('/chat', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { content } = req.body as { content?: string }
  if (!content?.trim()) {
    res.status(400).json({ error: 'content é obrigatório' })
    return
  }

  const { rows: inseridas } = await pool.query<ConsultorIaMessage>(
    `insert into consultor_ia_messages (professional_id, role, content) values ($1, 'personal', $2) returning *`,
    [req.professionalId, content.trim()]
  )
  const mensagemPersonal = inseridas[0]

  const { rows: historico } = await pool.query<ConsultorIaMessage>(
    `select * from (
       select * from consultor_ia_messages where professional_id = $1 order by created_at desc limit 30
     ) recentes order by created_at asc`,
    [req.professionalId]
  )

  const texto = await responderConsultor(req.professionalId as string, historico)

  const { rows: iaRows } = await pool.query<ConsultorIaMessage>(
    `insert into consultor_ia_messages (professional_id, role, content) values ($1, 'ai', $2) returning *`,
    [req.professionalId, texto]
  )

  res.status(201).json({ message: mensagemPersonal, aiReply: iaRows[0] })
}))

export default router
