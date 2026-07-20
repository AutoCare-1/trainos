'use client'

import { useState } from 'react'
import Link from 'next/link'
import Navbar from '@/components/Navbar'
import { api, ApiError } from '@/lib/api'
import { Student } from '@/lib/types'

export default function NovoAlunoPage() {
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [phone, setPhone] = useState('')
  const [objective, setObjective] = useState('')
  const [erro, setErro] = useState<string | null>(null)
  const [carregando, setCarregando] = useState(false)
  const [criado, setCriado] = useState<Student | null>(null)
  const [copiado, setCopiado] = useState(false)

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setErro(null)
    setCarregando(true)
    try {
      const data = await api.post<{ student: Student }>('/alunos', { name, email, phone, objective })
      setCriado(data.student)
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao cadastrar aluno')
    } finally {
      setCarregando(false)
    }
  }

  if (criado) {
    const link = `${window.location.origin}/aluno/${criado.invite_token}`
    return (
      <>
        <Navbar />
        <main className="mx-auto w-full max-w-lg flex-1 px-4 py-10">
          <div className="glass rounded-2xl p-7">
            <span className="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/15 text-2xl">
              ✓
            </span>
            <h1 className="text-xl font-bold text-white">Aluno cadastrado!</h1>
            <p className="mt-2 text-sm text-slate-400">
              Envie este link para <strong className="text-white">{criado.name}</strong> acessar o portal, ver os
              treinos e conversar com você:
            </p>
            <div className="mt-4 flex items-center gap-2">
              <code className="input-dark flex-1 truncate rounded-xl px-4 py-3 text-sm text-emerald-300">
                {link}
              </code>
              <button
                onClick={() => {
                  navigator.clipboard.writeText(link)
                  setCopiado(true)
                  setTimeout(() => setCopiado(false), 2000)
                }}
                className="glass glass-hover rounded-xl px-4 py-3 text-sm text-slate-200"
              >
                {copiado ? 'Copiado ✓' : 'Copiar'}
              </button>
            </div>
            <div className="mt-6 flex gap-3">
              <Link href={`/treinos/novo?aluno=${criado.id}`} className="btn-primary rounded-xl px-5 py-2.5 text-sm">
                Criar treino agora
              </Link>
              <Link
                href="/dashboard"
                className="rounded-xl px-5 py-2.5 text-sm font-medium text-slate-400 transition hover:text-white"
              >
                Voltar ao painel
              </Link>
            </div>
          </div>
        </main>
      </>
    )
  }

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-lg flex-1 px-4 py-10">
        <h1 className="mb-1 text-2xl font-bold tracking-tight text-white">Cadastrar aluno</h1>
        <p className="mb-6 text-sm text-slate-400">O aluno recebe um link de acesso — sem senha, sem fricção.</p>

        <form onSubmit={handleSubmit} className="glass space-y-4 rounded-2xl p-6">
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
          <div>
            <label className="mb-1.5 block text-sm font-medium text-slate-300">E-mail (opcional)</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
            />
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-slate-300">Telefone (opcional)</label>
            <input
              type="text"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
            />
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-slate-300">Objetivo</label>
            <input
              type="text"
              placeholder="Ex: hipertrofia, emagrecimento, condicionamento"
              value={objective}
              onChange={(e) => setObjective(e.target.value)}
              className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
            />
          </div>

          {erro && <p className="text-sm text-rose-400">{erro}</p>}

          <button type="submit" disabled={carregando} className="btn-primary w-full rounded-xl px-4 py-3 text-sm">
            {carregando ? 'Salvando...' : 'Cadastrar aluno'}
          </button>
        </form>
      </main>
    </>
  )
}
