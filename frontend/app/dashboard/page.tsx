'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import { api, ApiError } from '@/lib/api'
import { Student } from '@/lib/types'

export default function DashboardPage() {
  const router = useRouter()
  const [students, setStudents] = useState<Student[] | null>(null)
  const [erro, setErro] = useState<string | null>(null)

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    api
      .get<{ students: Student[] }>('/alunos')
      .then((data) => setStudents(data.students))
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar alunos'))
  }, [router])

  return (
    <>
      <Navbar />
      <main className="max-w-5xl mx-auto w-full px-4 py-8 flex-1">
        <div className="flex items-center justify-between mb-6">
          <h1 className="text-xl font-bold text-slate-900">Meus alunos</h1>
          <Link
            href="/alunos/novo"
            className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
          >
            + Cadastrar aluno
          </Link>
        </div>

        {erro && <p className="text-sm text-red-600 mb-4">{erro}</p>}

        {students === null && !erro && <p className="text-slate-500">Carregando...</p>}

        {students?.length === 0 && (
          <div className="rounded-xl border border-dashed border-slate-300 p-8 text-center text-slate-500">
            Nenhum aluno cadastrado ainda. Comece cadastrando o primeiro.
          </div>
        )}

        <div className="grid gap-3">
          {students?.map((s) => (
            <Link
              key={s.id}
              href={`/alunos/${s.id}`}
              className="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-5 py-4 hover:border-indigo-300 hover:shadow-sm transition"
            >
              <div>
                <p className="font-semibold text-slate-900">{s.name}</p>
                <p className="text-sm text-slate-500">{s.objective || 'Sem objetivo definido'}</p>
              </div>
              <div className="text-right text-sm text-slate-500">
                <p>{s.ultimo_treino ?? 'Sem treino ainda'}</p>
                <p>
                  {s.sessoes_concluidas ?? 0} {(s.sessoes_concluidas ?? 0) === 1 ? 'sessão concluída' : 'sessões concluídas'}
                </p>
              </div>
            </Link>
          ))}
        </div>
      </main>
    </>
  )
}
