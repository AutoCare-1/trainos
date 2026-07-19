import { NextFunction, Request, Response } from 'express'
import { verificarToken } from '../services/auth'

export interface AuthedRequest extends Request {
  professionalId?: string
}

export function requireAuth(req: AuthedRequest, res: Response, next: NextFunction): void {
  const header = req.headers.authorization
  const token = header?.startsWith('Bearer ') ? header.slice(7) : null
  if (!token) {
    res.status(401).json({ error: 'Não autenticado' })
    return
  }

  const payload = verificarToken(token)
  if (!payload) {
    res.status(401).json({ error: 'Token inválido ou expirado' })
    return
  }

  req.professionalId = payload.sub
  next()
}
