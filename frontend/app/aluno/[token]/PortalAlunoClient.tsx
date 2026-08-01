'use client'

import { useCallback, useEffect, useRef, useState } from 'react'
import Image from 'next/image'
import AtivarNotificacoesButton from '@/components/AtivarNotificacoesButton'
import ChatBox from '@/components/ChatBox'
import InstallBanner from '@/components/InstallBanner'
import ExerciseAnimation from '@/components/ExerciseAnimation'
import InstallAppModal from '@/components/InstallAppModal'
import FormCorrectionModal from '@/components/FormCorrectionModal'
import Leaderboard from '@/components/Leaderboard'
import AnamneseRevisao from '@/components/AnamneseRevisao'
import OnboardingAvaliacao from '@/components/OnboardingAvaliacao'
import SideMenu, { MenuItem } from '@/components/SideMenu'
import WeightChart from '@/components/WeightChart'
import { api, API_URL, ApiError } from '@/lib/api'
import { formatarDataCurta, formatarDataLonga, nomeMes, primeiroDiaAno, primeiroDiaMes, somarDias } from '@/lib/checkinDates'
import { comprimirImagem } from '@/lib/compressImage'
import { registrarServiceWorker, useEstaOnline } from '@/lib/conexao'
import { contarPendentes, enfileirar, listarFila, novoClientEntryId, removerDaFila } from '@/lib/filaOffline'
import { estaInstalado, useValorDoNavegador } from '@/lib/push'
import {
  Anamnese,
  BodyMeasurement,
  BodyPhoto,
  Challenge,
  ExternalActivity,
  Gamificacao,
  GymMediaSubmission,
  HistoricoCheckins,
  Message,
  ParQAnswers,
  PosturalAssessment,
  RespostasRevisao,
  RevisaoPendente,
  ResumoCheckins,
  Workout,
  WorkoutExerciseDetail,
  WorkoutResumo,
} from '@/lib/types'
import { agruparExercicios, rotuloEstrutura } from '@/lib/workoutStructures'

// localStorage pode conter lixo (versão antiga, extensão do navegador,
// edição manual) — um valor não-parseável não pode fazer a data "vista até"
// virar Invalid Date, senão a comparação abaixo nunca reconhece mensagem
// nova nenhuma.
function timestampValido(iso: string): number | null {
  const t = new Date(iso).getTime()
  return Number.isNaN(t) ? null : t
}

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
  workouts: WorkoutResumo[]
  workout: Workout | null
  exercises: WorkoutExerciseDetail[]
  activeSessionId: string | null
  registeredCounts: Record<string, number>
  measurements: BodyMeasurement[]
  gamificacao: Gamificacao
  desafio: Challenge | null
  onboardingCompleted: boolean
  revisaoPendente: RevisaoPendente | null
}

function chaveUltimaVista(token: string): string {
  return `chat_ultima_vista_${token}`
}

