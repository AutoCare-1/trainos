'use client'

import { useState } from 'react'
import Image from 'next/image'
import { useRouter } from 'next/navigation'
import { api, ApiError, setToken } from '@/lib/api'
import { Professional } from '@/lib/types'

export default function LoginPage() {
  const router = useRouter()
  const [modo, setModo] = useState<'login' | 'signup'>('login')
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [erro, setErro] = useState<string | null>(null)
  const [carregando, setCarregando] = useState(false)

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setErro(null)
    setCarregando(true)
    try {
      const path = modo === 'login' ? '/auth/login' : '/auth/signup'
      const body = modo === 'login' ? { email, password } : { name, email, password }
      const data = await api.post<{ token: string; professional: Professional }>(path, body)
      setToken(data.token)
      router.push('/negocio')
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao conectar com o servidor')
    } finally {
      setCarregando(false)
    }
  }

  return (
    <div className="hero-gradient flex flex-1 items-center justify-center px-4 py-10">
      <div className="w-full max-w-sm">
        <div className="mb-8 flex flex-col items-center text-center">
          <Image
            src="/clubemais-logo-white.png"
            alt="Clube Mais"
            width={240}
            height={67}
            priority
            className="h-16 w-auto"
          />
          <span className="mt-3 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-white">
            Personal
          </span>
          <p className="mt-4 text-sm text-white/75">
            {modo === 'login'
              ? 'Entre para gerenciar seus alunos e treinos'
              : 'Crie sua conta de profissional'}
          </p>
        </div>

        <form onSubmit={handleSubmit} className="glass-elevated space-y-4 rounded-2xl p-6">
          {modo === 'signup' && (
            <div>
              <label className="mb-1.5 block text-sm font-medium text-ink-soft">Nome</label>
              <input
                type="text"
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
              />
            </div>
          )}
          <div>
            <label className="mb-1.5 block text-sm font-medium text-ink-soft">E-mail</label>
            <input
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
            />
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-ink-soft">Senha</label>
            <input
              type="password"
              required
              minLength={6}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
            />
          </div>

          {erro && <p className="text-sm text-danger">{erro}</p>}

          <button
            type="submit"
            disabled={carregando}
            className={`w-full rounded-xl px-4 py-3 text-sm ${modo === 'signup' ? 'btn-cta' : 'btn-primary'}`}
          >
            {carregando ? 'Aguarde...' : modo === 'login' ? 'Entrar' : 'Criar conta'}
          </button>
        </form>

        <button
          type="button"
          onClick={() => setModo(modo === 'login' ? 'signup' : 'login')}
          className="mt-5 w-full text-center text-sm text-white/70 transition hover:text-white"
        >
          {modo === 'login' ? (
            <>Ainda não tem conta? <span className="font-semibold text-white">Criar conta</span></>
          ) : (
            <>Já tem conta? <span className="font-semibold text-white">Entrar</span></>
          )}
        </button>
      </div>
    </div>
  )
}
