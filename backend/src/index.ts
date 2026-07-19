import 'dotenv/config'
import cors from 'cors'
import express from 'express'
import authRoutes from './routes/auth'
import alunosRoutes from './routes/alunos'
import exerciciosRoutes from './routes/exercicios'
import treinosRoutes from './routes/treinos'
import portalRoutes from './routes/portal'

const app = express()

app.use(cors({ origin: process.env.FRONTEND_URL ?? 'http://localhost:3000' }))
app.use(express.json())

app.get('/health', (_req, res) => res.json({ status: 'ok' }))

app.use('/auth', authRoutes)
app.use('/alunos', alunosRoutes)
app.use('/exercicios', exerciciosRoutes)
app.use('/treinos', treinosRoutes)
app.use('/portal', portalRoutes)

app.use((err: Error, _req: express.Request, res: express.Response, _next: express.NextFunction) => {
  console.error('[Erro não tratado]', err)
  res.status(500).json({ error: 'Erro interno' })
})

const port = Number(process.env.PORT) || 3001
app.listen(port, () => console.log(`TrainOS backend rodando em http://localhost:${port}`))
