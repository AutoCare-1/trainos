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
        <main className="max-w-lg mx-auto w-full px-4 py-8 flex-1">
          <div className="rounded-xl border border-green-200 bg-green-50 p-6">
            <h1 className="text-lg font-bold text-slate-900 mb-2">Aluno cadastrado!</h1>
            <p className="text-sm text-slate-600 mb-4">
              Envie este link para <strong>{criado.name}</strong> acessar o portal e ver os treinos:
            </p>
            <code className="block break-all rounded-lg bg-white border border-slate-200 px-3 py-2 text-sm text-indigo-700 mb-4">
              {link}
            </code>
            <div className="flex gap-3">
              <Link
                href={`/treinos/novo?aluno=${criado.id}`}
                className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
              >
                Criar treino agora
              </Link>
              <Link href="/dashboard" className="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 hover:text-slate-900">
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
      <main className="max-w-lg mx-auto w-full px-4 py-8 flex-1">
        <h1 className="text-xl font-bold text-slate-900 mb-6">Cadastrar aluno</h1>
        <form onSubmit={handleSubmit} className="space-y-4 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">Nome</label>
            <input
              type="text"
              required
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">E-mail (opcional)</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">Telefone (opcional)</label>
            <input
              type="text"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1">Objetivo</label>
            <input
              type="text"
              placeholder="Ex: hipertrofia, emagrecimento, condicionamento"
              value={objective}
              onChange={(e) => setObjective(e.target.value)}
              className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
            />
          </div>

          {erro && <p className="text-sm text-red-600">{erro}</p>}

          <button
            type="submit"
            disabled={carregando}
            className="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
          >
            {carregando ? 'Salvando...' : 'Cadastrar'}
          </button>
        </form>
      </main>
    </>
  )
}
