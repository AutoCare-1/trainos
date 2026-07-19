'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import { api, ApiError } from '@/lib/api'
import { Student, Workout } from '@/lib/types'

export default function AlunoDetalheClient({ studentId }: { studentId: string }) {
  const router = useRouter()
  const [student, setStudent] = useState<Student | null>(null)
  const [workouts, setWorkouts] = useState<Workout[]>([])
  const [erro, setErro] = useState<string | null>(null)

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    api
      .get<{ student: Student; workouts: Workout[] }>(`/alunos/${studentId}`)
      .then((data) => {
        setStudent(data.student)
        setWorkouts(data.workouts)
      })
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar aluno'))
  }, [studentId, router])

  if (erro) {
    return (
      <>
        <Navbar />
        <main className="max-w-3xl mx-auto w-full px-4 py-8 flex-1">
          <p className="text-sm text-red-600">{erro}</p>
        </main>
      </>
    )
  }

  if (!student) {
    return (
      <>
        <Navbar />
        <main className="max-w-3xl mx-auto w-full px-4 py-8 flex-1">
          <p className="text-slate-500">Carregando...</p>
        </main>
      </>
    )
  }

  const inviteLink = typeof window !== 'undefined' ? `${window.location.origin}/aluno/${student.invite_token}` : ''

  return (
    <>
      <Navbar />
      <main className="max-w-3xl mx-auto w-full px-4 py-8 flex-1">
        <div className="mb-6">
          <h1 className="text-xl font-bold text-slate-900">{student.name}</h1>
          <p className="text-slate-500">{student.objective || 'Sem objetivo definido'}</p>
        </div>

        <div className="rounded-xl border border-slate-200 bg-white p-4 mb-6">
          <p className="text-sm text-slate-600 mb-2">Link do portal do aluno:</p>
          <code className="block break-all rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-sm text-indigo-700">
            {inviteLink}
          </code>
        </div>

        <div className="flex items-center justify-between mb-4">
          <h2 className="font-semibold text-slate-900">Treinos</h2>
          <Link
            href={`/treinos/novo?aluno=${student.id}`}
            className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
          >
            + Novo treino
          </Link>
        </div>

        {workouts.length === 0 && (
          <div className="rounded-xl border border-dashed border-slate-300 p-8 text-center text-slate-500">
            Nenhum treino criado ainda.
          </div>
        )}

        <div className="grid gap-3">
          {workouts.map((w) => (
            <Link
              key={w.id}
              href={`/treinos/${w.id}`}
              className="flex items-center justify-between rounded-xl border border-slate-200 bg-white px-5 py-4 hover:border-indigo-300 hover:shadow-sm transition"
            >
              <p className="font-semibold text-slate-900">{w.name}</p>
              <span
                className={`text-xs font-medium px-2 py-1 rounded-full ${
                  w.status === 'sent' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600'
                }`}
              >
                {w.status === 'sent' ? 'Enviado' : 'Rascunho'}
              </span>
            </Link>
          ))}
        </div>
      </main>
    </>
  )
}
