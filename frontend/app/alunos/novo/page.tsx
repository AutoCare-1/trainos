'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import BackLink from '@/components/BackLink'
import { api, ApiError } from '@/lib/api'
import { Student } from '@/lib/types'

export default function NovoAlunoPage() {
  const router = useRouter()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [phone, setPhone] = useState('')
  const [weight, setWeight] = useState('')
  const [height, setHeight] = useState('')
  const [objective, setObjective] = useState('')
  const [erro, setErro] = useState<string | null>(null)
  const [carregando, setCarregando] = useState(false)
  const [criado, setCriado] = useState<Student | null>(null)
  const [copiado, setCopiado] = useState(false)

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
    }
  }, [router])

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setErro(null)
    setCarregando(true)
    try {
      const data = await api.post<{ student: Student }>('/alunos', {
        name,
        email,
        phone,
        objective,
        weight_kg: weight ? Number(weight) : undefined,
        height_cm: height ? Number(height) : undefined,
      })
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
            <h1 className="text-xl font-bold text-slate-900">Aluno cadastrado!</h1>
            <p className="mt-2 text-sm text-slate-500">
              Envie este link para <strong className="text-slate-900">{criado.name}</strong> acessar o portal, ver os
              treinos e conversar com você:
            </p>
            <div className="mt-4 flex items-center gap-2">
              <code className="input-dark flex-1 truncate rounded-xl px-4 py-3 text-sm text-[#2648b3]">
                {link}
              </code>
              <button
                onClick={() => {
                  navigator.clipboard.writeText(link)
                  setCopiado(true)
                  setTimeout(() => setCopiado(false), 2000)
                }}
                className="glass glass-hover rounded-xl px-4 py-3 text-sm text-slate-700"
              >
                {copiado ? 'Copiado ✓' : 'Copiar'}
              </button>
            </div>
            <a
              href={`https://wa.me/?text=${encodeURIComponent(
                `Oi, ${criado.name.split(' ')[0]}! Aqui está seu acesso ao Clube Mais: ${link}`
              )}`}
              target="_blank"
              rel="noopener noreferrer"
              className="mt-3 inline-flex items-center gap-1.5 rounded-xl bg-[#25D366] px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90"
            >
              Enviar por WhatsApp
            </a>
            <div className="mt-6 flex gap-3">
              <Link href={`/treinos/novo?aluno=${criado.id}`} className="btn-primary rounded-xl px-5 py-2.5 text-sm">
                Criar treino agora
              </Link>
              <Link
                href="/dashboard"
                className="rounded-xl px-5 py-2.5 text-sm font-medium text-slate-500 transition hover:text-slate-900"
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
        <BackLink href="/dashboard" label="Voltar ao painel" />
        <h1 className="mb-1 text-2xl font-bold tracking-tight text-slate-900">Cadastrar aluno</h1>
        <p className="mb-6 text-sm text-slate-500">O aluno recebe um link de acesso — sem senha, sem fricção.</p>

        <form onSubmit={handleSubmit} className="glass space-y-4 rounded-2xl p-6">
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
          <div>
            <label className="mb-1.5 block text-sm font-medium text-slate-600">E-mail (opcional)</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
            />
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-slate-600">Telefone (opcional)</label>
            <input
              type="text"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="mb-1.5 block text-sm font-medium text-slate-600">Peso (kg)</label>
              <input
                type="number"
                min={0}
                step="0.1"
                inputMode="decimal"
                value={weight}
                onChange={(e) => setWeight(e.target.value)}
                className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
              />
            </div>
            <div>
              <label className="mb-1.5 block text-sm font-medium text-slate-600">Altura (cm)</label>
              <input
                type="number"
                min={0}
                step="1"
                inputMode="numeric"
                value={height}
                onChange={(e) => setHeight(e.target.value)}
                className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
              />
            </div>
          </div>

          <div>
            <label className="mb-1.5 block text-sm font-medium text-slate-600">Objetivo</label>
            <textarea
              placeholder="Ex: hipertrofia, emagrecimento, condicionamento. Vale contar mais: rotina, restrições, prazo de meta etc — isso ajuda a direcionar o treino."
              value={objective}
              onChange={(e) => setObjective(e.target.value)}
              rows={3}
              className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
            />
          </div>

          {erro && <p className="text-sm text-rose-500">{erro}</p>}

          <button type="submit" disabled={carregando} className="btn-primary w-full rounded-xl px-4 py-3 text-sm">
            {carregando ? 'Salvando...' : 'Cadastrar aluno'}
          </button>
        </form>
      </main>
    </>
  )
}
