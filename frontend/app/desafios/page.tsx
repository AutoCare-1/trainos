'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { Trophy } from 'lucide-react'
import Navbar from '@/components/Navbar'
import BackLink from '@/components/BackLink'
import { api, ApiError } from '@/lib/api'
import { Challenge } from '@/lib/types'

const STATUS_ESTILO: Record<string, string> = {
  ativo: 'bg-success/15 text-success',
  agendado: 'bg-warning/15 text-warning',
  encerrado: 'bg-ink/6 text-ink-muted',
}

const STATUS_LABEL: Record<string, string> = {
  ativo: 'Em andamento',
  agendado: 'Agendado',
  encerrado: 'Encerrado',
}

function paraData(iso: string): Date {
  return iso.includes('T') ? new Date(iso) : new Date(iso + 'T00:00:00')
}

function formatarPeriodo(inicio: string, fim: string): string {
  const opts: Intl.DateTimeFormatOptions = { day: '2-digit', month: '2-digit', timeZone: 'UTC' }
  return `${paraData(inicio).toLocaleDateString('pt-BR', opts)} – ${paraData(fim).toLocaleDateString('pt-BR', opts)}`
}

export default function DesafiosPage() {
  const router = useRouter()
  const [challenges, setChallenges] = useState<Challenge[] | null>(null)
  const [erro, setErro] = useState<string | null>(null)

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    api
      .get<{ challenges: Challenge[] }>('/desafios')
      .then((data) => setChallenges(data.challenges))
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar desafios'))
  }, [router])

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
        <BackLink href="/dashboard" label="Voltar ao painel" />
        <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
          <div>
            <h1 className="mb-1 font-display text-2xl font-bold tracking-tight text-ink">Desafios</h1>
            <p className="text-sm text-ink-muted">
              Crie um desafio com prazo — pontos vêm de treinos concluídos, não de carga, então todo mundo compete de
              igual pra igual.
            </p>
          </div>
          <Link href="/desafios/novo" className="btn-primary shrink-0 rounded-xl px-5 py-2.5 text-sm">
            + Novo desafio
          </Link>
        </div>

        {erro && <p className="mb-4 text-sm text-danger">{erro}</p>}
        {challenges === null && !erro && <p className="text-ink-muted">Carregando...</p>}

        {challenges?.length === 0 && (
          <div className="glass rounded-2xl border-dashed p-10 text-center">
            <span className="icon-chip icon-chip-coral mx-auto mb-3 h-12 w-12 [&>svg]:h-6 [&>svg]:w-6">
              <Trophy />
            </span>
            <p className="font-semibold text-ink-soft">Nenhum desafio criado ainda.</p>
            <p className="mt-1 text-sm text-ink-muted">Crie um e convide seus alunos pra participar.</p>
            <Link href="/desafios/novo" className="btn-cta mt-4 inline-block rounded-xl px-5 py-2.5 text-sm">
              Criar meu primeiro desafio
            </Link>
          </div>
        )}

        <div className="space-y-3">
          {challenges?.map((c) => (
            <Link
              key={c.id}
              href={`/desafios/${c.id}`}
              className="glass glass-hover flex items-center justify-between gap-3 rounded-2xl p-5"
            >
              <div className="min-w-0">
                <p className="font-semibold text-ink">{c.name}</p>
                <p className="text-sm text-ink-muted">
                  {formatarPeriodo(c.start_date, c.end_date)} · {c.total_participantes ?? 0} aluno
                  {c.total_participantes === 1 ? '' : 's'}
                </p>
              </div>
              <span className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ${STATUS_ESTILO[c.status ?? 'ativo']}`}>
                {STATUS_LABEL[c.status ?? 'ativo']}
              </span>
            </Link>
          ))}
        </div>
      </main>
    </>
  )
}
