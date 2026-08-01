'use client'

import { useEffect, useState } from 'react'
import { useRouter, useSearchParams } from 'next/navigation'
import { Check } from 'lucide-react'
import Navbar from '@/components/Navbar'
import BackLink from '@/components/BackLink'
import ExerciseAnimation from '@/components/ExerciseAnimation'
import { api, ApiError } from '@/lib/api'
import { Exercise, SugestaoProgressao, Workout, WorkoutTemplate, WorkoutTemplateExerciseDetail } from '@/lib/types'
import { ESTRUTURAS } from '@/lib/workoutStructures'

function formatarKg(valor: number): string {
  // pt-BR e sem casa decimal à toa: 42,5kg, mas 40kg (não "40,0kg").
  return valor.toLocaleString('pt-BR', { maximumFractionDigits: 2 })
}

/**
 * `ultima_sessao_em` é timestamp completo com fuso (o middleware do backend
 * normaliza pra ISO 8601 com Z), então aqui `new Date` é o caminho certo — ao
 * contrário das colunas de data pura, que o lib/checkinDates trata sem
 * construtor justamente pra não deslocar o dia.
 */
function formatarDiaDaSessao(iso: string): string {
  const data = new Date(iso)
  return Number.isNaN(data.getTime()) ? '' : data.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
}

function textoDaSugestao(s: SugestaoProgressao): string {
  if (s.acao === 'aumentar_reps') {
    return `Sugestão: ${s.reps_sugeridas} reps (+1)`
  }
  if (s.carga_sugerida === null) {
    return 'Sugestão: manter a carga'
  }
  if (s.acao === 'manter') {
    return `Sugestão: manter ${formatarKg(s.carga_sugerida)}kg`
  }
  const sinal = s.delta_kg > 0 ? '+' : '−'
  return `Sugestão: ${formatarKg(s.carga_sugerida)}kg (${sinal}${formatarKg(Math.abs(s.delta_kg))}kg)`
}

interface ItemTreino {
  exercise_id: string
  sets: number
  reps: string
  load_kg?: number
  rest_seconds?: number
  structure_type: string
  group_label?: string
}

