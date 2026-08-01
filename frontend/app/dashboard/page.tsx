'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { ChevronRight, UserPlus } from 'lucide-react'
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
  // Captura "agora" uma única vez por mount — useState com inicializador
  // lazy é a forma pura de fazer isso (ao contrário de chamar Date.now() direto
  // no corpo do render, que quebra a regra de pureza do React).
  const [agora] = useState(() => Date.now())

  function diasSemTreinar(s: Student): number | null {
    if (!s.ultima_sessao_em) return null
    return Math.floor((agora - new Date(s.ultima_sessao_em).getTime()) / 86400000)
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
            <h1 className="font-display text-2xl font-bold tracking-tight text-ink">Meus alunos</h1>
            <span className="title-accent" />
            <p className="mt-2 text-sm text-ink-muted">Acompanhe treinos, execuções e conversas</p>
          </div>
          <Link href="/alunos/novo" className="btn-cta flex items-center gap-1.5 rounded-xl px-5 py-2.5 text-sm">
            <UserPlus size={16} />
            Cadastrar aluno
          </Link>
        </div>

        {students && students.length > 0 && (
          <div className="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div className="glass rounded-2xl p-4">
              <p className="text-xs font-medium uppercase tracking-wider text-ink-muted">Alunos ativos</p>
              <p className="stat-number mt-1 text-2xl font-bold text-ink">{students.length}</p>
            </div>
            <div className="stat-hero rounded-2xl p-4">
              <p className="stat-hero-label text-xs font-medium uppercase tracking-wider">Sessões concluídas</p>
              <p className="stat-number mt-1 text-2xl font-bold text-white">{totalSessoes}</p>
            </div>
            <div className="glass hidden rounded-2xl p-4 sm:block">
              <p className="text-xs font-medium uppercase tracking-wider text-ink-muted">Com treino ativo</p>
              <p className="stat-number mt-1 text-2xl font-bold text-ink">
                {students.filter((s) => s.ultimo_treino).length}
              </p>
            </div>
          </div>
        )}

        {erro && <p className="mb-4 text-sm text-danger">{erro}</p>}
        {students === null && !erro && <p className="text-ink-muted">Carregando...</p>}

        {students?.length === 0 && (
          <div className="glass-flat rounded-2xl border-dashed p-10 text-center">
            <span className="icon-chip icon-chip-coral mx-auto mb-3 h-12 w-12 [&>svg]:h-6 [&>svg]:w-6">
              <UserPlus />
            </span>
            <p className="font-semibold text-ink-soft">Nenhum aluno cadastrado ainda.</p>
            <p className="mt-1 text-sm text-ink-muted">Comece cadastrando o primeiro — leva menos de um minuto.</p>
            <Link href="/alunos/novo" className="btn-cta mt-4 inline-block rounded-xl px-5 py-2.5 text-sm">
              Cadastrar meu primeiro aluno
            </Link>
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
                  <p className="truncate font-semibold text-ink">{s.name}</p>
                  {inativo(s) && (
                    <span className="shrink-0 rounded-full bg-warning-soft px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-warning">
                      {diasSemTreinar(s) === null ? 'Nunca treinou' : `Sem treinar há ${diasSemTreinar(s)}d`}
                    </span>
                  )}
                  {(s.exercicios_sem_progresso ?? 0) > 0 && (
                    <span
                      className="shrink-0 rounded-full bg-warning-soft px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-warning"
                      title="Sem aumento de carga entre as duas últimas sessões nesses exercícios"
                    >
                      {s.exercicios_sem_progresso} sem progresso
                    </span>
                  )}
                </div>
                <p className="truncate text-sm text-ink-muted">{s.objective || 'Sem objetivo definido'}</p>
              </div>
              <div className="hidden text-right sm:block">
                <p className="text-sm text-ink-soft">{s.ultimo_treino ?? 'Sem treino'}</p>
                <p className="text-xs text-ink-muted">
                  {Number(s.sessoes_concluidas ?? 0)}{' '}
                  {Number(s.sessoes_concluidas ?? 0) === 1 ? 'sessão concluída' : 'sessões concluídas'}
                </p>
              </div>
              <ChevronRight size={18} className="shrink-0 text-ink-muted" />
            </Link>
          ))}
        </div>
      </main>
    </>
  )
}
