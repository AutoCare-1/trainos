'use client'

import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import BackLink from '@/components/BackLink'
import Leaderboard from '@/components/Leaderboard'
import { api, ApiError } from '@/lib/api'
import { Challenge } from '@/lib/types'

function formatarData(iso: string): string {
  const d = iso.includes('T') ? new Date(iso) : new Date(iso + 'T00:00:00')
  return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' })
}

export default function DesafioDetalheClient({ challengeId }: { challengeId: string }) {
  const router = useRouter()
  const [challenge, setChallenge] = useState<Challenge | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [removendo, setRemovendo] = useState(false)

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    api
      .get<{ challenge: Challenge; leaderboard: Challenge['leaderboard'] }>(`/desafios/${challengeId}`)
      .then((data) => setChallenge({ ...data.challenge, leaderboard: data.leaderboard }))
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar desafio'))
  }, [challengeId, router])

  async function remover() {
    setRemovendo(true)
    try {
      await api.delete(`/desafios/${challengeId}`)
      router.push('/desafios')
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao remover desafio')
      setRemovendo(false)
    }
  }

  if (erro) {
    return (
      <>
        <Navbar />
        <main className="mx-auto w-full max-w-lg flex-1 px-4 py-8">
          <p className="text-sm text-rose-500">{erro}</p>
        </main>
      </>
    )
  }

  if (!challenge) {
    return (
      <>
        <Navbar />
        <main className="mx-auto w-full max-w-lg flex-1 px-4 py-8">
          <p className="text-slate-500">Carregando...</p>
        </main>
      </>
    )
  }

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-lg flex-1 px-4 py-8">
        <BackLink href="/desafios" label="Voltar aos desafios" />

        <div className="mb-6 flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-slate-900">{challenge.name} 🏆</h1>
            <p className="mt-0.5 text-sm text-slate-500">
              {formatarData(challenge.start_date)} – {formatarData(challenge.end_date)}
            </p>
            {challenge.description && <p className="mt-2 text-sm text-slate-600">{challenge.description}</p>}
          </div>
          <button
            onClick={remover}
            disabled={removendo}
            className="shrink-0 text-xs text-rose-500 transition hover:text-rose-600"
          >
            {removendo ? 'Removendo...' : 'Remover'}
          </button>
        </div>

        <section className="glass rounded-2xl p-5">
          <h2 className="mb-3 font-semibold text-slate-900">Quadro de destaques</h2>
          <Leaderboard entries={challenge.leaderboard ?? []} />
        </section>
      </main>
    </>
  )
}
