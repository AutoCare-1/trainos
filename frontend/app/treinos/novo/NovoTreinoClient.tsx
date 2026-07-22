'use client'

import { useEffect, useState } from 'react'
import { useRouter, useSearchParams } from 'next/navigation'
import Navbar from '@/components/Navbar'
import BackLink from '@/components/BackLink'
import ExerciseAnimation from '@/components/ExerciseAnimation'
import { api, ApiError } from '@/lib/api'
import { Exercise, Workout, WorkoutTemplate, WorkoutTemplateExerciseDetail } from '@/lib/types'

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
  const [templates, setTemplates] = useState<WorkoutTemplate[]>([])
  const [carregandoModelo, setCarregandoModelo] = useState(false)
  const [salvandoModelo, setSalvandoModelo] = useState(false)
  const [modeloSalvo, setModeloSalvo] = useState(false)

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
    api
      .get<{ templates: WorkoutTemplate[] }>('/modelos')
      .then((data) => setTemplates(data.templates))
      .catch(() => {})
  }, [studentId, router])

  async function carregarModelo(templateId: string) {
    if (!templateId) return
    setCarregandoModelo(true)
    setErro(null)
    try {
      const data = await api.get<{ template: WorkoutTemplate; exercises: WorkoutTemplateExerciseDetail[] }>(
        `/modelos/${templateId}`
      )
      setName(data.template.name)
      setItems(
        data.exercises.map((ex) => ({
          exercise_id: ex.exercise_id,
          sets: ex.sets,
          reps: ex.reps,
          load_kg: ex.load_kg ? Number(ex.load_kg) : undefined,
        }))
      )
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao carregar modelo')
    } finally {
      setCarregandoModelo(false)
    }
  }

  async function salvarComoModelo() {
    if (!name.trim() || items.length === 0) {
      setErro('Dê um nome ao treino e adicione pelo menos um exercício antes de salvar como modelo.')
      return
    }
    setSalvandoModelo(true)
    setErro(null)
    try {
      const { template } = await api.post<{ template: WorkoutTemplate }>('/modelos', { name, items })
      setTemplates((prev) => [{ ...template, total_exercicios: items.length }, ...prev])
      setModeloSalvo(true)
      setTimeout(() => setModeloSalvo(false), 2500)
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao salvar modelo')
    } finally {
      setSalvandoModelo(false)
    }
  }

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
        <BackLink href={studentId ? `/alunos/${studentId}` : '/dashboard'} label="Voltar ao aluno" />
        <h1 className="mb-1 text-2xl font-bold tracking-tight text-slate-900">Novo treino</h1>
        <p className="mb-6 text-sm text-slate-500">Monte a prescrição em poucos cliques e envie direto pro aluno.</p>

        {erro && <p className="mb-4 text-sm text-rose-400">{erro}</p>}

        {studentId && (
          <div className="grid gap-6 md:grid-cols-2">
            <div>
              <label className="mb-1.5 block text-sm font-medium text-slate-600">Nome do treino</label>
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
              />

              {templates.length > 0 && (
                <div className="mt-3">
                  <label className="mb-1.5 block text-sm font-medium text-slate-600">Começar de um modelo</label>
                  <select
                    onChange={(e) => carregarModelo(e.target.value)}
                    disabled={carregandoModelo}
                    defaultValue=""
                    className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
                  >
                    <option value="">Selecione um modelo salvo...</option>
                    {templates.map((t) => (
                      <option key={t.id} value={t.id}>
                        {t.name} ({t.total_exercicios ?? 0} exercícios)
                      </option>
                    ))}
                  </select>
                </div>
              )}

              <h2 className="mb-3 mt-6 font-semibold text-slate-900">Biblioteca de exercícios</h2>
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
                            className={`glass flex items-center gap-3 rounded-xl px-3 py-2 text-left text-sm transition ${
                              selecionado
                                ? 'opacity-35'
                                : 'glass-hover text-slate-800'
                            }`}
                          >
                            <ExerciseAnimation
                              name={ex.name}
                              muscleGroup={ex.muscle_group}
                              imageUrl={ex.image_url}
                              videoUrl={ex.video_url}
                              imageCredit={ex.image_credit}
                              size="sm"
                              className="shrink-0 rounded-md text-[#2648b3]"
                            />
                            <span className="flex-1">{ex.name}</span>
                            {selecionado && <span className="text-emerald-400">✓</span>}
                          </button>
                        )
                      })}
                    </div>
                  </div>
                ))}
              </div>
            </div>

            <div>
              <h2 className="mb-3 font-semibold text-slate-900">
                Exercícios selecionados{' '}
                <span className="ml-1 rounded-full bg-[#2648b3]/10 px-2 py-0.5 text-xs text-[#2648b3]">
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
                        <div className="flex items-center gap-2.5">
                          {ex && (
                            <ExerciseAnimation
                              name={ex.name}
                              muscleGroup={ex.muscle_group}
                              imageUrl={ex.image_url}
                              videoUrl={ex.video_url}
                              imageCredit={ex.image_credit}
                              size="sm"
                              className="shrink-0 rounded-md text-[#8b7fd6]"
                            />
                          )}
                          <p className="font-medium text-slate-900">{ex?.name}</p>
                        </div>
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

              <div className="mt-6 flex gap-2">
                <button
                  type="button"
                  onClick={salvarComoModelo}
                  disabled={salvandoModelo || items.length === 0}
                  className="glass glass-hover shrink-0 rounded-xl px-4 py-3 text-sm font-medium text-slate-700"
                >
                  {salvandoModelo ? 'Salvando...' : modeloSalvo ? 'Modelo salvo ✓' : '💾 Salvar como modelo'}
                </button>
                <button
                  type="button"
                  onClick={salvarEEnviar}
                  disabled={salvando || items.length === 0}
                  className="btn-primary flex-1 rounded-xl px-4 py-3 text-sm"
                >
                  {salvando ? 'Enviando...' : 'Salvar e enviar ao aluno'}
                </button>
              </div>
            </div>
          </div>
        )}
      </main>
    </>
  )
}
