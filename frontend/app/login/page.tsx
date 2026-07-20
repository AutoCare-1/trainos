'use client'

import { useState } from 'react'
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
      router.push('/dashboard')
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao conectar com o servidor')
    } finally {
      setCarregando(false)
    }
  }

  return (
    <div className="flex flex-1 items-center justify-center px-4 py-10">
      <div className="w-full max-w-sm">
        <div className="mb-8 flex flex-col items-center text-center">
          <span className="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-emerald-400 to-cyan-500 text-2xl font-black text-[#04110d] shadow-lg shadow-emerald-500/25">
            T
          </span>
          <h1 className="text-3xl font-bold tracking-tight text-white">
            Train<span className="bg-gradient-to-r from-emerald-400 to-cyan-400 bg-clip-text text-transparent">OS</span>
          </h1>
          <p className="mt-2 text-sm text-slate-400">
            {modo === 'login'
              ? 'Entre para gerenciar seus alunos e treinos'
              : 'Crie sua conta de profissional'}
          </p>
        </div>

        <form onSubmit={handleSubmit} className="glass space-y-4 rounded-2xl p-6">
          {modo === 'signup' && (
            <div>
              <label className="mb-1.5 block text-sm font-medium text-slate-300">Nome</label>
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
            <label className="mb-1.5 block text-sm font-medium text-slate-300">E-mail</label>
            <input
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
            />
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-slate-300">Senha</label>
            <input
              type="password"
              required
              minLength={6}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
            />
          </div>

          {erro && <p className="text-sm text-rose-400">{erro}</p>}

          <button type="submit" disabled={carregando} className="btn-primary w-full rounded-xl px-4 py-3 text-sm">
            {carregando ? 'Aguarde...' : modo === 'login' ? 'Entrar' : 'Criar conta'}
          </button>
        </form>

        <button
          type="button"
          onClick={() => setModo(modo === 'login' ? 'signup' : 'login')}
          className="mt-5 w-full text-center text-sm text-slate-400 transition hover:text-emerald-300"
        >
          {modo === 'login' ? (
            <>Ainda não tem conta? <span className="font-semibold text-emerald-400">Criar conta</span></>
          ) : (
            <>Já tem conta? <span className="font-semibold text-emerald-400">Entrar</span></>
          )}
        </button>
      </div>
    </div>
  )
}
