'use client'

import { useCallback, useEffect, useRef, useState } from 'react'
import Image from 'next/image'
import ChatBox from '@/components/ChatBox'
import ExerciseAnimation from '@/components/ExerciseAnimation'
import InstallAppModal from '@/components/InstallAppModal'
import Leaderboard from '@/components/Leaderboard'
import OnboardingAvaliacao from '@/components/OnboardingAvaliacao'
import SideMenu, { MenuItem } from '@/components/SideMenu'
import WeightChart from '@/components/WeightChart'
import { api, API_URL, ApiError } from '@/lib/api'
import { formatarDataCurta, formatarDataLonga, nomeMes, primeiroDiaAno, primeiroDiaMes, somarDias } from '@/lib/checkinDates'
import { comprimirImagem } from '@/lib/compressImage'
import {
  BodyMeasurement,
  BodyPhoto,
  Challenge,
  ExternalActivity,
  Gamificacao,
  HistoricoCheckins,
  Message,
  ParQAnswers,
  ResumoCheckins,
  Workout,
  WorkoutExerciseDetail,
} from '@/lib/types'
import { agruparExercicios, rotuloEstrutura } from '@/lib/workoutStructures'

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
  student: { id: string; name: string; objective: string | null; photo_url: string | null }
  workout: Workout | null
  exercises: WorkoutExerciseDetail[]
  activeSessionId: string | null
  registeredCounts: Record<string, number>
  measurements: BodyMeasurement[]
  gamificacao: Gamificacao
  desafio: Challenge | null
  onboardingCompleted: boolean
}

function chaveUltimaVista(token: string): string {
  return `chat_ultima_vista_${token}`
}

