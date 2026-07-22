'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import Avatar from '@/components/Avatar'
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

  const totalSessoes = students?.reduce((acc, s) => acc + Number(s.sessoes_concluidas ?? 0), 0) ?? 0

  function diasSemTreinar(s: Student): number | null {
    if (!s.ultima_sessao_em) return null
    return Math.floor((Date.now() - new Date(s.ultima_sessao_em).getTime()) / 86400000)
  }

  function inativo(s: Student): boolean {
    if (!s.tem_treino_enviado) return false
    const dias = diasSemTreinar(s)
    return dias === null || dias > 7
  }

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8">
        <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-slate-900">Meus alunos</h1>
            <p className="mt-1 text-sm text-slate-500">Acompanhe treinos, execuções e conversas</p>
          </div>
          <Link href="/alunos/novo" className="btn-primary rounded-xl px-5 py-2.5 text-sm">
            + Cadastrar aluno
          </Link>
        </div>

        {students && students.length > 0 && (
          <div className="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div className="glass rounded-2xl p-4">
              <p className="text-xs font-medium uppercase tracking-wider text-slate-500">Alunos ativos</p>
              <p className="mt-1 text-2xl font-bold text-slate-900">{students.length}</p>
            </div>
            <div className="glass rounded-2xl p-4">
              <p className="text-xs font-medium uppercase tracking-wider text-slate-500">Sessões concluídas</p>
              <p className="mt-1 text-2xl font-bold bg-gradient-to-r from-[#2648b3] to-[#8b7fd6] bg-clip-text text-transparent">
                {totalSessoes}
              </p>
            </div>
            <div className="glass hidden rounded-2xl p-4 sm:block">
              <p className="text-xs font-medium uppercase tracking-wider text-slate-500">Com treino ativo</p>
              <p className="mt-1 text-2xl font-bold text-slate-900">
                {students.filter((s) => s.ultimo_treino).length}
              </p>
            </div>
          </div>
        )}

        {erro && <p className="mb-4 text-sm text-rose-400">{erro}</p>}
        {students === null && !erro && <p className="text-slate-500">Carregando...</p>}

        {students?.length === 0 && (
          <div className="glass rounded-2xl border-dashed p-10 text-center">
            <p className="text-slate-500">Nenhum aluno cadastrado ainda.</p>
            <p className="mt-1 text-sm text-slate-400">Comece cadastrando o primeiro — leva menos de um minuto.</p>
          </div>
        )}

        <div className="grid gap-3">
          {students?.map((s) => (
            <Link
              key={s.id}
              href={`/alunos/${s.id}`}
              className="glass glass-hover flex items-center gap-4 rounded-2xl px-5 py-4"
            >
              <Avatar nome={s.name} fotoUrl={s.photo_url} />
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                  <p className="truncate font-semibold text-slate-900">{s.name}</p>
                  {inativo(s) && (
                    <span className="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700">
                      {diasSemTreinar(s) === null ? 'Nunca treinou' : `Sem treinar há ${diasSemTreinar(s)}d`}
                    </span>
                  )}
                  {(s.exercicios_sem_progresso ?? 0) > 0 && (
                    <span
                      className="shrink-0 rounded-full bg-orange-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-orange-700"
                      title="Sem aumento de carga entre as duas últimas sessões nesses exercícios"
                    >
                      {s.exercicios_sem_progresso} sem progresso
                    </span>
                  )}
                </div>
                <p className="truncate text-sm text-slate-500">{s.objective || 'Sem objetivo definido'}</p>
              </div>
              <div className="hidden text-right sm:block">
                <p className="text-sm text-slate-600">{s.ultimo_treino ?? 'Sem treino'}</p>
                <p className="text-xs text-slate-500">
                  {Number(s.sessoes_concluidas ?? 0)}{' '}
                  {Number(s.sessoes_concluidas ?? 0) === 1 ? 'sessão concluída' : 'sessões concluídas'}
                </p>
              </div>
              <span className="text-slate-600">→</span>
            </Link>
          ))}
        </div>
      </main>
    </>
  )
}
