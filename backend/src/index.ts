import 'dotenv/config'
import cors from 'cors'
import express from 'express'
import path from 'path'
import authRoutes from './routes/auth'
import alunosRoutes from './routes/alunos'
import exerciciosRoutes from './routes/exercicios'
import treinosRoutes from './routes/treinos'
import portalRoutes from './routes/portal'
import modelosRoutes from './routes/modelos'
import stravaRoutes from './routes/strava'

const app = express()

app.use(cors({ origin: process.env.FRONTEND_URL ?? 'http://localhost:3000' }))
app.use(express.json())
app.use('/uploads', express.static(path.join(__dirname, '..', 'uploads')))

app.get('/health', (_req, res) => res.json({ status: 'ok' }))

app.use('/auth', authRoutes)
app.use('/alunos', alunosRoutes)
app.use('/exercicios', exerciciosRoutes)
app.use('/treinos', treinosRoutes)
app.use('/portal', portalRoutes)
app.use('/modelos', modelosRoutes)
app.use('/strava', stravaRoutes)

app.use((err: Error & { code?: string }, _req: express.Request, res: express.Response, _next: express.NextFunction) => {
  if (err.code === '22P02') {
    // invalid_text_representation do Postgres — ex: id/token que não é um UUID válido
    res.status(400).json({ error: 'Identificador inválido' })
    return
  }
  console.error('[Erro não tratado]', err)
  res.status(500).json({ error: 'Erro interno' })
})

const port = Number(process.env.PORT) || 3001
app.listen(port, () => console.log(`Clube Mais Personal — backend rodando em http://localhost:${port}`))