export default function PortalAlunoClient({ token }: { token: string }) {
  const [data, setData] = useState<PortalData | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [aba, setAba] = useState<'treino' | 'checkin' | 'evolucao' | 'fotos' | 'academia' | 'desafio' | 'chat'>('treino')
  const [menuAberto, setMenuAberto] = useState(false)
  const [instalarAberto, setInstalarAberto] = useState(false)

  function selecionarItemMenu(id: string) {
    if (id === 'instalar') setInstalarAberto(true)
    else setAba(id as typeof aba)
  }
  const [sessionId, setSessionId] = useState<string | null>(null)
  // Treino começado sem rede: não existe sessão no servidor ainda, mas o aluno
  // precisa poder registrar as séries mesmo assim (a sessão é criada na
  // sincronização, e POST /sessoes já é idempotente por treino).
  const [sessaoOffline, setSessaoOffline] = useState(false)
  const [pendentes, setPendentes] = useState(0)
  const online = useEstaOnline()
  const sincronizandoRef = useRef(false)
  const treinoEmAndamento = sessionId !== null || sessaoOffline
  const [exercicioAnaliseForm, setExercicioAnaliseForm] = useState<{ id: string; nome: string } | null>(null)
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
  const ultimaVistaSalvaInicial = useValorDoNavegador(() => localStorage.getItem(chaveUltimaVista(token)), null)
  const [ultimaVistaSalvaForcada, setUltimaVistaSalvaForcada] = useState<string | null>(null)
  const ultimaVistaSalva = ultimaVistaSalvaForcada ?? ultimaVistaSalvaInicial
  const [abaAnterior, setAbaAnterior] = useState(aba)

  // Enquanto a aba de chat está aberta, "visto até" é sempre a última mensagem
  // (0 não-lidas ali); fora dela, é o que foi persistido no localStorage. Ao
  // detectar (durante o render, sem Effect) que acabou de sair da aba de chat,
  // guarda esse instante como o novo "visto até" salvo.
  const ultimaMensagem = messages[messages.length - 1]
  const ultimaVistaEm = aba === 'chat' && ultimaMensagem ? ultimaMensagem.created_at : ultimaVistaSalva
  if (aba !== abaAnterior) {
    setAbaAnterior(aba)
    if (abaAnterior === 'chat' && ultimaMensagem) setUltimaVistaSalvaForcada(ultimaMensagem.created_at)
  }

  const ultimaVistaEmTs = ultimaVistaEm ? timestampValido(ultimaVistaEm) : null
  const naoLidas = messages.filter((m) => {
    if (m.sender === 'student') return false
    if (ultimaVistaEmTs === null) return true
    const criadaEmTs = timestampValido(m.created_at)
    return criadaEmTs === null || criadaEmTs > ultimaVistaEmTs
  }).length

  const menuItems: MenuItem[] = [
    { id: 'treino', label: 'Treino', icon: '' },
    { id: 'checkin', label: 'Check-in', icon: '' },
    { id: 'evolucao', label: 'Avaliação Física', icon: '' },
    { id: 'fotos', label: 'Evolução', icon: '' },
    { id: 'academia', label: 'Academia', icon: '' },
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

  // avaliação postural (opcional) — 3 fotos (frente/lado/costas) num único envio
  const [posturais, setPosturais] = useState<PosturalAssessment[]>([])
  const [fotosPostural, setFotosPostural] = useState<{ frente: File | null; lado: File | null; costas: File | null }>({
    frente: null,
    lado: null,
    costas: null,
  })
  const [enviandoPostural, setEnviandoPostural] = useState(false)
  const [erroPostural, setErroPostural] = useState<string | null>(null)

  const carregarPosturais = useCallback(() => {
    api
      .get<{ assessments: PosturalAssessment[] }>(`/portal/${token}/postural`)
      .then((d) => setPosturais(d.assessments))
      .catch(() => {})
  }, [token])

  async function enviarAvaliacaoPostural() {
    if (!fotosPostural.frente || !fotosPostural.lado || !fotosPostural.costas) return
    setEnviandoPostural(true)
    setErroPostural(null)
    try {
      const [frente, lado, costas] = await Promise.all([
        comprimirImagem(fotosPostural.frente),
        comprimirImagem(fotosPostural.lado),
        comprimirImagem(fotosPostural.costas),
      ])
      const formData = new FormData()
      formData.append('foto_frente', frente, 'frente.jpg')
      formData.append('foto_lado', lado, 'lado.jpg')
      formData.append('foto_costas', costas, 'costas.jpg')
      const { assessment } = await api.postFile<{ assessment: PosturalAssessment }>(`/portal/${token}/postural`, formData)
      setPosturais((prev) => [assessment, ...prev])
      setFotosPostural({ frente: null, lado: null, costas: null })
    } catch (err) {
      setErroPostural(err instanceof ApiError ? err.message : 'Erro ao enviar avaliação postural')
    } finally {
      setEnviandoPostural(false)
    }
  }

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

  // análise de academia por mídia (foto, vídeo ou álbum)
  const [submissoesAcademia, setSubmissoesAcademia] = useState<GymMediaSubmission[]>([])
  const [enviandoAcademia, setEnviandoAcademia] = useState(false)
  const [erroAcademia, setErroAcademia] = useState<string | null>(null)
  const [diasSemanaAcademia, setDiasSemanaAcademia] = useState(3)
  const fotosAcademiaInputRef = useRef<HTMLInputElement | null>(null)
  const videoAcademiaInputRef = useRef<HTMLInputElement | null>(null)

  const carregarSubmissoesAcademia = useCallback(() => {
    api
      .get<{ submissions: GymMediaSubmission[] }>(`/portal/${token}/academia`)
      .then((d) => setSubmissoesAcademia(d.submissions))
      .catch(() => {})
  }, [token])

  async function enviarMidiaAcademia(files: File[]) {
    setEnviandoAcademia(true)
    setErroAcademia(null)
    try {
      const formData = new FormData()
      for (const file of files) {
        if (file.type.startsWith('image/')) {
          const comprimida = await comprimirImagem(file)
          formData.append('media', comprimida, file.name.replace(/\.[^.]+$/, '.jpg'))
        } else {
          formData.append('media', file)
        }
      }
      formData.append('days_per_week', String(diasSemanaAcademia))
      await api.postFile(`/portal/${token}/academia`, formData)
      carregarSubmissoesAcademia()
    } catch (err) {
      setErroAcademia(err instanceof ApiError ? err.message : 'Erro ao enviar mídia da academia')
    } finally {
      setEnviandoAcademia(false)
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

  // Evita empilhar requisições do polling: se o GET anterior ainda não voltou
  // (rede lenta) quando o próximo setInterval dispara, pula esse ciclo em vez
  // de deixar duas respostas em voo — a mais lenta poderia resolver por último
  // e sobrescrever o estado com dado desatualizado.
  const carregandoMensagensRef = useRef(false)
  const carregarMensagens = useCallback(() => {
    if (carregandoMensagensRef.current) return
    carregandoMensagensRef.current = true
    api
      .get<{ messages: Message[] }>(`/portal/${token}/mensagens`)
      .then((d) => setMessages(d.messages))
      .catch(() => {})
      .finally(() => {
        carregandoMensagensRef.current = false
      })
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

  // Espelho do `data` pra sincronização da fila poder recarregar o treino que
  // está aberto sem virar dependência do callback (o que o recriaria a cada
  // resposta do servidor e reiniciaria o intervalo de retry).
  const dataRef = useRef<PortalData | null>(null)
  useEffect(() => {
    dataRef.current = data
  }, [data])

  const carregarPortal = useCallback((workoutId?: string) => {
    const query = workoutId ? `?workout_id=${workoutId}` : ''
    api
      .get<PortalData>(`/portal/${token}${query}`)
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
  }, [token])

  useEffect(() => {
    carregarPortal()

    carregarMensagens()
    const intervalo = setInterval(carregarMensagens, 5000)

    carregarFotosEvolucao()
    carregarPosturais()
    carregarResumoCheckins()
    carregarSubmissoesAcademia()
    carregarStrava()
    const params = new URLSearchParams(window.location.search)
    const statusStrava = params.get('strava')
    if (statusStrava) {
      // Adiado pra fora do corpo síncrono do effect (mesma ideia do setTimeout
      // de baixo) — processa o parâmetro da URL só uma vez, sem setState direto.
      queueMicrotask(() => {
        if (statusStrava === 'conectado') {
          setAvisoStrava('Strava conectado com sucesso!')
          setAba('evolucao')
        } else if (statusStrava === 'erro') {
          setAvisoStrava('Não foi possível conectar ao Strava. Tenta de novo?')
        }
      })
      window.history.replaceState({}, '', window.location.pathname)
      setTimeout(() => setAvisoStrava(null), 5000)
    }

    return () => clearInterval(intervalo)
  }, [
    token,
    carregarPortal,
    carregarMensagens,
    carregarStrava,
    carregarFotosEvolucao,
    carregarPosturais,
    carregarResumoCheckins,
    carregarSubmissoesAcademia,
  ])

  // Pede permissão de notificação do navegador uma vez, sem bloquear o carregamento da página.
  // Só faz sentido pedir isso pra quem já instalou o app na tela inicial — no
  // navegador comum (Safari/Chrome sem instalar) o pedido nem funciona de verdade
  // pra Web Push no iOS, e só incomodaria à toa quem tá só visitando.
  useEffect(() => {
    if (typeof Notification !== 'undefined' && Notification.permission === 'default' && estaInstalado()) {
      Notification.requestPermission().catch(() => {})
    }
  }, [])

  // Persiste no localStorage assim que o aluno abre a aba de mensagens (o
  // "visto até" em si já é derivado direto de `aba`/`messages` acima).
  useEffect(() => {
    if (aba !== 'chat' || !ultimaMensagem) return
    localStorage.setItem(chaveUltimaVista(token), ultimaMensagem.created_at)
  }, [aba, ultimaMensagem, token])

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

  async function enviarAvaliacao(
    parQ: ParQAnswers,
    healthNotes: string,
    foto: File | null,
    birthDate: string,
    anamnese: Anamnese
  ) {
    await api.post(`/portal/${token}/avaliacao`, {
      par_q_answers: parQ,
      health_notes: healthNotes,
      birth_date: birthDate || null,
      anamnese,
    })

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

  async function enviarRevisao(respostas: RespostasRevisao) {
    if (!data?.revisaoPendente) return
    await api.post(`/portal/${token}/revisao`, {
      workout_id: data.revisaoPendente.workout_id,
      respostas,
    })
    setData((prev) => (prev ? { ...prev, revisaoPendente: null } : prev))
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

  // Despacha a fila offline em ordem. Cada treino vira uma sessão só (POST
  // /sessoes devolve a sessão em andamento se já existir), e cada série leva o
  // client_entry_id gerado quando o aluno registrou — o backend usa isso pra
  // reconhecer reenvio em vez de duplicar.
  const sincronizarFila = useCallback(async () => {
    if (sincronizandoRef.current) return
    sincronizandoRef.current = true
    try {
      const itens = await listarFila(token)
      if (itens.length === 0) {
        setPendentes(0)
        return
      }

      const sessaoDoTreino = new Map<string, string>()
      let despachados = 0

      for (const item of itens) {
        try {
          let idSessao = sessaoDoTreino.get(item.workoutId)
          if (!idSessao) {
            const { session } = await api.post<{ session: { id: string } }>(`/portal/${token}/sessoes`, {
              workout_id: item.workoutId,
            })
            idSessao = session.id
            sessaoDoTreino.set(item.workoutId, idSessao)
          }

          if (item.payload.tipo === 'serie') {
            await api.post(`/portal/${token}/sessoes/${idSessao}/registros`, {
              client_entry_id: item.clientEntryId,
              workout_exercise_id: item.payload.workoutExerciseId,
              set_number: item.payload.setNumber,
              reps_done: item.payload.repsDone,
              load_kg_done: item.payload.loadKgDone,
            })
          } else {
            await api.post(`/portal/${token}/sessoes/${idSessao}/concluir`, {
              effort_rpe: item.payload.effortRpe,
              satisfaction: item.payload.satisfaction,
              discomfort: item.payload.discomfort,
              comment: item.payload.comment,
            })
          }

          await removerDaFila(item.seq)
          despachados++
        } catch (err) {
          // 4xx que não seja excesso de requisições é recusa definitiva (treino
          // arquivado, série já registrada por outro aparelho): reenviar nunca
          // vai passar e o item travaria a fila pra sempre — descarta e segue.
          const recusaDefinitiva =
            err instanceof ApiError && err.status >= 400 && err.status < 500 && err.status !== 429
          if (recusaDefinitiva) {
            await removerDaFila(item.seq)
            despachados++
            continue
          }
          // Rede caiu de novo (ou o servidor está fora): para aqui e mantém o
          // resto da fila intacto, na ordem, pra próxima tentativa.
          break
        }
      }

      setPendentes(await contarPendentes(token))
      if (despachados > 0) {
        setSessaoOffline(false)
        carregarPortal(dataRef.current?.workout?.id)
      }
    } finally {
      sincronizandoRef.current = false
    }
  }, [token, carregarPortal])

  // Registra o Service Worker (cache do treino do dia) e tenta esvaziar o que
  // tiver sobrado da fila de uma visita anterior.
  useEffect(() => {
    registrarServiceWorker()
    contarPendentes(token).then(setPendentes).catch(() => {})
  }, [token])

  useEffect(() => {
    if (!online) return
    sincronizarFila()
  }, [online, sincronizarFila])

  // O `online` do navegador é otimista demais pra ser o único gatilho (Wi-Fi de
  // academia que conecta mas não navega não dispara evento nenhum) — enquanto
  // houver pendência, tenta de novo de tempos em tempos.
  useEffect(() => {
    if (pendentes === 0) return
    const intervalo = setInterval(sincronizarFila, 30000)
    return () => clearInterval(intervalo)
  }, [pendentes, sincronizarFila])

  async function iniciarTreino() {
    if (!data?.workout) return
    try {
      const { session } = await api.post<{ session: { id: string } }>(`/portal/${token}/sessoes`, {
        workout_id: data.workout.id,
      })
      setSessionId(session.id)
    } catch (err) {
      // Sem rede o treino começa localmente: a sessão de verdade é criada na
      // sincronização (POST /sessoes é idempotente por treino, então despachar
      // a fila depois não cria sessão duplicada).
      if (err instanceof ApiError) {
        setErro(err.message)
        return
      }
      setSessaoOffline(true)
    }
  }

  async function registrarSerie(ex: WorkoutExerciseDetail) {
    if (!treinoEmAndamento || !data?.workout) return
    const jaFeitas = registrados[ex.id] ?? 0
    if (jaFeitas >= ex.sets) return

    const valores = inputs[ex.id] ?? { reps: ex.reps, load: ex.load_kg ?? '' }
    const serie = {
      workoutExerciseId: ex.id,
      setNumber: jaFeitas + 1,
      repsDone: Number(valores.reps) || null,
      loadKgDone: valores.load ? Number(valores.load) : null,
    }
    // Gerado antes de tentar a rede: se o envio falhar no meio (a resposta pode
    // ter chegado no servidor mesmo assim), a série vai pra fila com o mesmo
    // ID e o backend reconhece como repetição em vez de duplicar.
    const clientEntryId = novoClientEntryId()

    async function enfileirarSerie() {
      await enfileirar(token, data!.workout!.id, { tipo: 'serie', ...serie }, clientEntryId)
      setPendentes(await contarPendentes(token))
      setRegistrados((prev) => ({ ...prev, [ex.id]: jaFeitas + 1 }))
    }

    if (!sessionId) {
      await enfileirarSerie()
      return
    }

    try {
      const { isPr } = await api.post<{ isPr: boolean }>(`/portal/${token}/sessoes/${sessionId}/registros`, {
        client_entry_id: clientEntryId,
        workout_exercise_id: ex.id,
        set_number: serie.setNumber,
        reps_done: serie.repsDone,
        load_kg_done: serie.loadKgDone,
      })
      setRegistrados((prev) => ({ ...prev, [ex.id]: jaFeitas + 1 }))
      if (isPr) {
        setRecordes((prev) => ({ ...prev, [ex.id]: true }))
        setTimeout(() => setRecordes((prev) => ({ ...prev, [ex.id]: false })), 4000)
      }
    } catch (err) {
      if (err instanceof ApiError) {
        setErro(err.message)
        return
      }
      // Caiu a rede no meio da série (o caso comum na academia) — guarda e segue.
      await enfileirarSerie()
    }
  }

  async function concluirTreino() {
    if (!treinoEmAndamento || !data?.workout) return
    setEnviandoFeedback(true)

    const conclusao = {
      tipo: 'concluir' as const,
      effortRpe: rpe,
      satisfaction: satisfacao,
      discomfort: desconforto,
      comment: comentario,
    }

    async function enfileirarConclusao() {
      await enfileirar(token, data!.workout!.id, conclusao)
      setPendentes(await contarPendentes(token))
      setTreinoConcluido(true)
    }

    try {
      if (!sessionId) {
        await enfileirarConclusao()
        return
      }
      await api.post(`/portal/${token}/sessoes/${sessionId}/concluir`, {
        effort_rpe: rpe,
        satisfaction: satisfacao,
        discomfort: desconforto,
        comment: comentario,
      })
      setTreinoConcluido(true)
    } catch (err) {
      if (err instanceof ApiError) {
        setErro(err.message)
        return
      }
      await enfileirarConclusao()
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

  if (data.revisaoPendente) {
    return (
      <AnamneseRevisao
        nome={data.student.name}
        nomeTreino={data.revisaoPendente.workout_name}
        onEnviar={enviarRevisao}
      />
    )
  }

  const primeiroNome = data.student.name.split(' ')[0]

  const cabecalho = (
    <>
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
      <div className="mx-auto w-full max-w-lg px-4 pt-4">
        <InstallBanner />
        <AtivarNotificacoesButton caminhoSubscribe={`/portal/${token}/push/subscribe`} />
      </div>
    </>
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
          {erro && <p className="mb-4 text-sm text-rose-400">{erro}</p>}

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
            <p className="mb-1 text-sm text-slate-500">
              Marque o treino de hoje com uma foto — na academia, treinando, tanto faz. Só conta 1 check-in por dia.
            </p>
            <p className="mb-4 text-xs text-slate-400">Seu professor também vê essas fotos e comentários.</p>

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

          {historico && (
            <div className="glass mt-4 rounded-2xl p-5">
              <p className="mb-3 text-xs uppercase tracking-wider text-slate-500">Fotos do período</p>
              {historico.fotos.length === 0 ? (
                <p className="text-sm text-slate-500">Nenhuma foto registrada nesse período ainda.</p>
              ) : (
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
              )}
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

          <div className="glass mt-6 rounded-2xl p-5">
            <h2 className="mb-1 font-semibold text-slate-900">Avaliação postural (opcional)</h2>
            <p className="mb-4 text-sm text-slate-500">
              Envie 3 fotos (frente, lado e costas) pra Coach IA comentar seu alinhamento postural.
              É opcional — só faça se estiver à vontade.
            </p>

            <div className="mb-4 rounded-xl bg-slate-900/3 p-4 text-xs leading-relaxed text-slate-600">
              <p className="mb-1.5 font-medium text-slate-700">Como tirar as fotos:</p>
              <p className="mb-1.5">
                Sempre no mesmo lugar, mesma roupa, descalço(a), braços soltos, postura natural — isso
                ajuda a comparar sua evolução com precisão.
              </p>
              <p className="mb-1.5">
                <strong>Homens:</strong> sem camisa, de shorts/bermuda. <strong>Mulheres:</strong> top
                esportivo justo + shorts.
              </p>
              <p>
                Peça pra alguém segurar o celular na altura do peito, a uns 2-3 passos de distância,
                numa parede lisa e clara. Luz natural de frente ajuda bastante.
              </p>
            </div>

            {erroPostural && <p className="mb-3 text-sm text-rose-500">{erroPostural}</p>}

            <div className="mb-4 grid grid-cols-3 gap-2">
              {(
                [
                  ['frente', 'De frente'],
                  ['lado', 'De lado'],
                  ['costas', 'De costas'],
                ] as const
              ).map(([angulo, label]) => (
                <label
                  key={angulo}
                  className="glass glass-hover flex cursor-pointer flex-col items-center gap-1.5 rounded-xl p-3 text-center text-xs text-slate-600"
                >
                  <input
                    type="file"
                    accept="image/*"
                    capture="environment"
                    className="hidden"
                    onChange={(e) => {
                      const file = e.target.files?.[0]
                      if (file) setFotosPostural((prev) => ({ ...prev, [angulo]: file }))
                      e.target.value = ''
                    }}
                  />
                  <span className={fotosPostural[angulo] ? 'text-emerald-500' : 'text-slate-400'}>
                    {fotosPostural[angulo] ? '✓' : '+'}
                  </span>
                  {label}
                </label>
              ))}
            </div>

            <button
              onClick={enviarAvaliacaoPostural}
              disabled={enviandoPostural || !fotosPostural.frente || !fotosPostural.lado || !fotosPostural.costas}
              className="btn-primary w-full rounded-xl px-4 py-3 text-sm"
            >
              {enviandoPostural ? 'Enviando...' : 'Enviar avaliação postural'}
            </button>
          </div>

          {posturais.length > 0 && (
            <div className="mt-4 space-y-4">
              {posturais.map((avaliacao) => (
                <div key={avaliacao.id} className="glass overflow-hidden rounded-2xl">
                  <div className="grid grid-cols-3 gap-0.5">
                    {(['frente', 'lado', 'costas'] as const).map((angulo) => (
                      // eslint-disable-next-line @next/next/no-img-element -- foto vem de rota autenticada do backend, não do next/image
                      <img
                        key={angulo}
                        src={`${API_URL}/portal/${token}/postural/${avaliacao.id}/imagem/${angulo}`}
                        alt={`Avaliação postural - ${angulo}`}
                        className="aspect-[3/4] w-full object-cover"
                      />
                    ))}
                  </div>
                  <div className="p-4">
                    <p className="mb-2 text-xs uppercase tracking-wider text-slate-500">
                      {new Date(avaliacao.taken_at).toLocaleDateString('pt-BR', {
                        day: '2-digit',
                        month: 'long',
                        year: 'numeric',
                      })}
                    </p>
                    {avaliacao.ai_feedback && (
                      <div className="rounded-2xl rounded-bl-md border border-violet-300 bg-violet-50 px-4 py-2.5 text-sm leading-relaxed text-violet-900">
                        <span className="mb-1 flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wider text-violet-500">
                          Coach IA
                        </span>
                        <p className="whitespace-pre-wrap">{avaliacao.ai_feedback}</p>
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </main>
      </div>
    )
  }

  if (aba === 'academia') {
    const rotuloStatus: Record<string, string> = {
      analyzing: 'Analisando...',
      failed: 'Falhou',
      pending: 'Aguardando o professor',
      approved: 'Aprovado — já está no seu treino',
      rejected: 'Não aprovado desta vez',
    }
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
            <h2 className="mb-1 font-semibold text-slate-900">Análise de academia</h2>
            <p className="mb-4 text-sm text-slate-500">
              Envie fotos ou um vídeo curto da sua academia — a IA identifica os equipamentos e
              monta uma sugestão de treino pro seu professor revisar e aprovar.
            </p>

            <label className="mb-3 block text-sm font-medium text-slate-700">
              Quantos dias por semana você treina?
              <select
                value={diasSemanaAcademia}
                onChange={(e) => setDiasSemanaAcademia(Number(e.target.value))}
                className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm"
              >
                {[2, 3, 4, 5, 6].map((n) => (
                  <option key={n} value={n}>
                    {n} dias
                  </option>
                ))}
              </select>
            </label>

            {erroAcademia && <p className="mb-3 text-sm text-rose-500">{erroAcademia}</p>}

            <input
              ref={fotosAcademiaInputRef}
              type="file"
              accept="image/*"
              multiple
              capture="environment"
              className="hidden"
              onChange={(e) => {
                const files = Array.from(e.target.files ?? [])
                if (files.length) enviarMidiaAcademia(files)
                e.target.value = ''
              }}
            />
            <input
              ref={videoAcademiaInputRef}
              type="file"
              accept="video/mp4,video/quicktime"
              capture="environment"
              className="hidden"
              onChange={(e) => {
                const file = e.target.files?.[0]
                if (file) enviarMidiaAcademia([file])
                e.target.value = ''
              }}
            />
            <div className="grid grid-cols-2 gap-3">
              <button
                onClick={() => fotosAcademiaInputRef.current?.click()}
                disabled={enviandoAcademia}
                className="btn-primary rounded-xl px-4 py-3 text-sm"
              >
                {enviandoAcademia ? 'Enviando...' : 'Enviar fotos'}
              </button>
              <button
                onClick={() => videoAcademiaInputRef.current?.click()}
                disabled={enviandoAcademia}
                className="glass glass-hover rounded-xl px-4 py-3 text-sm font-medium text-slate-700"
              >
                {enviandoAcademia ? 'Enviando...' : 'Enviar vídeo'}
              </button>
            </div>
            {enviandoAcademia && (
              <p className="mt-3 text-center text-sm text-slate-500">
                Analisando sua academia — isso pode levar alguns segundos...
              </p>
            )}
          </div>

          {submissoesAcademia.length === 0 && !enviandoAcademia && (
            <div className="glass rounded-2xl border-dashed p-8 text-center">
              <p className="text-sm text-slate-500">Nenhuma análise enviada ainda.</p>
            </div>
          )}

          <div className="space-y-3">
            {submissoesAcademia.map((sub) => (
              <div key={sub.id} className="glass rounded-2xl p-4">
                <div className="flex items-center justify-between gap-2">
                  <p className="text-sm font-semibold text-slate-900">
                    {sub.recommendation_name ?? (sub.submission_type === 'video' ? 'Vídeo enviado' : 'Fotos enviadas')}
                  </p>
                  <span className="shrink-0 rounded-full bg-slate-900/5 px-2.5 py-1 text-[11px] font-medium text-slate-600">
                    {sub.status === 'completed' ? rotuloStatus[sub.approval_status ?? 'pending'] : rotuloStatus[sub.status]}
                  </span>
                </div>
                <p className="mt-1 text-xs text-slate-500">
                  {new Date(sub.created_at).toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' })}
                </p>
                {sub.status === 'failed' && (
                  <p className="mt-2 text-xs text-rose-500">Não foi possível analisar essa mídia. Tenta enviar de novo?</p>
                )}
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
        <FormCorrectionModal
          open={exercicioAnaliseForm !== null}
          onClose={() => setExercicioAnaliseForm(null)}
          token={token}
          exerciseId={exercicioAnaliseForm?.id ?? ''}
          exerciseName={exercicioAnaliseForm?.nome ?? ''}
          workoutId={data.workout?.id}
        />
      <main className="mx-auto w-full max-w-lg flex-1 px-4 py-6 pb-24">
        {erro && <p className="mb-4 text-sm text-rose-400">{erro}</p>}

        {/* Deliberadamente informativo, não alarmante: ficar sem sinal na
            academia é esperado, e o aluno precisa seguir treinando sabendo que
            não vai perder nada. Some sozinho quando a fila esvazia. */}
        {(!online || pendentes > 0) && (
          <div className="mb-4 flex items-start gap-2.5 rounded-2xl bg-slate-900/5 px-4 py-3 text-sm text-slate-600">
            <span aria-hidden className="mt-0.5">
              {online ? '↑' : '☁'}
            </span>
            <p>
              {online
                ? pendentes === 1
                  ? 'Enviando 1 registro que ficou salvo...'
                  : `Enviando ${pendentes} registros que ficaram salvos...`
                : 'Sem conexão — seus registros serão salvos e enviados quando a internet voltar.'}
            </p>
          </div>
        )}

        {data.workouts.length > 1 && (
          <div className="chat-scroll mb-4 flex gap-2 overflow-x-auto pb-1">
            {data.workouts.map((w) => (
              <button
                key={w.id}
                onClick={() => carregarPortal(w.id)}
                disabled={treinoEmAndamento && w.id !== data.workout?.id}
                className={`shrink-0 rounded-full px-4 py-2 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-40 ${
                  w.id === data.workout?.id
                    ? 'bg-gradient-to-r from-[#2648b3] to-[#8b7fd6] text-white'
                    : 'glass glass-hover text-slate-700'
                }`}
              >
                {w.name}
              </button>
            ))}
          </div>
        )}

        {!treinoEmAndamento && (
          <button onClick={iniciarTreino} className="btn-primary mb-6 w-full rounded-2xl px-4 py-4 text-base">
            Iniciar treino
          </button>
        )}

        {treinoEmAndamento && (
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

                        {sessionId && online && (
                          <button
                            onClick={() => setExercicioAnaliseForm({ id: ex.exercise_id, nome: ex.exercise_name })}
                            className="mt-3 rounded-lg bg-slate-900/5 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-900/10"
                          >
                            🎥 Analisar forma
                          </button>
                        )}

                        {treinoEmAndamento && !completo && (
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

                        {treinoEmAndamento && (
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

        {treinoEmAndamento && (
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
