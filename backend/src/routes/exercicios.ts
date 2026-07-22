import { Router, Response } from 'express'
import multer from 'multer'
import path from 'path'
import fs from 'fs'
import { nanoid } from 'nanoid'
import { pool } from '../db/pool'
import { asyncHandler } from '../middleware/asyncHandler'
import { AuthedRequest, requireAuth } from '../middleware/auth'

const router = Router()
router.use(requireAuth)

const uploadDir = path.join(__dirname, '..', '..', 'uploads', 'exercise-videos')
fs.mkdirSync(uploadDir, { recursive: true })

const upload = multer({
  storage: multer.diskStorage({
    destination: uploadDir,
    filename: (_req, file, cb) => cb(null, `${nanoid(12)}${path.extname(file.originalname)}`),
  }),
  limits: { fileSize: 100 * 1024 * 1024 }, // 100MB
  fileFilter: (_req, file, cb) => cb(null, file.mimetype.startsWith('video/')),
})

// GET / — biblioteca de exercícios, com o vídeo customizado do profissional (se houver) sobrepondo o padrão
router.get('/', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rows } = await pool.query(
    `select e.*,
            coalesce(emo.video_url, e.video_url) as video_url,
            emo.video_url is not null as video_customizado
     from exercises e
     left join exercise_media_overrides emo on emo.exercise_id = e.id and emo.professional_id = $1
     order by e.muscle_group, e.name`,
    [req.professionalId]
  )
  res.json({ exercises: rows })
}))

// POST /:id/video — envia (ou substitui) o vídeo customizado do profissional para este exercício
router.post('/:id/video', upload.single('video'), asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  const { rows: exerciseRows } = await pool.query('select id from exercises where id = $1', [req.params.id])
  if (exerciseRows.length === 0) {
    res.status(404).json({ error: 'Exercício não encontrado' })
    return
  }
  if (!req.file) {
    res.status(400).json({ error: 'Arquivo de vídeo é obrigatório' })
    return
  }

  const videoUrl = `/uploads/exercise-videos/${req.file.filename}`
  const { rows } = await pool.query(
    `insert into exercise_media_overrides (professional_id, exercise_id, video_url)
     values ($1, $2, $3)
     on conflict (professional_id, exercise_id) do update set video_url = excluded.video_url
     returning *`,
    [req.professionalId, req.params.id, videoUrl]
  )
  res.status(201).json({ override: rows[0] })
}))

// DELETE /:id/video — remove o vídeo customizado, voltando ao padrão da biblioteca
router.delete('/:id/video', asyncHandler(async (req: AuthedRequest, res: Response): Promise<void> => {
  await pool.query(
    'delete from exercise_media_overrides where professional_id = $1 and exercise_id = $2',
    [req.professionalId, req.params.id]
  )
  res.status(204).end()
}))

export default router
