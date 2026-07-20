'use client'

import { useEffect, useState } from 'react'
import { useRouter, useSearchParams } from 'next/navigation'
import Navbar from '@/components/Navbar'
import { api, ApiError } from '@/lib/api'
import { Exercise, Workout } from '@/lib/types'

interface ItemTreino {
  exercise_id: string
  sets: number
  reps: string
  load_kg?: number
}

export default function NovoTreinoClient() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const studentId = searchParams.get('aluno')

  const [exercises, setExercises] = useState<Exercise[]>([])
  const [name, setName] = useState('Treino A')
  const [items, setItems] = useState<ItemTreino[]>([])
  const [erro, setErro] = useState<string | null>(null)
  const [salvando, setSalvando] = useState(false)

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    if (!studentId) {
      setErro('Nenhum aluno selecionado. Volte ao perfil do aluno e clique em "Novo treino".')
      return
    }
    api
      .get<{ exercises: Exercise[] }>('/exercicios')
      .then((data) => setExercises(data.exercises))
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar exercícios'))
  }, [studentId, router])

  function adicionarExercicio(exerciseId: string) {
    if (items.some((i) => i.exercise_id === exerciseId)) return
    setItems([...items, { exercise_id: exerciseId, sets: 3, reps: '10-12' }])
  }

  function removerExercicio(exerciseId: string) {
    setItems(items.filter((i) => i.exercise_id !== exerciseId))
  }

  function atualizarItem(exerciseId: string, campo: keyof ItemTreino, valor: string) {
    setItems(
      items.map((i) => {
        if (i.exercise_id !== exerciseId) return i
        if (campo === 'sets') return { ...i, sets: Number(valor) || 0 }
        if (campo === 'load_kg') return { ...i, load_kg: valor ? Number(valor) : undefined }
        return { ...i, reps: valor }
      })
    )
  }

  async function salvarEEnviar() {
    if (!studentId) return
    setErro(null)
    if (!name.trim() || items.length === 0) {
      setErro('Dê um nome ao treino e adicione pelo menos um exercício.')
      return
    }
    setSalvando(true)
    try {
      const { workout } = await api.post<{ workout: Workout }>('/treinos', { student_id: studentId, name, items })
      await api.post(`/treinos/${workout.id}/enviar`)
      router.push(`/treinos/${workout.id}`)
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao salvar treino')
    } finally {
      setSalvando(false)
    }
  }

  const porGrupo = exercises.reduce<Record<string, Exercise[]>>((acc, ex) => {
    acc[ex.muscle_group] = acc[ex.muscle_group] ?? []
    acc[ex.muscle_group].push(ex)
    return acc
  }, {})

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8">
        <h1 className="mb-1 text-2xl font-bold tracking-tight text-white">Novo treino</h1>
        <p className="mb-6 text-sm text-slate-400">Monte a prescrição em poucos cliques e envie direto pro aluno.</p>

        {erro && <p className="mb-4 text-sm text-rose-400">{erro}</p>}

        {studentId && (
          <div className="grid gap-6 md:grid-cols-2">
            <div>
              <label className="mb-1.5 block text-sm font-medium text-slate-300">Nome do treino</label>
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="input-dark mb-6 w-full rounded-xl px-4 py-2.5 text-sm"
              />

              <h2 className="mb-3 font-semibold text-white">Biblioteca de exercícios</h2>
              <div className="chat-scroll max-h-[28rem] space-y-4 overflow-y-auto pr-2">
                {Object.entries(porGrupo).map(([grupo, exs]) => (
                  <div key={grupo}>
                    <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500">{grupo}</p>
                    <div className="grid gap-2">
                      {exs.map((ex) => {
                        const selecionado = items.some((i) => i.exercise_id === ex.id)
                        return (
                          <button
                            key={ex.id}
                            type="button"
                            onClick={() => adicionarExercicio(ex.id)}
                            disabled={selecionado}
                            className={`glass rounded-xl px-4 py-2.5 text-left text-sm transition ${
                              selecionado
                                ? 'opacity-35'
                                : 'glass-hover text-slate-100'
                            }`}
                          >
                            {ex.name}
                            {selecionado && <span className="float-right text-emerald-400">✓</span>}
                          </button>
                        )
                      })}
                    </div>
                  </div>
                ))}
              </div>
            </div>

            <div>
              <h2 className="mb-3 font-semibold text-white">
                Exercícios selecionados{' '}
                <span className="ml-1 rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs text-emerald-300">
                  {items.length}
                </span>
              </h2>
              {items.length === 0 && (
                <div className="glass rounded-2xl border-dashed p-8 text-center text-sm text-slate-500">
                  Clique nos exercícios ao lado para adicionar
                </div>
              )}
              <div className="space-y-3">
                {items.map((item) => {
                  const ex = exercises.find((e) => e.id === item.exercise_id)
                  return (
                    <div key={item.exercise_id} className="glass rounded-2xl p-4">
                      <div className="mb-3 flex items-center justify-between">
                        <p className="font-medium text-white">{ex?.name}</p>
                        <button
                          type="button"
                          onClick={() => removerExercicio(item.exercise_id)}
                          className="text-xs text-rose-400 transition hover:text-rose-300"
                        >
                          Remover
                        </button>
                      </div>
                      <div className="grid grid-cols-3 gap-2">
                        <div>
                          <label className="mb-1 block text-xs text-slate-500">Séries</label>
                          <input
                            type="number"
                            min={1}
                            value={item.sets}
                            onChange={(e) => atualizarItem(item.exercise_id, 'sets', e.target.value)}
                            className="input-dark w-full rounded-lg px-2.5 py-2 text-sm"
                          />
                        </div>
                        <div>
                          <label className="mb-1 block text-xs text-slate-500">Reps</label>
                          <input
                            type="text"
                            value={item.reps}
                            onChange={(e) => atualizarItem(item.exercise_id, 'reps', e.target.value)}
                            className="input-dark w-full rounded-lg px-2.5 py-2 text-sm"
                          />
                        </div>
                        <div>
                          <label className="mb-1 block text-xs text-slate-500">Carga (kg)</label>
                          <input
                            type="number"
                            min={0}
                            value={item.load_kg ?? ''}
                            onChange={(e) => atualizarItem(item.exercise_id, 'load_kg', e.target.value)}
                            className="input-dark w-full rounded-lg px-2.5 py-2 text-sm"
                          />
                        </div>
                      </div>
                    </div>
                  )
                })}
              </div>

              <button
                type="button"
                onClick={salvarEEnviar}
                disabled={salvando || items.length === 0}
                className="btn-primary mt-6 w-full rounded-xl px-4 py-3 text-sm"
              >
                {salvando ? 'Enviando...' : 'Salvar e enviar ao aluno'}
              </button>
            </div>
          </div>
        )}
      </main>
    </>
  )
}
