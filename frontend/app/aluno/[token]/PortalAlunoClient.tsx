'use client'

import { useCallback, useEffect, useState } from 'react'
import Image from 'next/image'
import ChatBox from '@/components/ChatBox'
import ExerciseAnimation from '@/components/ExerciseAnimation'
import WeightChart from '@/components/WeightChart'
import { api, API_URL, ApiError } from '@/lib/api'
import { BodyMeasurement, ExternalActivity, Message, Workout, WorkoutExerciseDetail } from '@/lib/types'

function formatarDuracao(segundos: number | null): string {
  if (!segundos) return ''
  const min = Math.round(segundos / 60)
  return min >= 60 ? `${Math.floor(min / 60)}h${String(min % 60).padStart(2, '0')}` : `${min} min`
}

function formatarDistancia(metros: number | null): string {
  if (!metros) return ''
  return `${(metros / 1000).toFixed(1)} km`
}

const NOME_ATIVIDADE: Record<string, string> = {
  Run: 'Corrida',
  Ride: 'Pedal',
  Walk: 'Caminhada',
  Swim: 'Natação',
  Hike: 'Trilha',
  WeightTraining: 'Musculação',
  Workout: 'Treino',
}

interface PortalData {
  student: { id: string; name: string; objective: string | null }
  workout: Workout | null
  exercises: WorkoutExerciseDetail[]
  activeSessionId: string | null
  registeredCounts: Record<string, number>
  measurements: BodyMeasurement[]
}