export default function PortalAlunoClient({ token }: { token: string }) {
  const [data, setData] = useState<PortalData | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [aba, setAba] = useState<'treino' | 'checkin' | 'evolucao' | 'fotos' | 'desafio' | 'chat'>('treino')
  const [menuAberto, setMenuAberto] = useState(false)
  const [instalarAberto, setInstalarAberto] = useState(false)

  function selecionarItemMenu(id: string) {
    if (id === 'instalar') setInstalarAberto(true)
    else setAba(id as typeof aba)
  }
  const [sessionId, setSessionId] = useState<string | null>(null)
  const [registrados, setRegistrados] = useState<Record<string, number>>({})
  const [recordes, setRecordes] = useState<Record<string, boolean>>({})
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
  const [ultimaVistaEm, setUltimaVistaEm] = useState<string | null>(null)

  const naoLidas = messages.filter(
    (m) => m.sender !== 'student' && (!ultimaVistaEm || new Date(m.created_at) > new Date(ultimaVistaEm))
  ).length

  const menuItems: MenuItem[] = [
    { id: 'treino', label: 'Treino', icon: '' },
    { id: 'checkin', label: 'Check-in', icon: '' },
    { id: 'evolucao', label: 'Avaliação Física', icon: '' },
    { id: 'fotos', label: 'Evolução', icon: '' },
    { id: 'desafio', label: 'Desafio', icon: '' },
    { id: 'chat', label: naoLidas > 0 ? `Mensagens (${naoLidas})` : 'Mensagens', icon: '' },
    { id: 'instalar', label: 'Instalar app', icon: '' },
  ]

  // strava
  const [stravaConectado, setStravaConectado] = useState(false)
  const [atividades, setAtividades] = useState<ExternalActivity[]>([])
  const [sincronizando, setSincronizando] = useState(false)
  const [avisoStrava, setAvisoStrava] = useState<string | null>(null)

  // evolução física por fotos
  const [fotosEvolucao, setFotosEvolucao] = useState<BodyPhoto[]>([])
  const [enviandoFotoEvolucao, setEnviandoFotoEvolucao] = useState(false)
  const [erroFotoEvolucao, setErroFotoEvolucao] = useState<string | null>(null)
  const fotoEvolucaoInputRef = useRef<HTMLInputElement | null>(null)

  const carregarFotosEvolucao = useCallback(() => {
    api
      .get<{ photos: BodyPhoto[] }>(`/portal/${token}/body-photos`)
      .then((d) => setFotosEvolucao(d.photos))
      .catch(() => {})
  }, [token])

  async function enviarFotoEvolucao(file: File) {
    setEnviandoFotoEvolucao(true)
    setErroFotoEvolucao(null)
    try {
      const comprimida = await comprimirImagem(file)
      const formData = new FormData()
      formData.append('foto', comprimida, 'evolucao.jpg')
      const { photo } = await api.postFile<{ photo: BodyPhoto }>(`/portal/${token}/body-photos`, formData)
      setFotosEvolucao((prev) => [photo, ...prev])
    } catch (err) {
      setErroFotoEvolucao(err instanceof ApiError ? err.message : 'Erro ao enviar foto')
    } finally {
      setEnviandoFotoEvolucao(false)
    }
  }

  // check-in de frequência
  const [resumoCheckins, setResumoCheckins] = useState<ResumoCheckins | null>(null)
  const [enviandoCheckin, setEnviandoCheckin] = useState(false)
  const [erroCheckin, setErroCheckin] = useState<string | null>(null)
  const [periodoHistorico, setPeriodoHistorico] = useState<'week' | 'month' | 'year'>('week')
  const [refHistorico, setRefHistorico] = useState<string | null>(null)
  const [historico, setHistorico] = useState<HistoricoCheckins | null>(null)
  const [fotoCheckinSelecionada, setFotoCheckinSelecionada] = useState<File | null>(null)
  const [comentarioCheckin, setComentarioCheckin] = useState('')
  const checkinCameraInputRef = useRef<HTMLInputElement | null>(null)
  const checkinGaleriaInputRef = useRef<HTMLInputElement | null>(null)

  const carregarResumoCheckins = useCallback(() => {
    api
      .get<ResumoCheckins>(`/portal/${token}/checkins/summary`)
      .then(setResumoCheckins)
      .catch(() => {})
  }, [token])

  const carregarHistoricoCheckins = useCallback(
    (period: 'week' | 'month' | 'year', ref: string | null) => {
      const params = new URLSearchParams({ period })
      if (ref) params.set('ref', ref)
      api
        .get<HistoricoCheckins>(`/portal/${token}/checkins?${params.toString()}`)
        .then(setHistorico)
        .catch(() => {})
    },
    [token]
  )

  useEffect(() => {
    carregarHistoricoCheckins(periodoHistorico, refHistorico)
  }, [periodoHistorico, refHistorico, carregarHistoricoCheckins])

  async function enviarCheckin() {
    if (!fotoCheckinSelecionada) return
    setEnviandoCheckin(true)
    setErroCheckin(null)
    try {
      const comprimida = await comprimirImagem(fotoCheckinSelecionada)
      const formData = new FormData()
      formData.append('foto', comprimida, 'checkin.jpg')
      if (comentarioCheckin.trim()) formData.append('comment', comentarioCheckin.trim())
      await api.postFile(`/portal/${token}/checkins`, formData)
      carregarResumoCheckins()
      carregarHistoricoCheckins(periodoHistorico, refHistorico)
      setFotoCheckinSelecionada(null)
      setComentarioCheckin('')
    } catch (err) {
      setErroCheckin(err instanceof ApiError ? err.message : 'Erro ao marcar check-in')
    } finally {
      setEnviandoCheckin(false)
    }
  }

  function irParaPeriodo(direcao: -1 | 1) {
    if (periodoHistorico === 'week') {
      const base = historico?.semana?.inicio ?? resumoCheckins?.semana.inicio
      if (base) setRefHistorico(somarDias(base, direcao * 7))
    } else if (periodoHistorico === 'month') {
      const base = historico?.mes ?? resumoCheckins?.mes
      if (base) setRefHistorico(primeiroDiaMes(base.ano, base.mes + direcao))
    } else {
      const base = historico?.ano ?? resumoCheckins?.ano
      if (base) setRefHistorico(primeiroDiaAno(base.ano + direcao))
    }
  }

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

    carregarFotosEvolucao()
    carregarResumoCheckins()
    carregarStrava()
    const params = new URLSearchParams(window.location.search)
    const statusStrava = params.get('strava')
    if (statusStrava === 'conectado') {
      setAvisoStrava('Strava conectado com sucesso!')
      setAba('evolucao')
    } else if (statusStrava === 'erro') {
      setAvisoStrava('Não foi possível conectar ao Strava. Tenta de novo?')
    }
    if (statusStrava) {
      window.history.replaceState({}, '', window.location.pathname)
      setTimeout(() => setAvisoStrava(null), 5000)
    }

    return () => clearInterval(intervalo)
  }, [token, carregarMensagens, carregarStrava, carregarFotosEvolucao, carregarResumoCheckins])

  // Restaura de onde o aluno parou de ler o chat (persiste entre visitas).
  useEffect(() => {
    const salvo = localStorage.getItem(chaveUltimaVista(token))
    if (salvo) setUltimaVistaEm(salvo)
  }, [token])

  // Pede permissão de notificação do navegador uma vez, sem bloquear o carregamento da página.
  useEffect(() => {
    if (typeof Notification !== 'undefined' && Notification.permission === 'default') {
      Notification.requestPermission().catch(() => {})
    }
  }, [])

  // Marca como lido assim que o aluno abre a aba de mensagens.
  useEffect(() => {
    if (aba !== 'chat' || messages.length === 0) return
    const ultima = messages[messages.length - 1]
    setUltimaVistaEm(ultima.created_at)
    localStorage.setItem(chaveUltimaVista(token), ultima.created_at)
  }, [aba, messages, token])

  // Notifica o aluno quando chega mensagem nova do professor/IA e ele não está na aba de chat.
  const totalMensagensAnterior = useRef(0)
  useEffect(() => {
    if (totalMensagensAnterior.current > 0 && messages.length > totalMensagensAnterior.current) {
      const novas = messages.slice(totalMensagensAnterior.current)
      const novaDoCoach = novas.find((m) => m.sender !== 'student')
      const foraDoChat = aba !== 'chat' || document.hidden
      if (novaDoCoach && foraDoChat && typeof Notification !== 'undefined' && Notification.permission === 'granted') {
        new Notification(novaDoCoach.sender === 'ai' ? 'Coach IA' : 'Seu professor', {
          body: novaDoCoach.content,
          icon: '/icon-192.png',
        })
      }
    }
    totalMensagensAnterior.current = messages.length
  }, [messages, aba])

  async function enviarAvaliacao(parQ: ParQAnswers, healthNotes: string, foto: File | null) {
    await api.post(`/portal/${token}/avaliacao`, { par_q_answers: parQ, health_notes: healthNotes })

    let photoUrl: string | null = null
    if (foto) {
      const formData = new FormData()
      formData.append('foto', foto)
      const resp = await api.postFile<{ photoUrl: string }>(`/portal/${token}/foto`, formData)
      photoUrl = resp.photoUrl
    }

    setData((prev) =>
      prev
        ? {
            ...prev,
            onboardingCompleted: true,
            student: photoUrl ? { ...prev.student, photo_url: photoUrl } : prev.student,
          }
        : prev
    )
  }

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
      const { isPr } = await api.post<{ isPr: boolean }>(`/portal/${token}/sessoes/${sessionId}/registros`, {
        workout_exercise_id: ex.id,
        set_number: jaFeitas + 1,
        reps_done: Number(valores.reps) || null,
        load_kg_done: valores.load ? Number(valores.load) : null,
      })
      setRegistrados({ ...registrados, [ex.id]: jaFeitas + 1 })
      if (isPr) {
        setRecordes((prev) => ({ ...prev, [ex.id]: true }))
        setTimeout(() => setRecordes((prev) => ({ ...prev, [ex.id]: false })), 4000)
      }
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

  if (!data.onboardingCompleted) {
    return <OnboardingAvaliacao nome={data.student.name} onEnviar={enviarAvaliacao} />
  }

  const primeiroNome = data.student.name.split(' ')[0]

  const cabecalho = (
    <header className="sticky top-0 z-20 border-b border-black/8 bg-white/90 backdrop-blur-xl">
      <div className="mx-auto flex w-full max-w-lg items-center gap-3 px-4 py-3">
        <button
          onClick={() => setMenuAberto(true)}
          aria-label="Abrir menu"
          className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-900/5"
        >
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <line x1="4" y1="7" x2="20" y2="7" />
            <line x1="4" y1="12" x2="20" y2="12" />
            <line x1="4" y1="17" x2="20" y2="17" />
          </svg>
        </button>
        <div className="min-w-0 flex-1">
          <p className="text-xs text-slate-500">Olá, {primeiroNome}</p>
          <p className="truncate font-bold text-slate-900">{data.workout ? data.workout.name : 'Seu espaço de treino'}</p>
        </div>
        <Image src="/clubemais-icone.png" alt="Clube Mais" width={36} height={36} className="h-9 w-9 shrink-0" />
      </div>
    </header>
  )

  if (aba === 'evolucao') {
    const validos = data.measurements.filter((m) => m.weight_kg != null)
    const ultima = validos[validos.length - 1]
    return (
      <div className="flex min-h-screen flex-col">
        {cabecalho}
        <SideMenu
          open={menuAberto}
          onClose={() => setMenuAberto(false)}
          nome={data.student.name}
          fotoUrl={data.student.photo_url}
          subtitulo="Clube Mais"
          items={menuItems}
          ativo={aba}
          onSelect={selecionarItemMenu}
        />
        <InstallAppModal open={instalarAberto} onClose={() => setInstalarAberto(false)} />
        <main className="mx-auto w-full max-w-lg flex-1 px-4 py-6">
          {avisoStrava && (
            <div className="mb-4 rounded-2xl border border-[#2648b3]/25 bg-[#2648b3]/8 px-4 py-3 text-sm text-[#2648b3]">
              {avisoStrava}
            </div>
          )}

          <div className="glass rounded-2xl p-5">
            <h2 className="mb-1 font-semibold text-slate-900">Sua evolução</h2>
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
                  {sincronizando ? 'Sincronizando...' : 'Sincronizar'}
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

  if (aba === 'checkin') {
    return (
      <div className="flex min-h-screen flex-col">
        {cabecalho}
        <SideMenu
          open={menuAberto}
          onClose={() => setMenuAberto(false)}
          nome={data.student.name}
          fotoUrl={data.student.photo_url}
          subtitulo="Clube Mais"
          items={menuItems}
          ativo={aba}
          onSelect={selecionarItemMenu}
        />
        <InstallAppModal open={instalarAberto} onClose={() => setInstalarAberto(false)} />
        <main className="mx-auto w-full max-w-lg flex-1 px-4 py-6">
          <div className="glass mb-4 rounded-2xl p-5">
            <h2 className="mb-1 font-semibold text-slate-900">Check-in</h2>
            <p className="mb-4 text-sm text-slate-500">
              Marque o treino de hoje com uma foto — na academia, treinando, tanto faz. Só conta 1 check-in por dia.
            </p>

            {erroCheckin && <p className="mb-3 text-sm text-rose-500">{erroCheckin}</p>}

            <input
              ref={checkinCameraInputRef}
              type="file"
              accept="image/*"
              capture="environment"
              className="hidden"
              onChange={(e) => {
                const file = e.target.files?.[0]
                if (file) setFotoCheckinSelecionada(file)
                e.target.value = ''
              }}
            />
            <input
              ref={checkinGaleriaInputRef}
              type="file"
              accept="image/*"
              className="hidden"
              onChange={(e) => {
                const file = e.target.files?.[0]
                if (file) setFotoCheckinSelecionada(file)
                e.target.value = ''
              }}
            />

            {fotoCheckinSelecionada ? (
              <div className="space-y-3">
                <p className="text-sm text-slate-600">
                  Foto selecionada: <span className="font-medium text-slate-900">{fotoCheckinSelecionada.name}</span>
                </p>
                <textarea
                  value={comentarioCheckin}
                  onChange={(e) => setComentarioCheckin(e.target.value)}
                  placeholder="Comentário pro seu professor (opcional)"
                  rows={2}
                  className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
                />
                <div className="flex gap-2">
                  <button
                    onClick={() => {
                      setFotoCheckinSelecionada(null)
                      setComentarioCheckin('')
                    }}
                    disabled={enviandoCheckin}
                    className="glass glass-hover rounded-xl px-4 py-2.5 text-sm text-slate-700"
                  >
                    Cancelar
                  </button>
                  <button
                    onClick={enviarCheckin}
                    disabled={enviandoCheckin}
                    className="btn-primary flex-1 rounded-xl px-4 py-2.5 text-sm"
                  >
                    {enviandoCheckin ? 'Enviando...' : 'Enviar check-in'}
                  </button>
                </div>
              </div>
            ) : (
              <div className="space-y-2">
                {resumoCheckins?.checkinHoje && (
                  <p className="text-xs text-slate-500">Treino de hoje já marcado — pode trocar a foto se quiser.</p>
                )}
                <div className="flex gap-2">
                  <button
                    onClick={() => checkinCameraInputRef.current?.click()}
                    className="btn-primary flex-1 rounded-xl px-4 py-3 text-sm"
                  >
                    Tirar foto agora
                  </button>
                  <button
                    onClick={() => checkinGaleriaInputRef.current?.click()}
                    className="glass glass-hover flex-1 rounded-xl px-4 py-3 text-sm text-slate-700"
                  >
                    Escolher da galeria
                  </button>
                </div>
              </div>
            )}
          </div>

          {resumoCheckins && (
            <>
              <div className="mb-4 grid grid-cols-3 gap-3">
                <div className="glass rounded-2xl p-4 text-center">
                  <p className="text-xs uppercase tracking-wider text-slate-500">Semana</p>
                  <p className="mt-1 text-xl font-bold text-slate-900">
                    {resumoCheckins.semana.dias_com_checkin}
                    <span className="text-sm font-normal text-slate-400">/{resumoCheckins.semana.total_dias}</span>
                  </p>
                </div>
                <div className="glass rounded-2xl p-4 text-center">
                  <p className="text-xs uppercase tracking-wider text-slate-500">Mês</p>
                  <p className="mt-1 text-xl font-bold text-slate-900">
                    {resumoCheckins.mes.dias_com_checkin}
                    <span className="text-sm font-normal text-slate-400">/{resumoCheckins.mes.total_dias_mes}</span>
                  </p>
                </div>
                <div className="glass rounded-2xl p-4 text-center">
                  <p className="text-xs uppercase tracking-wider text-slate-500">Ano</p>
                  <p className="mt-1 text-xl font-bold text-slate-900">{resumoCheckins.ano.dias_com_checkin}</p>
                </div>
              </div>

              <div className="glass mb-4 rounded-2xl p-5">
                <p className="mb-3 text-xs uppercase tracking-wider text-slate-500">Semana atual</p>
                <div className="grid grid-cols-7 gap-2">
                  {resumoCheckins.semana.grid.map((d) => (
                    <div key={d.date} className="flex flex-col items-center gap-1">
                      <span className="text-[10px] text-slate-400">{d.label}</span>
                      <span
                        title={d.comment ?? undefined}
                        className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold ${
                          d.checked ? 'bg-emerald-500 text-white' : 'bg-slate-900/6 text-slate-400'
                        }`}
                      >
                        {Number(d.date.slice(-2))}
                      </span>
                    </div>
                  ))}
                </div>
              </div>
            </>
          )}

          <div className="glass rounded-2xl p-5">
            <div className="mb-3 flex items-center justify-between">
              <div className="flex gap-1 rounded-lg bg-slate-900/5 p-1">
                <button
                  onClick={() => {
                    setPeriodoHistorico('week')
                    setRefHistorico(null)
                  }}
                  className={`rounded-md px-3 py-1 text-xs font-medium transition ${
                    periodoHistorico === 'week' ? 'bg-white text-slate-900 shadow' : 'text-slate-500'
                  }`}
                >
                  Semana
                </button>
                <button
                  onClick={() => {
                    setPeriodoHistorico('month')
                    setRefHistorico(null)
                  }}
                  className={`rounded-md px-3 py-1 text-xs font-medium transition ${
                    periodoHistorico === 'month' ? 'bg-white text-slate-900 shadow' : 'text-slate-500'
                  }`}
                >
                  Mês
                </button>
                <button
                  onClick={() => {
                    setPeriodoHistorico('year')
                    setRefHistorico(null)
                  }}
                  className={`rounded-md px-3 py-1 text-xs font-medium transition ${
                    periodoHistorico === 'year' ? 'bg-white text-slate-900 shadow' : 'text-slate-500'
                  }`}
                >
                  Ano
                </button>
              </div>
              <div className="flex items-center gap-1">
                <button
                  onClick={() => irParaPeriodo(-1)}
                  aria-label="Período anterior"
                  className="flex h-7 w-7 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-900/5"
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <polyline points="15 18 9 12 15 6" />
                  </svg>
                </button>
                {refHistorico && (
                  <button onClick={() => setRefHistorico(null)} className="px-1 text-xs font-medium text-[#2648b3]">
                    Hoje
                  </button>
                )}
                <button
                  onClick={() => irParaPeriodo(1)}
                  aria-label="Próximo período"
                  className="flex h-7 w-7 items-center justify-center rounded-lg text-slate-500 transition hover:bg-slate-900/5"
                >
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                    <polyline points="9 18 15 12 9 6" />
                  </svg>
                </button>
              </div>
            </div>

            {historico?.period === 'week' && historico.semana && (
              <div>
                <p className="mb-3 text-xs text-slate-500">
                  {formatarDataCurta(historico.semana.inicio)} a {formatarDataCurta(historico.semana.fim)}
                </p>
                <div className="grid grid-cols-7 gap-2">
                  {historico.semana.grid.map((d) => (
                    <div key={d.date} className="flex flex-col items-center gap-1">
                      <span className="text-[10px] text-slate-400">{d.label}</span>
                      <span
                        title={d.comment ?? undefined}
                        className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold ${
                          d.checked ? 'bg-emerald-500 text-white' : 'bg-slate-900/6 text-slate-400'
                        }`}
                      >
                        {Number(d.date.slice(-2))}
                      </span>
                    </div>
                  ))}
                </div>
                <p className="mt-3 text-sm text-slate-600">
                  {historico.semana.dias_com_checkin} de {historico.semana.total_dias} dias
                </p>
              </div>
            )}

            {historico?.period === 'month' && historico.mes && (
              <div>
                <p className="mb-3 text-xs text-slate-500">
                  {nomeMes(historico.mes.mes)} de {historico.mes.ano}
                </p>
                <div className="grid grid-cols-7 gap-1.5">
                  {Array.from({ length: historico.mes.total_dias_mes }, (_, i) => i + 1).map((dia) => (
                    <span
                      key={dia}
                      className={`flex h-7 w-7 items-center justify-center rounded-full text-[10px] font-medium ${
                        historico.mes?.dias_marcados.includes(dia)
                          ? 'bg-emerald-500 text-white'
                          : 'bg-slate-900/6 text-slate-400'
                      }`}
                    >
                      {dia}
                    </span>
                  ))}
                </div>
                <p className="mt-3 text-sm text-slate-600">{historico.mes.dias_com_checkin} dias treinados</p>
              </div>
            )}

            {historico?.period === 'year' && historico.ano && (
              <div>
                <p className="text-sm text-slate-600">
                  <span className="text-lg font-bold text-slate-900">{historico.ano.dias_com_checkin}</span> dias
                  treinados em {historico.ano.ano}
                </p>
              </div>
            )}
          </div>

          {historico && historico.fotos.length > 0 && (
            <div className="glass mt-4 rounded-2xl p-5">
              <p className="mb-3 text-xs uppercase tracking-wider text-slate-500">Fotos do período</p>
              <div className="space-y-3">
                {historico.fotos.map((foto) => (
                  <div key={foto.id} className="flex gap-3 rounded-xl bg-slate-900/3 p-3">
                    {/* eslint-disable-next-line @next/next/no-img-element -- foto vem de rota autenticada pelo token do aluno */}
                    <img
                      src={`${API_URL}/portal/${token}/checkins/${foto.id}/imagem`}
                      alt="Foto do check-in"
                      className="h-16 w-16 shrink-0 rounded-lg object-cover"
                    />
                    <div className="min-w-0 flex-1">
                      <p className="text-xs text-slate-500">{formatarDataLonga(foto.checkin_date)}</p>
                      {foto.comment && <p className="mt-0.5 text-sm text-slate-700">{foto.comment}</p>}
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </main>
      </div>
    )
  }

  if (aba === 'fotos') {
    return (
      <div className="flex min-h-screen flex-col">
        {cabecalho}
        <SideMenu
          open={menuAberto}
          onClose={() => setMenuAberto(false)}
          nome={data.student.name}
          fotoUrl={data.student.photo_url}
          subtitulo="Clube Mais"
          items={menuItems}
          ativo={aba}
          onSelect={selecionarItemMenu}
        />
        <InstallAppModal open={instalarAberto} onClose={() => setInstalarAberto(false)} />
        <main className="mx-auto w-full max-w-lg flex-1 px-4 py-6">
          <div className="glass mb-4 rounded-2xl p-5">
            <h2 className="mb-1 font-semibold text-slate-900">Evolução</h2>
            <p className="mb-4 text-sm text-slate-500">
              Registre fotos do seu corpo quando sentir que faz sentido — não existe frequência
              certa. A Coach IA comenta a evolução comparando com a foto anterior.
            </p>

            {erroFotoEvolucao && <p className="mb-3 text-sm text-rose-500">{erroFotoEvolucao}</p>}

            <input
              ref={fotoEvolucaoInputRef}
              type="file"
              accept="image/*"
              capture="environment"
              className="hidden"
              onChange={(e) => {
                const file = e.target.files?.[0]
                if (file) enviarFotoEvolucao(file)
                e.target.value = ''
              }}
            />
            <button
              onClick={() => fotoEvolucaoInputRef.current?.click()}
              disabled={enviandoFotoEvolucao}
              className="btn-primary w-full rounded-xl px-4 py-3 text-sm"
            >
              {enviandoFotoEvolucao ? 'Enviando...' : 'Registrar nova foto'}
            </button>
          </div>

          {fotosEvolucao.length === 0 && !enviandoFotoEvolucao && (
            <div className="glass rounded-2xl border-dashed p-8 text-center">
              <p className="text-sm text-slate-500">
                Nenhuma foto registrada ainda. Tire a primeira quando quiser começar a acompanhar
                sua evolução.
              </p>
            </div>
          )}

          <div className="space-y-4">
            {fotosEvolucao.map((foto) => (
              <div key={foto.id} className="glass overflow-hidden rounded-2xl">
                {/* eslint-disable-next-line @next/next/no-img-element -- foto vem de rota autenticada do backend, não do next/image */}
                <img
                  src={`${API_URL}/portal/${token}/body-photos/${foto.id}/imagem`}
                  alt="Foto de evolução"
                  className="max-h-96 w-full object-cover"
                />
                <div className="p-4">
                  <p className="mb-2 text-xs uppercase tracking-wider text-slate-500">
                    {new Date(foto.taken_at).toLocaleDateString('pt-BR', {
                      day: '2-digit',
                      month: 'long',
                      year: 'numeric',
                    })}
                    {' às '}
                    {new Date(foto.taken_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                  </p>
                  {foto.ai_feedback && (
                    <div className="rounded-2xl rounded-bl-md border border-violet-300 bg-violet-50 px-4 py-2.5 text-sm leading-relaxed text-violet-900">
                      <span className="mb-1 flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wider text-violet-500">
                        Coach IA
                      </span>
                      <p className="whitespace-pre-wrap">{foto.ai_feedback}</p>
                    </div>
                  )}
                </div>
              </div>
            ))}
          </div>
        </main>
      </div>
    )
  }

  if (aba === 'desafio') {
    const { gamificacao, desafio } = data
    return (
      <div className="flex min-h-screen flex-col">
        {cabecalho}
        <SideMenu
          open={menuAberto}
          onClose={() => setMenuAberto(false)}
          nome={data.student.name}
          fotoUrl={data.student.photo_url}
          subtitulo="Clube Mais"
          items={menuItems}
          ativo={aba}
          onSelect={selecionarItemMenu}
        />
        <InstallAppModal open={instalarAberto} onClose={() => setInstalarAberto(false)} />
        <main className="mx-auto w-full max-w-lg flex-1 px-4 py-6 space-y-4">
          <div className="glass flex items-center gap-5 rounded-2xl p-5">
            <div>
              <p className="text-xs uppercase tracking-wider text-slate-500">Sequência</p>
              <p className="text-lg font-bold text-slate-900">
                {gamificacao.streak > 0 ? `${gamificacao.streak} dia${gamificacao.streak === 1 ? '' : 's'}` : '—'}
              </p>
            </div>
            <div>
              <p className="text-xs uppercase tracking-wider text-slate-500">Treinos concluídos</p>
              <p className="text-lg font-bold text-slate-900">{gamificacao.total_sessoes}</p>
            </div>
          </div>

          {gamificacao.badges.length > 0 && (
            <div className="glass rounded-2xl p-5">
              <h2 className="mb-3 font-semibold text-slate-900">Suas medalhas</h2>
              <div className="flex flex-wrap gap-2">
                {gamificacao.badges.map((b) => (
                  <span
                    key={b.id}
                    title={b.label}
                    className="flex items-center gap-1.5 rounded-full bg-slate-900/5 px-3 py-1.5 text-sm"
                  >
                    {b.emoji} <span className="text-xs text-slate-600">{b.label}</span>
                  </span>
                ))}
              </div>
            </div>
          )}

          <div className="glass rounded-2xl p-5">
            <h2 className="mb-1 font-semibold text-slate-900">{desafio ? desafio.name : 'Nenhum desafio ativo'}</h2>
            {desafio ? (
              <>
                <p className="mb-3 text-sm text-slate-500">Quem completa mais treinos no período sobe no quadro.</p>
                <Leaderboard entries={desafio.leaderboard ?? []} highlightId={data.student.id} />
              </>
            ) : (
              <p className="text-sm text-slate-500">
                Assim que seu professor te colocar num desafio, ele aparece aqui.
              </p>
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
        <SideMenu
          open={menuAberto}
          onClose={() => setMenuAberto(false)}
          nome={data.student.name}
          fotoUrl={data.student.photo_url}
          subtitulo="Clube Mais"
          items={menuItems}
          ativo={aba}
          onSelect={selecionarItemMenu}
        />
        <InstallAppModal open={instalarAberto} onClose={() => setInstalarAberto(false)} />
        <main className="mx-auto flex w-full max-w-lg flex-1 flex-col">
          <div className="flex flex-1 flex-col" style={{ minHeight: 'calc(100vh - 110px)' }}>
            <ChatBox
              messages={messages}
              perspective="student"
              onSend={enviarMensagem}
              aguardandoIa={aguardandoIa}
              placeholder="Tire uma dúvida sobre seu treino..."
              vazioTexto="Fale com seu professor ou tire dúvidas — a IA do seu coach responde na hora."
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
        <SideMenu
          open={menuAberto}
          onClose={() => setMenuAberto(false)}
          nome={data.student.name}
          fotoUrl={data.student.photo_url}
          subtitulo="Clube Mais"
          items={menuItems}
          ativo={aba}
          onSelect={selecionarItemMenu}
        />
        <InstallAppModal open={instalarAberto} onClose={() => setInstalarAberto(false)} />
        <main className="flex flex-1 items-center justify-center px-4">
          <div className="glass max-w-sm rounded-3xl p-8 text-center">
            <span className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-emerald-400 to-cyan-500 text-white">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <polyline points="20 6 9 17 4 12" />
              </svg>
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
        <SideMenu
          open={menuAberto}
          onClose={() => setMenuAberto(false)}
          nome={data.student.name}
          fotoUrl={data.student.photo_url}
          subtitulo="Clube Mais"
          items={menuItems}
          ativo={aba}
          onSelect={selecionarItemMenu}
        />
        <InstallAppModal open={instalarAberto} onClose={() => setInstalarAberto(false)} />
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
        <SideMenu
          open={menuAberto}
          onClose={() => setMenuAberto(false)}
          nome={data.student.name}
          fotoUrl={data.student.photo_url}
          subtitulo="Clube Mais"
          items={menuItems}
          ativo={aba}
          onSelect={selecionarItemMenu}
        />
        <InstallAppModal open={instalarAberto} onClose={() => setInstalarAberto(false)} />
      <main className="mx-auto w-full max-w-lg flex-1 px-4 py-6 pb-24">
        {erro && <p className="mb-4 text-sm text-rose-400">{erro}</p>}

        {!sessionId && (
          <button onClick={iniciarTreino} className="btn-primary mb-6 w-full rounded-2xl px-4 py-4 text-base">
            Iniciar treino
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
          {agruparExercicios(data.exercises).map((grupo, gIdx) => {
            const estrutura = rotuloEstrutura(grupo.structureType)
            const emBloco = grupo.groupLabel && grupo.itens.length > 1
            return (
              <div key={gIdx} className={emBloco ? 'rounded-2xl border-2 border-dashed border-[#2648b3]/25 p-3' : ''}>
                {emBloco && (
                  <p className="mb-2 px-1 text-xs font-bold uppercase tracking-wider text-[#2648b3]">
                    {estrutura.label} {grupo.groupLabel}
                  </p>
                )}
                <div className="space-y-4">
                  {grupo.itens.map((ex, idx) => {
                    const feitas = registrados[ex.id] ?? 0
                    const completo = feitas >= ex.sets
                    return (
                      <div
                        key={ex.id}
                        className={`rounded-2xl border p-5 transition ${
                          completo ? 'border-emerald-400/30 bg-emerald-500/8' : 'glass'
                        }`}
                      >
                        <div className="flex items-start gap-3.5">
                          <span
                            className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-sm font-bold ${
                              completo ? 'bg-emerald-500/15 text-emerald-600' : 'bg-slate-900/6 text-slate-600'
                            }`}
                          >
                            {completo ? '✓' : emBloco ? `${grupo.groupLabel}${idx + 1}` : gIdx + 1}
                          </span>
                          <div className="min-w-[90px] flex-1">
                            <p className="text-xs uppercase tracking-wider text-slate-500">{ex.muscle_group}</p>
                            <p className="font-semibold text-slate-900">{ex.exercise_name}</p>
                            <p className="mt-0.5 text-sm text-slate-500">
                              {ex.sets} × {ex.reps}
                              {ex.load_kg ? ` · ${ex.load_kg}kg` : ''}
                              {ex.rest_seconds ? ` · ${ex.rest_seconds}s descanso` : ''}
                            </p>
                            {!emBloco && estrutura.label !== 'Tradicional' && (
                              <span className="mt-1 inline-block rounded-lg bg-violet-500/10 px-2 py-0.5 text-xs text-violet-600">
                                {estrutura.label}
                              </span>
                            )}
                            {recordes[ex.id] && (
                              <span className="mt-1 ml-1 inline-block rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700">
                                Novo recorde
                              </span>
                            )}
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
                                onChange={(e) =>
                                  setInputs({ ...inputs, [ex.id]: { ...inputs[ex.id], reps: e.target.value } })
                                }
                                className="input-dark w-full rounded-xl px-3 py-2.5 text-center text-sm"
                              />
                            </div>
                            <div className="flex-1">
                              <label className="mb-1 block text-xs text-slate-500">Carga (kg)</label>
                              <input
                                type="number"
                                inputMode="decimal"
                                value={inputs[ex.id]?.load ?? ''}
                                onChange={(e) =>
                                  setInputs({ ...inputs, [ex.id]: { ...inputs[ex.id], load: e.target.value } })
                                }
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
              </div>
            )
          })}
        </div>

        {sessionId && (
          <div className="glass mt-8 rounded-2xl p-5">
            <h2 className="mb-4 font-semibold text-slate-900">
              {todasSeriesFeitas ? 'Como foi o treino?' : 'Finalizar treino'}
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
                {enviandoFeedback ? 'Enviando...' : 'Concluir treino'}
              </button>
            </div>
          </div>
        )}
      </main>
    </div>
  )
}
