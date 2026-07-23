import { randomUUID } from 'crypto'
import { Router, Response } from 'express'
import { pool } from '../db/pool'
import { asyncHandler } from '../middleware/asyncHandler'
import { AuthedRequest, requireAuth } from '../middleware/auth'
import { montarResumoAgregadoAlunos } from '../services/conteudoAgregados'
import { gerarIdeiasConteudo } from '../services/conteudoIdeias'
import { ContentIdea } from '../types'

const router = Router()
router.use(requireAuth)

// Limite de gerações (não de ideias) por dia — cada geração cria um lote (batch)
// de várias ideias; isso controla o custo da busca na web e da própria chamada.
const LIMITE_GERACOES_POR_DIA = 5

// GET / — histórico de ideias já geradas, mais recente primeiro
router.get('/', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rows } = await pool.query<ContentIdea>(
    'select * from content_ideas where professional_id = $1 order by created_at desc',
    [req.professionalId]
  )
  res.json({ ideas: rows })
}))

// POST / — gera um novo lote de ideias (funde tendência de formato + dado agregado da base)
router.post('/', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rows: geracoesHoje } = await pool.query<{ total: string }>(
    `select count(distinct batch_id) as total
     from content_ideas
     where professional_id = $1 and created_at >= current_date`,
    [req.professionalId]
  )
  if (Number(geracoesHoje[0]?.total ?? 0) >= LIMITE_GERACOES_POR_DIA) {
    res.status(429).json({ error: `Limite de ${LIMITE_GERACOES_POR_DIA} gerações por dia atingido. Tente novamente amanhã.` })
    return
  }

  const { direcionamento } = req.body as { direcionamento?: string }

  const resumoAgregado = await montarResumoAgregadoAlunos(req.professionalId as string)
  const ideias = await gerarIdeiasConteudo(resumoAgregado, direcionamento?.trim() || null)

  const batchId = randomUUID()
  const inseridas: ContentIdea[] = []
  for (const ideia of ideias) {
    const { rows } = await pool.query<ContentIdea>(
      `insert into content_ideas (professional_id, batch_id, format, title, description, caption_suggestion)
       values ($1, $2, $3, $4, $5, $6) returning *`,
      [req.professionalId, batchId, ideia.format, ideia.title, ideia.description, ideia.caption_suggestion]
    )
    inseridas.push(rows[0])
  }

  res.status(201).json({ ideas: inseridas })
}))

// PATCH /:id — favorita/desfavorita uma ideia
router.patch('/:id', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { saved } = req.body as { saved?: boolean }
  if (typeof saved !== 'boolean') {
    res.status(400).json({ error: 'saved (boolean) é obrigatório' })
    return
  }

  const { rows } = await pool.query<ContentIdea>(
    'update content_ideas set saved = $1 where id = $2 and professional_id = $3 returning *',
    [saved, req.params.id, req.professionalId]
  )
  if (!rows[0]) {
    res.status(404).json({ error: 'Ideia não encontrada' })
    return
  }
  res.json({ idea: rows[0] })
}))

export default router