export default function PortalAlunoClient({ token }: { token: string }) {
  const [data, setData] = useState<PortalData | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [aba, setAba] = useState<'treino' | 'evolucao' | 'chat'>('treino')
  const [sessionId, setSessionId] = useState<string | null>(null)
  const [registrados, setRegistrados] = useState<Record<string, number>>({})
  const [inputs, setInputs] = useState<Record<string, { reps: string; load: string }>>({})
  const [treinoConcluido, setTreinoConcluido] = useState(false)
  const [enviandoFeedback, setEnviandoFeedback] = useState(false)
  const [rpe, setRpe] = useState(5)
  const [satisfacao, setSatisfacao] = useState(5)
  const [desconforto, setDesconforto] = useState('')
  const [comentario, setComentario] = useState('')

  // chat
  const [messages, setMessages] = useState<Message[]>([])
  const [aguardandoIa, setAguardandoIa] = useState(false)

  // strava
  const [stravaConectado, setStravaConectado] = useState(false)
  const [atividades, setAtividades] = useState<ExternalActivity[]>([])
  const [sincronizando, setSincronizando] = useState(false)
  const [avisoStrava, setAvisoStrava] = useState<string | null>(null)

  const carregarMensagens = useCallback(() => {
    api
      .get<{ messages: Message[] }>(`/portal/${token}/mensagens`)
      .then((d) => setMessages(d.messages))
      .catch(() => {})
  }, [token])

  const carregarStrava = useCallback(() => {
    api
      .get<{ conectado: boolean; atividades: ExternalActivity[] }>(`/strava/${token}/status`)
      .then((d) => {
        setStravaConectado(d.conectado)
        setAtividades(d.atividades)
      })
      .catch(() => {})
  }, [token])

  async function sincronizarStrava() {
    setSincronizando(true)
    try {
      await api.post(`/strava/${token}/sincronizar`)
      carregarStrava()
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao sincronizar com o Strava')
    } finally {
      setSincronizando(false)
    }
  }

  useEffect(() => {
    api
      .get<PortalData>(`/portal/${token}`)
      .then((d) => {
        setData(d)
        setSessionId(d.activeSessionId)
        setRegistrados(d.registeredCounts ?? {})
        const initialInputs: Record<string, { reps: string; load: string }> = {}
        d.exercises.forEach((ex) => {
          initialInputs[ex.id] = { reps: ex.reps, load: ex.load_kg ?? '' }
        })
        setInputs(initialInputs)
      })
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Não foi possível carregar seu treino'))

    carregarMensagens()
    const intervalo = setInterval(carregarMensagens, 5000)

    carregarStrava()
    const params = new URLSearchParams(window.location.search)
    const statusStrava = params.get('strava')
    if (statusStrava === 'conectado') {
      setAvisoStrava('Strava conectado com sucesso! 🎉')
      setAba('evolucao')
    } else if (statusStrava === 'erro') {
      setAvisoStrava('Não foi possível conectar ao Strava. Tenta de novo?')
    }
    if (statusStrava) {
      window.history.replaceState({}, '', window.location.pathname)
      setTimeout(() => setAvisoStrava(null), 5000)
    }

    return () => clearInterval(intervalo)
  }, [token, carregarMensagens, carregarStrava])

  async function enviarMensagem(texto: string) {
    setAguardandoIa(true)
    try {
      const resp = await api.post<{ message: Message; aiReply: Message | null }>(`/portal/${token}/mensagens`, {
        content: texto,
      })
      setMessages((prev) => [...prev, resp.message, ...(resp.aiReply ? [resp.aiReply] : [])])
    } finally {
      setAguardandoIa(false)
    }
  }

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

  if (erro && !data) {
    return (
      <main className="flex flex-1 items-center justify-center px-4">
        <p className="text-center text-rose-400">{erro}</p>
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

  const primeiroNome = data.student.name.split(' ')[0]

  const cabecalho = (
    <header className="sticky top-0 z-20 border-b border-black/8 bg-white/90 backdrop-blur-xl">
      <div className="mx-auto flex w-full max-w-lg items-center justify-between px-4 py-3">
        <div>
          <p className="text-xs text-slate-500">Olá, {primeiroNome} 👋</p>
          <p className="font-bold text-slate-900">{data.workout ? data.workout.name : 'Seu espaço de treino'}</p>
        </div>
        <Image src="/clubemais-icone.png" alt="Clube Mais" width={36} height={36} className="h-9 w-9" />
      </div>
      <nav className="mx-auto flex w-full max-w-lg px-4">
        {(
          [
            ['treino', 'Treino'],
            ['evolucao', 'Evolução'],
            ['chat', 'Chat'],
          ] as const
        ).map(([id, rotulo]) => (
          <button
            key={id}
            onClick={() => setAba(id)}
            className={`relative flex-1 pb-3 pt-1 text-sm font-medium transition ${
              aba === id ? 'text-slate-900' : 'text-slate-500 hover:text-slate-700'
            }`}
          >
            {rotulo}
            {aba === id && (
              <span className="absolute inset-x-8 bottom-0 h-0.5 rounded-full bg-gradient-to-r from-[#2648b3] to-[#8b7fd6]" />
            )}
          </button>
        ))}
      </nav>
    </header>
  )

  if (aba === 'evolucao') {
    const validos = data.measurements.filter((m) => m.weight_kg != null)
    const ultima = validos[validos.length - 1]
    return (
      <div className="flex min-h-screen flex-col">
        {cabecalho}
        <main className="mx-auto w-full max-w-lg flex-1 px-4 py-6">
          {avisoStrava && (
            <div className="mb-4 rounded-2xl border border-[#2648b3]/25 bg-[#2648b3]/8 px-4 py-3 text-sm text-[#2648b3]">
              {avisoStrava}
            </div>
          )}

          <div className="glass rounded-2xl p-5">
            <h2 className="mb-1 font-semibold text-slate-900">Sua evolução 📈</h2>
            <p className="mb-4 text-sm text-slate-500">
              {validos.length > 1
                ? 'Olha só o quanto você já caminhou até aqui!'
                : validos.length === 1
                  ? 'Primeira medição registrada — a partir daqui dá pra acompanhar sua evolução.'
                  : 'Assim que seu professor registrar sua primeira medição, seu progresso aparece aqui.'}
            </p>
            <WeightChart pontos={data.measurements} />
            {ultima && (ultima.waist_cm || ultima.hip_cm) && (
              <div className="mt-4 flex gap-4 text-sm text-slate-500">
                {ultima.waist_cm && (
                  <span>
                    Cintura: <strong className="text-slate-900">{ultima.waist_cm} cm</strong>
                  </span>
                )}
                {ultima.hip_cm && (
                  <span>
                    Quadril: <strong className="text-slate-900">{ultima.hip_cm} cm</strong>
                  </span>
                )}
              </div>
            )}
          </div>

          <div className="glass mt-4 rounded-2xl p-5">
            <div className="mb-3 flex items-center justify-between">
              <h2 className="font-semibold text-slate-900">Atividades (Strava)</h2>
              {stravaConectado ? (
                <button
                  onClick={sincronizarStrava}
                  disabled={sincronizando}
                  className="glass glass-hover rounded-xl px-3 py-1.5 text-xs font-medium text-slate-700"
                >
                  {sincronizando ? 'Sincronizando...' : '↻ Sincronizar'}
                </button>
              ) : (
                <a
                  href={`${API_URL}/strava/conectar/${token}`}
                  className="rounded-xl bg-[#fc4c02] px-3 py-1.5 text-xs font-semibold text-white"
                >
                  Conectar Strava
                </a>
              )}
            </div>

            {!stravaConectado && (
              <p className="text-sm text-slate-500">
                Conecte sua conta do Strava pra suas corridas, pedaladas e outras atividades aparecerem aqui
                automaticamente.
              </p>
            )}

            {stravaConectado && atividades.length === 0 && (
              <p className="text-sm text-slate-500">
                Nenhuma atividade sincronizada ainda. Clique em &quot;Sincronizar&quot; pra buscar suas atividades
                recentes.
              </p>
            )}

            {stravaConectado && atividades.length > 0 && (
              <div className="space-y-2">
                {atividades.map((a) => (
                  <div key={a.id} className="flex items-center justify-between rounded-xl bg-slate-900/3 px-3 py-2.5">
                    <div className="min-w-0">
                      <p className="truncate text-sm font-medium text-slate-800">
                        {NOME_ATIVIDADE[a.activity_type] ?? a.activity_type}
                        {a.name ? ` — ${a.name}` : ''}
                      </p>
                      <p className="text-xs text-slate-500">
                        {new Date(a.started_at).toLocaleDateString('pt-BR')}
                        {a.duration_seconds ? ` · ${formatarDuracao(a.duration_seconds)}` : ''}
                        {a.distance_meters ? ` · ${formatarDistancia(a.distance_meters)}` : ''}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        </main>
      </div>
    )
  }

  if (aba === 'chat') {
    return (
      <div className="flex min-h-screen flex-col">
        {cabecalho}
        <main className="mx-auto flex w-full max-w-lg flex-1 flex-col">
          <div className="flex flex-1 flex-col" style={{ minHeight: 'calc(100vh - 110px)' }}>
            <ChatBox
              messages={messages}
              perspective="student"
              onSend={enviarMensagem}
              aguardandoIa={aguardandoIa}
              placeholder="Tire uma dúvida sobre seu treino..."
              vazioTexto="Fale com seu professor ou tire dúvidas — a IA do seu coach responde na hora. 💬"
            />
          </div>
        </main>
      </div>
    )
  }

  if (treinoConcluido) {
    return (
      <div className="flex min-h-screen flex-col">
        {cabecalho}
        <main className="flex flex-1 items-center justify-center px-4">
          <div className="glass max-w-sm rounded-3xl p-8 text-center">
            <span className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 to-cyan-500 text-3xl">
              💪
            </span>
            <h1 className="text-2xl font-bold text-slate-900">Treino concluído!</h1>
            <p className="mt-2 text-slate-500">
              Bom trabalho, {primeiroNome}! Seu professor já pode ver seu progresso.
            </p>
            <button onClick={() => setAba('chat')} className="btn-primary mt-6 w-full rounded-xl px-4 py-3 text-sm">
              Mandar mensagem pro coach
            </button>
          </div>
        </main>
      </div>
    )
  }

  if (!data.workout) {
    return (
      <div className="flex min-h-screen flex-col">
        {cabecalho}
        <main className="flex flex-1 items-center justify-center px-4">
          <div className="glass max-w-sm rounded-3xl p-8 text-center">
            <h1 className="text-xl font-bold text-slate-900">Nenhum treino por aqui ainda</h1>
            <p className="mt-2 text-sm text-slate-500">
              Assim que seu professor enviar um treino, ele aparece aqui. Enquanto isso, pode mandar mensagem na aba
              Chat.
            </p>
          </div>
        </main>
      </div>
    )
  }

  const todasSeriesFeitas = data.exercises.every((ex) => (registrados[ex.id] ?? 0) >= ex.sets)
  const totalSeries = data.exercises.reduce((acc, ex) => acc + ex.sets, 0)
  const seriesFeitas = Object.values(registrados).reduce((acc, n) => acc + n, 0)
  const progresso = totalSeries > 0 ? Math.round((seriesFeitas / totalSeries) * 100) : 0

  return (
    <div className="flex min-h-screen flex-col">
      {cabecalho}
      <main className="mx-auto w-full max-w-lg flex-1 px-4 py-6 pb-24">
        {erro && <p className="mb-4 text-sm text-rose-400">{erro}</p>}

        {!sessionId && (
          <button onClick={iniciarTreino} className="btn-primary mb-6 w-full rounded-2xl px-4 py-4 text-base">
            Iniciar treino 🔥
          </button>
        )}

        {sessionId && (
          <div className="glass mb-6 rounded-2xl p-4">
            <div className="mb-2 flex items-center justify-between text-sm">
              <span className="font-medium text-slate-900">Progresso</span>
              <span className="text-slate-500">
                {seriesFeitas}/{totalSeries} séries
              </span>
            </div>
            <div className="h-2 overflow-hidden rounded-full bg-slate-900/8">
              <div
                className="h-full rounded-full bg-gradient-to-r from-[#2648b3] to-[#8b7fd6] transition-all duration-500"
                style={{ width: `${progresso}%` }}
              />
            </div>
          </div>
        )}

        <div className="space-y-4">
          {data.exercises.map((ex, idx) => {
            const feitas = registrados[ex.id] ?? 0
            const completo = feitas >= ex.sets
            return (
              <div
                key={ex.id}
                className={`rounded-2xl border p-5 transition ${
                  completo
                    ? 'border-emerald-400/30 bg-emerald-500/8'
                    : 'glass'
                }`}
              >
                <div className="flex items-start gap-3.5">
                  <span
                    className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm font-bold ${
                      completo
                        ? 'bg-emerald-500/15 text-emerald-600'
                        : 'bg-slate-900/6 text-slate-600'
                    }`}
                  >
                    {completo ? '✓' : idx + 1}
                  </span>
                  <div className="min-w-[90px] flex-1">
                    <p className="text-xs uppercase tracking-wider text-slate-500">{ex.muscle_group}</p>
                    <p className="font-semibold text-slate-900">{ex.exercise_name}</p>
                    <p className="mt-0.5 text-sm text-slate-500">
                      {ex.sets} × {ex.reps}
                      {ex.load_kg ? ` · ${ex.load_kg}kg` : ''}
                    </p>
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

                {sessionId && !completo && (
                  <div className="mt-4 flex items-end gap-2">
                    <div className="flex-1">
                      <label className="mb-1 block text-xs text-slate-500">Reps</label>
                      <input
                        type="number"
                        inputMode="numeric"
                        value={inputs[ex.id]?.reps ?? ''}
                        onChange={(e) => setInputs({ ...inputs, [ex.id]: { ...inputs[ex.id], reps: e.target.value } })}
                        className="input-dark w-full rounded-xl px-3 py-2.5 text-center text-sm"
                      />
                    </div>
                    <div className="flex-1">
                      <label className="mb-1 block text-xs text-slate-500">Carga (kg)</label>
                      <input
                        type="number"
                        inputMode="decimal"
                        value={inputs[ex.id]?.load ?? ''}
                        onChange={(e) => setInputs({ ...inputs, [ex.id]: { ...inputs[ex.id], load: e.target.value } })}
                        className="input-dark w-full rounded-xl px-3 py-2.5 text-center text-sm"
                      />
                    </div>
                    <button
                      onClick={() => registrarSerie(ex)}
                      className="btn-primary shrink-0 rounded-xl px-4 py-2.5 text-sm"
                    >
                      ✓ {feitas + 1}/{ex.sets}
                    </button>
                  </div>
                )}

                {sessionId && (
                  <div className="mt-3 flex gap-1.5">
                    {Array.from({ length: ex.sets }).map((_, i) => (
                      <span
                        key={i}
                        className={`h-1.5 flex-1 rounded-full ${
                          i < feitas ? 'bg-emerald-400' : 'bg-slate-900/8'
                        }`}
                      />
                    ))}
                  </div>
                )}
              </div>
            )
          })}
        </div>

        {sessionId && (
          <div className="glass mt-8 rounded-2xl p-5">
            <h2 className="mb-4 font-semibold text-slate-900">
              {todasSeriesFeitas ? 'Como foi o treino? 🎯' : 'Finalizar treino'}
            </h2>
            <div className="space-y-4">
              <div>
                <div className="mb-1 flex justify-between text-xs text-slate-500">
                  <span>Esforço percebido (RPE)</span>
                  <span className="font-bold text-[#2648b3]">{rpe}</span>
                </div>
                <input
                  type="range"
                  min={0}
                  max={10}
                  value={rpe}
                  onChange={(e) => setRpe(Number(e.target.value))}
                  className="w-full accent-[#2648b3]"
                />
              </div>
              <div>
                <div className="mb-1 flex justify-between text-xs text-slate-500">
                  <span>Satisfação</span>
                  <span className="font-bold text-[#2648b3]">{'★'.repeat(satisfacao)}</span>
                </div>
                <input
                  type="range"
                  min={1}
                  max={5}
                  value={satisfacao}
                  onChange={(e) => setSatisfacao(Number(e.target.value))}
                  className="w-full accent-[#2648b3]"
                />
              </div>
              <div>
                <label className="mb-1 block text-xs text-slate-500">Sentiu algum desconforto?</label>
                <input
                  type="text"
                  value={desconforto}
                  onChange={(e) => setDesconforto(e.target.value)}
                  className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
                />
              </div>
              <div>
                <label className="mb-1 block text-xs text-slate-500">Comentário (opcional)</label>
                <textarea
                  value={comentario}
                  onChange={(e) => setComentario(e.target.value)}
                  className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
                  rows={2}
                />
              </div>
              <button
                onClick={concluirTreino}
                disabled={enviandoFeedback}
                className="btn-primary w-full rounded-xl px-4 py-3 text-sm"
              >
                {enviandoFeedback ? 'Enviando...' : 'Concluir treino 🏁'}
              </button>
            </div>
          </div>
        )}
      </main>
    </div>
  )
}
