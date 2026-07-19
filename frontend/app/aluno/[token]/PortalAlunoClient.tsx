'use client'

import { useEffect, useState } from 'react'
import { api, ApiError } from '@/lib/api'
import { Workout, WorkoutExerciseDetail } from '@/lib/types'

interface PortalData {
  student: { id: string; name: string; objective: string | null }
  workout: Workout | null
  exercises: WorkoutExerciseDetail[]
  activeSessionId: string | null
}

export default function PortalAlunoClient({ token }: { token: string }) {
  const [data, setData] = useState<PortalData | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [sessionId, setSessionId] = useState<string | null>(null)
  const [registrados, setRegistrados] = useState<Record<string, number>>({})
  const [inputs, setInputs] = useState<Record<string, { reps: string; load: string }>>({})
  const [treinoConcluido, setTreinoConcluido] = useState(false)
  const [enviandoFeedback, setEnviandoFeedback] = useState(false)
  const [rpe, setRpe] = useState(5)
  const [satisfacao, setSatisfacao] = useState(5)
  const [desconforto, setDesconforto] = useState('')
  const [comentario, setComentario] = useState('')

  useEffect(() => {
    api
      .get<PortalData>(`/portal/${token}`)
      .then((d) => {
        setData(d)
        setSessionId(d.activeSessionId)
        const initialInputs: Record<string, { reps: string; load: string }> = {}
        d.exercises.forEach((ex) => {
          initialInputs[ex.id] = { reps: ex.reps, load: ex.load_kg ?? '' }
        })
        setInputs(initialInputs)
      })
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Não foi possível carregar seu treino'))
  }, [token])

  async function iniciarTreino() {
    if (!data?.workout) return
    try {
      const { session } = await api.post<{ session: { id: string } }>(`/portal/${token}/sessoes`, {
        workout_id: data.workout.id,
      })
      setSessionId(session.id)
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao iniciar treino')
    }
  }

  async function registrarSerie(ex: WorkoutExerciseDetail) {
    if (!sessionId) return
    const jaFeitas = registrados[ex.id] ?? 0
    if (jaFeitas >= ex.sets) return

    const valores = inputs[ex.id] ?? { reps: ex.reps, load: ex.load_kg ?? '' }
    try {
      await api.post(`/portal/${token}/sessoes/${sessionId}/registros`, {
        workout_exercise_id: ex.id,
        set_number: jaFeitas + 1,
        reps_done: Number(valores.reps) || null,
        load_kg_done: valores.load ? Number(valores.load) : null,
      })
      setRegistrados({ ...registrados, [ex.id]: jaFeitas + 1 })
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao registrar série')
    }
  }

  async function concluirTreino() {
    if (!sessionId) return
    setEnviandoFeedback(true)
    try {
      await api.post(`/portal/${token}/sessoes/${sessionId}/concluir`, {
        effort_rpe: rpe,
        satisfaction: satisfacao,
        discomfort: desconforto,
        comment: comentario,
      })
      setTreinoConcluido(true)
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao concluir treino')
    } finally {
      setEnviandoFeedback(false)
    }
  }

  if (erro) {
    return (
      <main className="flex flex-1 items-center justify-center px-4">
        <p className="text-red-600 text-center">{erro}</p>
      </main>
    )
  }

  if (!data) {
    return (
      <main className="flex flex-1 items-center justify-center px-4">
        <p className="text-slate-500">Carregando...</p>
      </main>
    )
  }

  if (treinoConcluido) {
    return (
      <main className="flex flex-1 items-center justify-center px-4">
        <div className="text-center max-w-sm">
          <h1 className="text-2xl font-bold text-slate-900 mb-2">Treino concluído! 💪</h1>
          <p className="text-slate-500">Bom trabalho, {data.student.name.split(' ')[0]}! Seu professor já pode ver seu progresso.</p>
        </div>
      </main>
    )
  }

  if (!data.workout) {
    return (
      <main className="flex flex-1 items-center justify-center px-4">
        <div className="text-center max-w-sm">
          <h1 className="text-xl font-bold text-slate-900 mb-2">Olá, {data.student.name.split(' ')[0]}!</h1>
          <p className="text-slate-500">Você ainda não tem nenhum treino disponível. Assim que seu professor enviar, ele aparece aqui.</p>
        </div>
      </main>
    )
  }

  const todasSeriesFeitas = data.exercises.every((ex) => (registrados[ex.id] ?? 0) >= ex.sets)

  return (
    <main className="max-w-lg mx-auto w-full px-4 py-8 flex-1">
      <div className="mb-6">
        <p className="text-sm text-slate-500">Olá, {data.student.name.split(' ')[0]}</p>
        <h1 className="text-xl font-bold text-slate-900">{data.workout.name}</h1>
      </div>

      {!sessionId && (
        <button
          onClick={iniciarTreino}
          className="w-full rounded-lg bg-indigo-600 px-4 py-3 text-base font-semibold text-white hover:bg-indigo-500 mb-6"
        >
          Iniciar treino
        </button>
      )}

      <div className="space-y-4">
        {data.exercises.map((ex, idx) => {
          const feitas = registrados[ex.id] ?? 0
          const completo = feitas >= ex.sets
          return (
            <div
              key={ex.id}
              className={`rounded-xl border p-4 ${completo ? 'border-green-200 bg-green-50' : 'border-slate-200 bg-white'}`}
            >
              <p className="text-xs text-slate-400 mb-1">
                Exercício {idx + 1} — {ex.muscle_group}
              </p>
              <p className="font-semibold text-slate-900 mb-1">{ex.exercise_name}</p>
              <p className="text-sm text-slate-600 mb-3">
                Alvo: {ex.sets} séries × {ex.reps} reps{ex.load_kg ? ` — ${ex.load_kg}kg` : ''}
              </p>

              {sessionId && !completo && (
                <div className="flex items-end gap-2">
                  <div>
                    <label className="block text-xs text-slate-500 mb-1">Reps feitas</label>
                    <input
                      type="number"
                      value={inputs[ex.id]?.reps ?? ''}
                      onChange={(e) => setInputs({ ...inputs, [ex.id]: { ...inputs[ex.id], reps: e.target.value } })}
                      className="w-20 rounded-lg border border-slate-300 px-2 py-2 text-sm"
                    />
                  </div>
                  <div>
                    <label className="block text-xs text-slate-500 mb-1">Carga (kg)</label>
                    <input
                      type="number"
                      value={inputs[ex.id]?.load ?? ''}
                      onChange={(e) => setInputs({ ...inputs, [ex.id]: { ...inputs[ex.id], load: e.target.value } })}
                      className="w-20 rounded-lg border border-slate-300 px-2 py-2 text-sm"
                    />
                  </div>
                  <button
                    onClick={() => registrarSerie(ex)}
                    className="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-700"
                  >
                    Registrar série {feitas + 1}/{ex.sets}
                  </button>
                </div>
              )}

              {sessionId && (
                <p className="text-xs text-slate-400 mt-2">
                  {feitas}/{ex.sets} séries registradas
                </p>
              )}
            </div>
          )
        })}
      </div>

      {sessionId && (
        <div className="mt-8 rounded-xl border border-slate-200 bg-white p-4">
          <h2 className="font-semibold text-slate-900 mb-3">
            {todasSeriesFeitas ? 'Como foi o treino?' : 'Finalizar antes de terminar todas as séries?'}
          </h2>
          <div className="space-y-3">
            <div>
              <label className="block text-xs text-slate-500 mb-1">Esforço percebido (RPE 0-10)</label>
              <input
                type="range"
                min={0}
                max={10}
                value={rpe}
                onChange={(e) => setRpe(Number(e.target.value))}
                className="w-full"
              />
              <p className="text-sm text-slate-600">{rpe}</p>
            </div>
            <div>
              <label className="block text-xs text-slate-500 mb-1">Satisfação (1-5)</label>
              <input
                type="range"
                min={1}
                max={5}
                value={satisfacao}
                onChange={(e) => setSatisfacao(Number(e.target.value))}
                className="w-full"
              />
              <p className="text-sm text-slate-600">{satisfacao}</p>
            </div>
            <div>
              <label className="block text-xs text-slate-500 mb-1">Sentiu algum desconforto?</label>
              <input
                type="text"
                value={desconforto}
                onChange={(e) => setDesconforto(e.target.value)}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
              />
            </div>
            <div>
              <label className="block text-xs text-slate-500 mb-1">Comentário (opcional)</label>
              <textarea
                value={comentario}
                onChange={(e) => setComentario(e.target.value)}
                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                rows={2}
              />
            </div>
            <button
              onClick={concluirTreino}
              disabled={enviandoFeedback}
              className="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-60"
            >
              {enviandoFeedback ? 'Enviando...' : 'Concluir treino'}
            </button>
          </div>
        </div>
      )}
    </main>
  )
}
