'use client'

import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import BackLink from '@/components/BackLink'
import ExerciseAnimation from '@/components/ExerciseAnimation'
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
        <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
          <p className="text-sm text-rose-400">{erro}</p>
        </main>
      </>
    )
  }

  if (!workout) {
    return (
      <>
        <Navbar />
        <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
          <p className="text-slate-500">Carregando...</p>
        </main>
      </>
    )
  }

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
        <BackLink href={`/alunos/${workout.student_id}`} label="Voltar ao aluno" />

        <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <h1 className="text-2xl font-bold tracking-tight text-slate-900">{workout.name}</h1>
            <span
              className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                workout.status === 'sent' ? 'bg-emerald-500/15 text-emerald-600' : 'bg-slate-900/6 text-slate-500'
              }`}
            >
              {workout.status === 'sent' ? 'Enviado ao aluno' : 'Rascunho'}
            </span>
          </div>
          {workout.status === 'draft' && (
            <button
              onClick={enviarTreino}
              disabled={enviando}
              className="btn-primary rounded-xl px-5 py-2.5 text-sm"
            >
              {enviando ? 'Enviando...' : 'Enviar ao aluno'}
            </button>
          )}
        </div>

        <div className="space-y-3">
          {exercises.map((ex, idx) => (
            <div key={ex.id} className="glass rounded-2xl p-5">
              <div className="flex items-start gap-4">
                <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-[#2648b3]/15 to-[#8b7fd6]/15 text-sm font-bold text-[#2648b3]">
                  {idx + 1}
                </span>
                <div className="min-w-[110px] flex-1">
                  <p className="text-xs uppercase tracking-wider text-slate-500">{ex.muscle_group}</p>
                  <p className="font-semibold text-slate-900">{ex.exercise_name}</p>
                  <div className="mt-2 flex flex-wrap gap-2">
                    <span className="rounded-lg bg-slate-900/5 px-2.5 py-1 text-xs text-slate-600">
                      {ex.sets} séries
                    </span>
                    <span className="rounded-lg bg-slate-900/5 px-2.5 py-1 text-xs text-slate-600">
                      {ex.reps} reps
                    </span>
                    {ex.load_kg && (
                      <span className="rounded-lg bg-[#2648b3]/10 px-2.5 py-1 text-xs text-[#2648b3]">
                        {ex.load_kg} kg
                      </span>
                    )}
                  </div>
                  {ex.instructions && <p className="mt-3 text-sm text-slate-500">{ex.instructions}</p>}
                </div>
                <div className="glass shrink-0 rounded-xl p-1.5 text-[#2648b3]">
                  <ExerciseAnimation
                    name={ex.exercise_name}
                    muscleGroup={ex.muscle_group}
                    imageUrl={ex.image_url}
                    videoUrl={ex.video_url}
                    imageCredit={ex.image_credit}
                    size="md"
                    className="rounded-lg"
                  />
                </div>
              </div>
            </div>
          ))}
        </div>
      </main>
    </>
  )
}
