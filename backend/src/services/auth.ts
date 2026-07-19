import bcrypt from 'bcryptjs'
import jwt from 'jsonwebtoken'

const JWT_SECRET: string = (() => {
  const value = process.env.JWT_SECRET
  if (!value) throw new Error('JWT_SECRET não configurada no .env')
  return value
})()

export async function hashPassword(senha: string): Promise<string> {
  return bcrypt.hash(senha, 10)
}

export async function verificarSenha(senha: string, hash: string): Promise<boolean> {
  return bcrypt.compare(senha, hash)
}

export function gerarToken(professionalId: string): string {
  return jwt.sign({ sub: professionalId }, JWT_SECRET, { expiresIn: '7d' })
}

export function verificarToken(token: string): { sub: string } | null {
  try {
    return jwt.verify(token, JWT_SECRET) as { sub: string }
  } catch {
    return null
  }
}
