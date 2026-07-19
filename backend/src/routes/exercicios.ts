import { Router, Response } from 'express'
import { pool } from '../db/pool'
import { AuthedRequest, requireAuth } from '../middleware/auth'

const router = Router()
router.use(requireAuth)

router.get('/', async (_req: AuthedRequest, res: Response): Promise<void> => {
  const { rows } = await pool.query('select * from exercises order by muscle_group, name')
  res.json({ exercises: rows })
})

export default router
