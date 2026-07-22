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
          <Image src="/clubemais-logo.png" alt="Clube Mais" width={240} height={67} priority className="h-16 w-auto" />
          <span className="mt-2 rounded-full bg-[#2648b3]/8 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-[#2648b3]">
            Personal
          </span>
          <p className="mt-4 text-sm text-slate-500">
            {modo === 'login'
              ? 'Entre para gerenciar seus alunos e treinos'
              : 'Crie sua conta de profissional'}
          </p>
        </div>

        <form onSubmit={handleSubmit} className="glass space-y-4 rounded-2xl p-6">
          {modo === 'signup' && (
            <div>
              <label className="mb-1.5 block text-sm font-medium text-slate-600">Nome</label>
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
            <label className="mb-1.5 block text-sm font-medium text-slate-600">E-mail</label>
            <input
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
            />
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-slate-600">Senha</label>
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
          className="mt-5 w-full text-center text-sm text-slate-500 transition hover:text-[#2648b3]"
        >
          {modo === 'login' ? (
            <>Ainda não tem conta? <span className="font-semibold text-[#2648b3]">Criar conta</span></>
          ) : (
            <>Já tem conta? <span className="font-semibold text-[#2648b3]">Entrar</span></>
          )}
        </button>
      </div>
    </div>
  )
}
