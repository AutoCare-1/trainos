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
      <main className="max-w-5xl mx-auto w-full px-4 py-8 flex-1">
        <h1 className="text-xl font-bold text-slate-900 mb-6">Novo treino</h1>

        {erro && <p className="text-sm text-red-600 mb-4">{erro}</p>}

        {studentId && (
          <div className="grid md:grid-cols-2 gap-6">
            <div>
              <label className="block text-sm font-medium text-slate-700 mb-1">Nome do treino</label>
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm mb-6 focus:outline-none focus:ring-2 focus:ring-indigo-500"
              />

              <h2 className="font-semibold text-slate-900 mb-3">Biblioteca de exercícios</h2>
              <div className="space-y-4 max-h-[28rem] overflow-y-auto pr-2">
                {Object.entries(porGrupo).map(([grupo, exs]) => (
                  <div key={grupo}>
                    <p className="text-xs font-semibold uppercase text-slate-400 mb-2">{grupo}</p>
                    <div className="grid gap-2">
                      {exs.map((ex) => (
                        <button
                          key={ex.id}
                          type="button"
                          onClick={() => adicionarExercicio(ex.id)}
                          disabled={items.some((i) => i.exercise_id === ex.id)}
                          className="text-left rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm hover:border-indigo-300 disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                          {ex.name}
                        </button>
                      ))}
                    </div>
                  </div>
                ))}
              </div>
            </div>

            <div>
              <h2 className="font-semibold text-slate-900 mb-3">Exercícios selecionados ({items.length})</h2>
              {items.length === 0 && (
                <div className="rounded-xl border border-dashed border-slate-300 p-6 text-center text-slate-500 text-sm">
                  Clique nos exercícios ao lado para adicionar
                </div>
              )}
              <div className="space-y-3">
                {items.map((item) => {
                  const ex = exercises.find((e) => e.id === item.exercise_id)
                  return (
                    <div key={item.exercise_id} className="rounded-xl border border-slate-200 bg-white p-4">
                      <div className="flex items-center justify-between mb-3">
                        <p className="font-medium text-slate-900">{ex?.name}</p>
                        <button
                          type="button"
                          onClick={() => removerExercicio(item.exercise_id)}
                          className="text-xs text-red-600 hover:underline"
                        >
                          Remover
                        </button>
                      </div>
                      <div className="grid grid-cols-3 gap-2">
                        <div>
                          <label className="block text-xs text-slate-500 mb-1">Séries</label>
                          <input
                            type="number"
                            min={1}
                            value={item.sets}
                            onChange={(e) => atualizarItem(item.exercise_id, 'sets', e.target.value)}
                            className="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                          />
                        </div>
                        <div>
                          <label className="block text-xs text-slate-500 mb-1">Reps</label>
                          <input
                            type="text"
                            value={item.reps}
                            onChange={(e) => atualizarItem(item.exercise_id, 'reps', e.target.value)}
                            className="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
                          />
                        </div>
                        <div>
                          <label className="block text-xs text-slate-500 mb-1">Carga (kg)</label>
                          <input
                            type="number"
                            min={0}
                            value={item.load_kg ?? ''}
                            onChange={(e) => atualizarItem(item.exercise_id, 'load_kg', e.target.value)}
                            className="w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm"
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
                className="mt-6 w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
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
