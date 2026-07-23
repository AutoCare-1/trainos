'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import BackLink from '@/components/BackLink'
import Avatar from '@/components/Avatar'
import { api, ApiError } from '@/lib/api'
import { GymMediaSubmission } from '@/lib/types'

const STATUS_ESTILO: Record<string, string> = {
  pending: 'bg-amber-500/15 text-amber-600',
  approved: 'bg-emerald-500/15 text-emerald-600',
  rejected: 'bg-slate-900/6 text-slate-500',
}

const STATUS_LABEL: Record<string, string> = {
  pending: 'Aguardando revisão',
  approved: 'Aprovado',
  rejected: 'Rejeitado',
}

const TIPO_LABEL: Record<string, string> = {
  photo: 'Foto',
  video: 'Vídeo',
  album: 'Álbum de fotos',
}

export default function AcademiaListaPage() {
  const router = useRouter()
  const [submissions, setSubmissions] = useState<GymMediaSubmission[] | null>(null)
  const [erro, setErro] = useState<string | null>(null)

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    api
      .get<{ submissions: GymMediaSubmission[] }>('/academia')
      .then((data) => setSubmissions(data.submissions))
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar análises'))
  }, [router])

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
        <BackLink href="/dashboard" label="Voltar ao painel" />
        <div className="mb-6">
          <h1 className="mb-1 text-2xl font-bold tracking-tight text-slate-900">Análises de academia</h1>
          <p className="text-sm text-slate-500">
            Fotos e vídeos que os alunos enviaram da própria academia, com máquinas detectadas e treino
            sugerido pela IA — revise e aprove antes de virar treino de verdade.
          </p>
        </div>

        {erro && <p className="mb-4 text-sm text-rose-500">{erro}</p>}
        {submissions === null && !erro && <p className="text-slate-500">Carregando...</p>}

        {submissions?.length === 0 && (
          <div className="glass rounded-2xl border-dashed p-10 text-center">
            <p className="text-slate-500">Nenhuma análise enviada ainda.</p>
            <p className="mt-1 text-sm text-slate-400">Assim que um aluno enviar fotos ou vídeo da academia, aparece aqui.</p>
          </div>
        )}

        <div className="space-y-3">
          {submissions?.map((s) => (
            <Link
              key={s.id}
              href={`/academia/${s.id}`}
              className="glass glass-hover flex items-center gap-4 rounded-2xl p-4"
            >
              <Avatar nome={s.student_name ?? '?'} fotoUrl={s.student_photo_url} />
              <div className="min-w-0 flex-1">
                <p className="truncate font-semibold text-slate-900">{s.student_name}</p>
                <p className="truncate text-sm text-slate-500">
                  {TIPO_LABEL[s.submission_type]}
                  {s.recommendation_name ? ` · ${s.recommendation_name}` : ''}
                </p>
              </div>
              <span
                className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ${
                  s.status !== 'completed'
                    ? 'bg-slate-900/6 text-slate-500'
                    : STATUS_ESTILO[s.approval_status ?? 'pending']
                }`}
              >
                {s.status === 'analyzing' ? 'Analisando...' : s.status === 'failed' ? 'Falhou' : STATUS_LABEL[s.approval_status ?? 'pending']}
              </span>
            </Link>
          ))}
        </div>
      </main>
    </>
  )
}
