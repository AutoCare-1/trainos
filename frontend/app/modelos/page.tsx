'use client'

import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import BackLink from '@/components/BackLink'
import ExerciseAnimation from '@/components/ExerciseAnimation'
import { api, ApiError } from '@/lib/api'
import { WorkoutTemplate, WorkoutTemplateExerciseDetail } from '@/lib/types'

export default function ModelosPage() {
  const router = useRouter()
  const [templates, setTemplates] = useState<WorkoutTemplate[] | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [aberto, setAberto] = useState<string | null>(null)
  const [exercicios, setExercicios] = useState<Record<string, WorkoutTemplateExerciseDetail[]>>({})
  const [removendo, setRemovendo] = useState<string | null>(null)

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    api
      .get<{ templates: WorkoutTemplate[] }>('/modelos')
      .then((data) => setTemplates(data.templates))
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar modelos'))
  }, [router])

  async function alternarAberto(id: string) {
    if (aberto === id) {
      setAberto(null)
      return
    }
    setAberto(id)
    if (!exercicios[id]) {
      try {
        const data = await api.get<{ exercises: WorkoutTemplateExerciseDetail[] }>(`/modelos/${id}`)
        setExercicios((prev) => ({ ...prev, [id]: data.exercises }))
      } catch {
        // silencioso — o card só não expande os detalhes
      }
    }
  }

  async function remover(id: string) {
    setRemovendo(id)
    try {
      await api.delete(`/modelos/${id}`)
      setTemplates((prev) => prev?.filter((t) => t.id !== id) ?? null)
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao remover modelo')
    } finally {
      setRemovendo(null)
    }
  }

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
        <BackLink href="/dashboard" label="Voltar ao painel" />
        <h1 className="mb-1 text-2xl font-bold tracking-tight text-slate-900">Modelos de treino</h1>
        <p className="mb-6 text-sm text-slate-500">
          Salve um treino como modelo na tela &quot;Novo treino&quot; pra reaproveitar com outros alunos.
        </p>

        {erro && <p className="mb-4 text-sm text-rose-500">{erro}</p>}
        {templates === null && !erro && <p className="text-slate-500">Carregando...</p>}

        {templates?.length === 0 && (
          <div className="glass rounded-2xl border-dashed p-10 text-center">
            <p className="text-slate-500">Nenhum modelo salvo ainda.</p>
            <p className="mt-1 text-sm text-slate-400">
              Monte um treino, clique em &quot;Salvar como modelo&quot; e ele aparece aqui.
            </p>
          </div>
        )}

        <div className="space-y-3">
          {templates?.map((t) => (
            <div key={t.id} className="glass rounded-2xl p-5">
              <div className="flex items-center justify-between gap-3">
                <button
                  onClick={() => alternarAberto(t.id)}
                  className="flex flex-1 items-center justify-between gap-3 text-left"
                >
                  <div>
                    <p className="font-semibold text-slate-900">{t.name}</p>
                    <p className="text-sm text-slate-500">{t.total_exercicios ?? 0} exercícios</p>
                  </div>
                  <span className="text-slate-400">{aberto === t.id ? '▲' : '▼'}</span>
                </button>
                <button
                  onClick={() => remover(t.id)}
                  disabled={removendo === t.id}
                  className="shrink-0 text-xs text-rose-500 transition hover:text-rose-600"
                >
                  {removendo === t.id ? 'Removendo...' : 'Remover'}
                </button>
              </div>

              {aberto === t.id && (
                <div className="mt-4 space-y-2 border-t border-black/6 pt-4">
                  {!exercicios[t.id] && <p className="text-sm text-slate-400">Carregando exercícios...</p>}
                  {exercicios[t.id]?.map((ex) => (
                    <div key={ex.id} className="flex items-center gap-3 rounded-xl bg-slate-900/3 px-3 py-2">
                      <ExerciseAnimation
                        name={ex.exercise_name}
                        muscleGroup={ex.muscle_group}
                        imageUrl={ex.image_url}
                        imageCredit={ex.image_credit}
                        size="sm"
                        className="shrink-0 rounded-lg text-[#2648b3]"
                      />
                      <div className="min-w-0 flex-1">
                        <p className="text-sm font-medium text-slate-800">{ex.exercise_name}</p>
                        <p className="text-xs text-slate-500">
                          {ex.sets} séries · {ex.reps} reps{ex.load_kg ? ` · ${ex.load_kg}kg` : ''}
                        </p>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          ))}
        </div>
      </main>
    </>
  )
}
