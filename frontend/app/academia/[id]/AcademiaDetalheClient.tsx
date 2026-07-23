'use client'

import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import BackLink from '@/components/BackLink'
import Avatar from '@/components/Avatar'
import { api, ApiError, resolveMediaUrl } from '@/lib/api'
import { GymAnalysisResult, GymMediaAsset, GymWorkoutRecommendation, RecommendedItem } from '@/lib/types'

interface Detalhe {
  submission: {
    id: string
    student_name: string
    student_photo_url: string | null
    submission_type: 'photo' | 'video' | 'album'
    status: 'analyzing' | 'completed' | 'failed'
    error_message: string | null
    created_at: string
  }
  assets: GymMediaAsset[]
  analysis: GymAnalysisResult | null
  recommendation: GymWorkoutRecommendation | null
}

export default function AcademiaDetalheClient({ submissionId }: { submissionId: string }) {
  const router = useRouter()
  const [detalhe, setDetalhe] = useState<Detalhe | null>(null)
  const [itens, setItens] = useState<RecommendedItem[]>([])
  const [erro, setErro] = useState<string | null>(null)
  const [processando, setProcessando] = useState(false)

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    api
      .get<Detalhe>(`/academia/${submissionId}`)
      .then((d) => {
        setDetalhe(d)
        setItens(d.recommendation?.recommended_items ?? [])
      })
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar análise'))
  }, [router, submissionId])

  function atualizarItem(idx: number, campo: keyof RecommendedItem, valor: string | number) {
    setItens((prev) => prev.map((item, i) => (i === idx ? { ...item, [campo]: valor } : item)))
  }

  function removerItem(idx: number) {
    setItens((prev) => prev.filter((_, i) => i !== idx))
  }

  async function aprovar() {
    if (itens.length === 0) {
      setErro('Deixe ao menos um exercício pra aprovar')
      return
    }
    setProcessando(true)
    setErro(null)
    try {
      await api.patch(`/academia/${submissionId}/aprovar`, {
        items: itens.map((i) => ({
          exercise_id: i.exercise_id,
          sets: Number(i.sets),
          reps: i.reps,
          rest_seconds: Number(i.rest_seconds) || undefined,
          notes: i.notes,
        })),
      })
      router.push('/academia')
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao aprovar treino')
    } finally {
      setProcessando(false)
    }
  }

  async function rejeitar() {
    setProcessando(true)
    setErro(null)
    try {
      await api.patch(`/academia/${submissionId}/rejeitar`, {})
      router.push('/academia')
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao rejeitar')
    } finally {
      setProcessando(false)
    }
  }

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
        <BackLink href="/academia" label="Voltar às análises" />

        {erro && <p className="mb-4 text-sm text-rose-500">{erro}</p>}
        {!detalhe && !erro && <p className="text-slate-500">Carregando...</p>}

        {detalhe && (
          <>
            <div className="mb-6 flex items-center gap-4">
              <Avatar nome={detalhe.submission.student_name} fotoUrl={detalhe.submission.student_photo_url} tamanho="lg" />
              <div>
                <h1 className="text-xl font-bold text-slate-900">{detalhe.submission.student_name}</h1>
                <p className="text-sm text-slate-500">
                  {new Date(detalhe.submission.created_at).toLocaleDateString('pt-BR', {
                    day: '2-digit',
                    month: 'long',
                    year: 'numeric',
                  })}
                </p>
              </div>
            </div>

            {detalhe.submission.status === 'analyzing' && (
              <div className="glass rounded-2xl p-6 text-center text-slate-500">Ainda analisando essa mídia...</div>
            )}
            {detalhe.submission.status === 'failed' && (
              <div className="glass rounded-2xl border border-rose-200 bg-rose-50 p-6 text-center text-rose-600">
                Falha ao analisar: {detalhe.submission.error_message}
              </div>
            )}

            {detalhe.assets.length > 0 && (
              <div className="glass mb-4 rounded-2xl p-5">
                <h2 className="mb-3 font-semibold text-slate-900">Mídia enviada</h2>
                <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
                  {detalhe.assets.map((a) => (
                    // eslint-disable-next-line @next/next/no-img-element -- foto/frame vem do backend
                    <img
                      key={a.id}
                      src={resolveMediaUrl(a.file_path)}
                      alt="Foto da academia"
                      className="aspect-square w-full rounded-xl object-cover"
                    />
                  ))}
                </div>
              </div>
            )}

            {detalhe.analysis && (
              <div className="glass mb-4 rounded-2xl p-5">
                <h2 className="mb-1 font-semibold text-slate-900">
                  Máquinas detectadas ({detalhe.analysis.total_unique_machines})
                </h2>
                {detalhe.analysis.zones_identified.length > 0 && (
                  <p className="mb-3 text-sm text-slate-500">Zonas: {detalhe.analysis.zones_identified.join(', ')}</p>
                )}
                <div className="space-y-1.5">
                  {detalhe.analysis.machines_json.machines.map((m, i) => (
                    <div key={i} className="rounded-xl bg-slate-900/3 px-3 py-2 text-sm">
                      <span className="font-medium text-slate-800">{m.name}</span>
                      <span className="text-slate-500"> — {m.primary_muscles.join(', ')}</span>
                    </div>
                  ))}
                </div>
                {detalhe.analysis.gaps.length > 0 && (
                  <div className="mt-3 rounded-xl border-l-4 border-amber-400 bg-amber-50 px-3 py-2 text-sm text-amber-700">
                    {detalhe.analysis.gaps.join(' · ')}
                  </div>
                )}
              </div>
            )}

            {detalhe.recommendation && (
              <div className="glass rounded-2xl p-5">
                <h2 className="mb-1 font-semibold text-slate-900">{detalhe.recommendation.name}</h2>
                {detalhe.recommendation.reasoning && (
                  <p className="mb-4 text-sm text-slate-500">{detalhe.recommendation.reasoning}</p>
                )}

                {detalhe.recommendation.approval_status !== 'pending' && (
                  <p className="mb-4 text-sm font-medium text-slate-600">
                    Status:{' '}
                    {detalhe.recommendation.approval_status === 'approved' ? 'Aprovado' : 'Rejeitado'}
                  </p>
                )}

                <div className="space-y-2">
                  {itens.map((item, idx) => (
                    <div key={item.exercise_id} className="flex flex-wrap items-center gap-2 rounded-xl bg-slate-900/3 p-3">
                      <span className="min-w-[10rem] flex-1 text-sm font-medium text-slate-800">{item.exercise_name}</span>
                      <input
                        type="number"
                        value={item.sets}
                        onChange={(e) => atualizarItem(idx, 'sets', Number(e.target.value))}
                        disabled={detalhe.recommendation!.approval_status !== 'pending'}
                        className="w-16 rounded-lg border border-slate-200 px-2 py-1 text-sm"
                        title="Séries"
                      />
                      <input
                        type="text"
                        value={item.reps}
                        onChange={(e) => atualizarItem(idx, 'reps', e.target.value)}
                        disabled={detalhe.recommendation!.approval_status !== 'pending'}
                        className="w-20 rounded-lg border border-slate-200 px-2 py-1 text-sm"
                        title="Repetições"
                      />
                      <input
                        type="number"
                        value={item.rest_seconds}
                        onChange={(e) => atualizarItem(idx, 'rest_seconds', Number(e.target.value))}
                        disabled={detalhe.recommendation!.approval_status !== 'pending'}
                        className="w-20 rounded-lg border border-slate-200 px-2 py-1 text-sm"
                        title="Descanso (s)"
                      />
                      {detalhe.recommendation!.approval_status === 'pending' && (
                        <button
                          onClick={() => removerItem(idx)}
                          className="rounded-lg px-2 py-1 text-xs font-medium text-rose-500 hover:bg-rose-50"
                        >
                          Remover
                        </button>
                      )}
                    </div>
                  ))}
                </div>

                {detalhe.recommendation.approval_status === 'pending' && (
                  <div className="mt-5 flex gap-3">
                    <button
                      onClick={aprovar}
                      disabled={processando}
                      className="btn-primary flex-1 rounded-xl px-4 py-3 text-sm disabled:opacity-50"
                    >
                      {processando ? 'Aprovando...' : '✓ Aprovar treino'}
                    </button>
                    <button
                      onClick={rejeitar}
                      disabled={processando}
                      className="glass glass-hover flex-1 rounded-xl px-4 py-3 text-sm font-medium text-rose-600 disabled:opacity-50"
                    >
                      Rejeitar
                    </button>
                  </div>
                )}
              </div>
            )}
          </>
        )}
      </main>
    </>
  )
}
