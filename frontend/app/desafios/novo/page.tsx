'use client'

import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import BackLink from '@/components/BackLink'
import Avatar from '@/components/Avatar'
import { api, ApiError } from '@/lib/api'
import { Challenge, Student } from '@/lib/types'

function daquiA(dias: number): string {
  const d = new Date()
  d.setDate(d.getDate() + dias)
  return d.toISOString().slice(0, 10)
}

export default function NovoDesafioPage() {
  const router = useRouter()
  const [students, setStudents] = useState<Student[]>([])
  const [name, setName] = useState('')
  const [description, setDescription] = useState('')
  const [startDate, setStartDate] = useState(daquiA(0))
  const [endDate, setEndDate] = useState(daquiA(30))
  const [selecionados, setSelecionados] = useState<Set<string>>(new Set())
  const [erro, setErro] = useState<string | null>(null)
  const [salvando, setSalvando] = useState(false)

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    api
      .get<{ students: Student[] }>('/alunos')
      .then((data) => setStudents(data.students))
      .catch(() => {})
  }, [router])

  function alternar(id: string) {
    setSelecionados((prev) => {
      const novo = new Set(prev)
      if (novo.has(id)) novo.delete(id)
      else novo.add(id)
      return novo
    })
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (selecionados.size === 0) {
      setErro('Selecione pelo menos um aluno')
      return
    }
    setErro(null)
    setSalvando(true)
    try {
      const { challenge } = await api.post<{ challenge: Challenge }>('/desafios', {
        name,
        description,
        start_date: startDate,
        end_date: endDate,
        student_ids: Array.from(selecionados),
      })
      router.push(`/desafios/${challenge.id}`)
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao criar desafio')
    } finally {
      setSalvando(false)
    }
  }

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-lg flex-1 px-4 py-10">
        <BackLink href="/desafios" label="Voltar aos desafios" />
        <h1 className="mb-1 text-2xl font-bold tracking-tight text-slate-900">Novo desafio</h1>
        <p className="mb-6 text-sm text-slate-500">
          Defina o prazo e escolha quem participa. A pontuação conta treinos concluídos no período.
        </p>

        <form onSubmit={handleSubmit} className="glass space-y-4 rounded-2xl p-6">
          <div>
            <label className="mb-1.5 block text-sm font-medium text-slate-600">Nome do desafio</label>
            <input
              type="text"
              required
              placeholder="Ex: Desafio de Julho"
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
            />
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-slate-600">Descrição (opcional)</label>
            <textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              rows={2}
              className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
            />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="mb-1.5 block text-sm font-medium text-slate-600">Início</label>
              <input
                type="date"
                required
                value={startDate}
                onChange={(e) => setStartDate(e.target.value)}
                className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
              />
            </div>
            <div>
              <label className="mb-1.5 block text-sm font-medium text-slate-600">Fim</label>
              <input
                type="date"
                required
                value={endDate}
                onChange={(e) => setEndDate(e.target.value)}
                className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
              />
            </div>
          </div>

          <div>
            <label className="mb-1.5 block text-sm font-medium text-slate-600">
              Participantes ({selecionados.size} selecionado{selecionados.size === 1 ? '' : 's'})
            </label>
            <div className="max-h-64 space-y-1 overflow-y-auto rounded-xl border border-black/8 p-2">
              {students.map((s) => (
                <label
                  key={s.id}
                  className="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-2 hover:bg-slate-900/3"
                >
                  <input
                    type="checkbox"
                    checked={selecionados.has(s.id)}
                    onChange={() => alternar(s.id)}
                    className="h-4 w-4 accent-[#2648b3]"
                  />
                  <Avatar nome={s.name} fotoUrl={s.photo_url} tamanho="sm" />
                  <span className="text-sm text-slate-800">{s.name}</span>
                </label>
              ))}
              {students.length === 0 && <p className="px-2 py-2 text-sm text-slate-400">Nenhum aluno cadastrado ainda.</p>}
            </div>
          </div>

          {erro && <p className="text-sm text-rose-500">{erro}</p>}

          <button type="submit" disabled={salvando} className="btn-primary w-full rounded-xl px-4 py-3 text-sm">
            {salvando ? 'Criando...' : 'Criar desafio'}
          </button>
        </form>
      </main>
    </>
  )
}
