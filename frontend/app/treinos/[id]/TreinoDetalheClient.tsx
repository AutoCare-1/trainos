'use client'

import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import { api, ApiError } from '@/lib/api'
import { Workout, WorkoutExerciseDetail } from '@/lib/types'

export default function TreinoDetalheClient({ workoutId }: { workoutId: string }) {
  const router = useRouter()
  const [workout, setWorkout] = useState<Workout | null>(null)
  const [exercises, setExercises] = useState<WorkoutExerciseDetail[]>([])
  const [erro, setErro] = useState<string | null>(null)
  const [enviando, setEnviando] = useState(false)

  function carregar() {
    api
      .get<{ workout: Workout; exercises: WorkoutExerciseDetail[] }>(`/treinos/${workoutId}`)
      .then((data) => {
        setWorkout(data.workout)
        setExercises(data.exercises)
      })
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar treino'))
  }

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    carregar()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [workoutId, router])

  async function enviarTreino() {
    setEnviando(true)
    try {
      await api.post(`/treinos/${workoutId}/enviar`)
      carregar()
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao enviar treino')
    } finally {
      setEnviando(false)
    }
  }

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

  if (!workout) {
    return (
      <>
        <Navbar />
        <main className="max-w-3xl mx-auto w-full px-4 py-8 flex-1">
          <p className="text-slate-500">Carregando...</p>
        </main>
      </>
    )
  }

  return (
    <>
      <Navbar />
      <main className="max-w-3xl mx-auto w-full px-4 py-8 flex-1">
        <div className="flex items-center justify-between mb-6">
          <div>
            <h1 className="text-xl font-bold text-slate-900">{workout.name}</h1>
            <span
              className={`text-xs font-medium px-2 py-1 rounded-full ${
                workout.status === 'sent' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600'
              }`}
            >
              {workout.status === 'sent' ? 'Enviado ao aluno' : 'Rascunho'}
            </span>
          </div>
          {workout.status === 'draft' && (
            <button
              onClick={enviarTreino}
              disabled={enviando}
              className="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
            >
              {enviando ? 'Enviando...' : 'Enviar ao aluno'}
            </button>
          )}
        </div>

        <div className="space-y-3">
          {exercises.map((ex, idx) => (
            <div key={ex.id} className="rounded-xl border border-slate-200 bg-white p-4">
              <p className="text-xs text-slate-400 mb-1">Exercício {idx + 1} — {ex.muscle_group}</p>
              <p className="font-semibold text-slate-900 mb-1">{ex.exercise_name}</p>
              <p className="text-sm text-slate-600">
                {ex.sets} séries × {ex.reps} reps{ex.load_kg ? ` — ${ex.load_kg}kg` : ''}
              </p>
              {ex.instructions && <p className="text-sm text-slate-400 mt-2">{ex.instructions}</p>}
            </div>
          ))}
        </div>
      </main>
    </>
  )
}