export default function NovoTreinoClient() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const studentId = searchParams.get('aluno')

  const [exercises, setExercises] = useState<Exercise[]>([])
  const [buscaExercicio, setBuscaExercicio] = useState('')
  const [name, setName] = useState('Treino A')
  const [items, setItems] = useState<ItemTreino[]>([])
  const [duracaoSemanas, setDuracaoSemanas] = useState('')
  const [erro, setErro] = useState<string | null>(null)
  const [salvando, setSalvando] = useState(false)
  const [templates, setTemplates] = useState<WorkoutTemplate[]>([])
  const [sugestoes, setSugestoes] = useState<Record<string, SugestaoProgressao>>({})
  const [carregandoModelo, setCarregandoModelo] = useState(false)
  const [salvandoModelo, setSalvandoModelo] = useState(false)
  const [modeloSalvo, setModeloSalvo] = useState(false)

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    if (!studentId) return
    api
      .get<{ exercises: Exercise[] }>('/exercicios')
      .then((data) => setExercises(data.exercises))
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar exercícios'))
    api
      .get<{ templates: WorkoutTemplate[] }>('/modelos')
      .then((data) => setTemplates(data.templates))
      .catch(() => {})
    // Sugestão de progressão é um extra em cima da prescrição — se falhar, a
    // tela continua funcionando exatamente como antes, sem sugestão nenhuma.
    api
      .get<{ sugestoes: Record<string, SugestaoProgressao> }>(`/alunos/${studentId}/progressao`)
      .then((data) => setSugestoes(data.sugestoes))
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
          rest_seconds: ex.rest_seconds ?? undefined,
          structure_type: ex.structure_type || 'tradicional',
          group_label: ex.group_label ?? undefined,
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
    if (items.some((i) => i.sets < 1)) {
      setErro('Todos os exercícios precisam ter pelo menos 1 série.')
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
    setItems([...items, { exercise_id: exerciseId, sets: 3, reps: '10-12', structure_type: 'tradicional' }])
  }

  function removerExercicio(exerciseId: string) {
    setItems(items.filter((i) => i.exercise_id !== exerciseId))
  }

  // Preenche o campo com o número sugerido — quem confirma a prescrição
  // continua sendo o personal, no botão de salvar. A sugestão nunca se aplica
  // sozinha.
  function aplicarSugestao(sugestao: SugestaoProgressao) {
    setItems(
      items.map((i) => {
        if (i.exercise_id !== sugestao.exercise_id) return i
        if (sugestao.acao === 'aumentar_reps') {
          return sugestao.reps_sugeridas ? { ...i, reps: sugestao.reps_sugeridas } : i
        }
        return sugestao.carga_sugerida !== null ? { ...i, load_kg: sugestao.carga_sugerida } : i
      })
    )
  }

  function atualizarItem(exerciseId: string, campo: keyof ItemTreino, valor: string) {
    setItems(
      items.map((i) => {
        if (i.exercise_id !== exerciseId) return i
        if (campo === 'sets') return { ...i, sets: Number(valor) || 0 }
        if (campo === 'load_kg') return { ...i, load_kg: valor ? Number(valor) : undefined }
        if (campo === 'rest_seconds') return { ...i, rest_seconds: valor ? Number(valor) : undefined }
        if (campo === 'structure_type') return { ...i, structure_type: valor }
        if (campo === 'group_label') return { ...i, group_label: valor || undefined }
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
    if (items.some((i) => i.sets < 1)) {
      setErro('Todos os exercícios precisam ter pelo menos 1 série.')
      return
    }
    setSalvando(true)
    try {
      const { workout } = await api.post<{ workout: Workout }>('/treinos', { student_id: studentId, name, items })
      await api.post(`/treinos/${workout.id}/enviar`, {
        duration_weeks: duracaoSemanas ? Number(duracaoSemanas) : undefined,
      })
      router.push(`/treinos/${workout.id}`)
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao salvar treino')
    } finally {
      setSalvando(false)
    }
  }

  const termoBusca = buscaExercicio.trim().toLowerCase()
  const exerciciosFiltrados = termoBusca
    ? exercises.filter((ex) => ex.name.toLowerCase().includes(termoBusca))
    : exercises
  const porGrupo = exerciciosFiltrados.reduce<Record<string, Exercise[]>>((acc, ex) => {
    acc[ex.muscle_group] = acc[ex.muscle_group] ?? []
    acc[ex.muscle_group].push(ex)
    return acc
  }, {})

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8">
        <BackLink href={studentId ? `/alunos/${studentId}` : '/dashboard'} label="Voltar ao aluno" />
        <h1 className="mb-1 font-display text-2xl font-bold tracking-tight text-ink">Novo treino</h1>
        <p className="mb-6 text-sm text-ink-muted">Monte a prescrição em poucos cliques e envie direto pro aluno.</p>

        {!studentId && (
          <p className="mb-4 text-sm text-danger">
            Nenhum aluno selecionado. Volte ao perfil do aluno e clique em &quot;Novo treino&quot;.
          </p>
        )}
        {erro && <p className="mb-4 text-sm text-danger">{erro}</p>}

        {studentId && (
          <div className="grid gap-6 md:grid-cols-2">
            <div>
              <label className="mb-1.5 block text-sm font-medium text-ink-soft">Nome do treino</label>
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
              />

              <div className="mt-3">
                <label className="mb-1.5 block text-sm font-medium text-ink-soft">
                  Validade (opcional)
                </label>
                <select
                  value={duracaoSemanas}
                  onChange={(e) => setDuracaoSemanas(e.target.value)}
                  className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
                >
                  <option value="">Sem prazo definido</option>
                  <option value="4">4 semanas</option>
                  <option value="6">6 semanas</option>
                  <option value="8">8 semanas</option>
                  <option value="12">12 semanas</option>
                </select>
                <p className="mt-1 text-xs text-ink-muted">
                  Se definir um prazo, você e o aluno recebem um aviso quando faltar 1 semana pra vencer.
                </p>
              </div>

              {templates.length > 0 && (
                <div className="mt-3">
                  <label className="mb-1.5 block text-sm font-medium text-ink-soft">Começar de um modelo</label>
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

              <h2 className="mb-3 mt-6 font-semibold text-ink">Biblioteca de exercícios</h2>
              <input
                type="text"
                value={buscaExercicio}
                onChange={(e) => setBuscaExercicio(e.target.value)}
                placeholder="Buscar exercício pelo nome..."
                className="input-dark mb-3 w-full rounded-xl px-4 py-2.5 text-sm"
              />
              <div className="chat-scroll max-h-[28rem] space-y-4 overflow-y-auto pr-2">
                {termoBusca && Object.keys(porGrupo).length === 0 && (
                  <p className="text-sm text-ink-muted">Nenhum exercício encontrado.</p>
                )}
                {Object.entries(porGrupo).map(([grupo, exs]) => (
                  <div key={grupo}>
                    <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-ink-muted">{grupo}</p>
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
                                : 'glass-hover text-ink'
                            }`}
                          >
                            <ExerciseAnimation
                              name={ex.name}
                              muscleGroup={ex.muscle_group}
                              imageUrl={ex.image_url}
                              videoUrl={ex.video_url}
                              imageCredit={ex.image_credit}
                              size="sm"
                              className="shrink-0 rounded-md text-brand"
                            />
                            <span className="flex-1">{ex.name}</span>
                            {selecionado && <Check size={16} className="text-success" />}
                          </button>
                        )
                      })}
                    </div>
                  </div>
                ))}
              </div>
            </div>

            <div>
              <h2 className="mb-3 font-semibold text-ink">
                Exercícios selecionados{' '}
                <span className="ml-1 rounded-full bg-brand/10 px-2 py-0.5 text-xs text-brand">
                  {items.length}
                </span>
              </h2>
              {items.length === 0 && (
                <div className="glass rounded-2xl border-dashed p-8 text-center text-sm text-ink-muted">
                  Clique nos exercícios ao lado para adicionar
                </div>
              )}
              <div className="space-y-3">
                {items.map((item) => {
                  const ex = exercises.find((e) => e.id === item.exercise_id)
                  const sugestao = sugestoes[item.exercise_id]
                  const temAlgoPraAplicar =
                    sugestao &&
                    (sugestao.acao === 'aumentar_reps' ? !!sugestao.reps_sugeridas : sugestao.carga_sugerida !== null)
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
                              className="shrink-0 rounded-md text-accent-deep"
                            />
                          )}
                          <p className="font-medium text-ink">{ex?.name}</p>
                        </div>
                        <button
                          type="button"
                          onClick={() => removerExercicio(item.exercise_id)}
                          className="text-xs text-danger transition hover:text-danger"
                        >
                          Remover
                        </button>
                      </div>
                      {sugestao && (
                        <div className="mb-3 rounded-xl bg-brand/6 px-3 py-2.5">
                          <div className="flex items-center justify-between gap-2">
                            <p className="text-xs font-semibold text-brand">{textoDaSugestao(sugestao)}</p>
                            {temAlgoPraAplicar && (
                              <button
                                type="button"
                                onClick={() => aplicarSugestao(sugestao)}
                                className="shrink-0 rounded-lg bg-brand/12 px-2.5 py-1 text-xs font-medium text-brand transition hover:bg-brand/20"
                              >
                                Aplicar
                              </button>
                            )}
                          </div>
                          <p className="mt-1 text-xs text-ink-muted">
                            {sugestao.motivo} · última sessão em {formatarDiaDaSessao(sugestao.ultima_sessao_em)} (
                            {sugestao.series_registradas}×{sugestao.reps_prescritas}
                            {sugestao.carga_anterior !== null ? ` · ${formatarKg(sugestao.carga_anterior)}kg` : ''})
                          </p>
                        </div>
                      )}

                      <div className="grid grid-cols-2 gap-2">
                        <div>
                          <label className="mb-1 block text-xs text-ink-muted">Séries</label>
                          <input
                            type="number"
                            min={1}
                            value={item.sets}
                            onChange={(e) => atualizarItem(item.exercise_id, 'sets', e.target.value)}
                            className="input-dark w-full rounded-lg px-2.5 py-2 text-sm"
                          />
                        </div>
                        <div>
                          <label className="mb-1 block text-xs text-ink-muted">Reps</label>
                          <input
                            type="text"
                            value={item.reps}
                            onChange={(e) => atualizarItem(item.exercise_id, 'reps', e.target.value)}
                            className="input-dark w-full rounded-lg px-2.5 py-2 text-sm"
                          />
                        </div>
                        <div>
                          <label className="mb-1 block text-xs text-ink-muted">Carga (kg)</label>
                          <input
                            type="number"
                            min={0}
                            value={item.load_kg ?? ''}
                            onChange={(e) => atualizarItem(item.exercise_id, 'load_kg', e.target.value)}
                            className="input-dark w-full rounded-lg px-2.5 py-2 text-sm"
                          />
                        </div>
                        <div>
                          <label className="mb-1 block text-xs text-ink-muted">Descanso (s)</label>
                          <input
                            type="number"
                            min={0}
                            step={5}
                            placeholder="Ex: 60"
                            value={item.rest_seconds ?? ''}
                            onChange={(e) => atualizarItem(item.exercise_id, 'rest_seconds', e.target.value)}
                            className="input-dark w-full rounded-lg px-2.5 py-2 text-sm"
                          />
                        </div>
                      </div>

                      <div className="mt-2 grid grid-cols-2 gap-2">
                        <div>
                          <label className="mb-1 block text-xs text-ink-muted">Estrutura</label>
                          <select
                            value={item.structure_type}
                            onChange={(e) => atualizarItem(item.exercise_id, 'structure_type', e.target.value)}
                            className="input-dark w-full rounded-lg px-2.5 py-2 text-sm"
                          >
                            {ESTRUTURAS.map((e) => (
                              <option key={e.value} value={e.value}>
                                {e.icone} {e.label}
                              </option>
                            ))}
                          </select>
                        </div>
                        {ESTRUTURAS.find((e) => e.value === item.structure_type)?.agrupavel && (
                          <div>
                            <label className="mb-1 block text-xs text-ink-muted">Grupo (ex: A)</label>
                            <input
                              type="text"
                              maxLength={2}
                              placeholder="A"
                              value={item.group_label ?? ''}
                              onChange={(e) => atualizarItem(item.exercise_id, 'group_label', e.target.value.toUpperCase())}
                              className="input-dark w-full rounded-lg px-2.5 py-2 text-sm"
                            />
                          </div>
                        )}
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
                  className="glass glass-hover flex shrink-0 items-center gap-1.5 rounded-xl px-4 py-3 text-sm font-medium text-ink-soft"
                >
                  {salvandoModelo ? (
                    'Salvando...'
                  ) : modeloSalvo ? (
                    <>
                      <Check size={15} className="text-success" /> Modelo salvo
                    </>
                  ) : (
                    'Salvar como modelo'
                  )}
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
