'use client'

import { useCallback, useEffect, useRef, useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { Check, MessageCircle, Camera, ChevronLeft, ChevronRight, UserX, UserCheck } from 'lucide-react'
import Navbar from '@/components/Navbar'
import BackLink from '@/components/BackLink'
import Avatar from '@/components/Avatar'
import ChatBox from '@/components/ChatBox'
import WeightChart from '@/components/WeightChart'
import { api, API_URL, ApiError, fetchImagemAutenticada } from '@/lib/api'
import { ANAMNESE_VAZIA, anamneseTemConteudo, LOCAL_TREINO_OPCOES, normalizarAnamnese, OBJETIVOS_OPCOES } from '@/lib/anamnese'
import { ASPECTOS_PROGRESSO_OPCOES } from '@/lib/anamneseRevisao'
import { copiarTexto, linkWhatsApp, mensagemConvite } from '@/lib/compartilharLink'
import { formatarDataCurta, formatarDataLonga, nomeMes, primeiroDiaAno, primeiroDiaMes, somarDias } from '@/lib/checkinDates'
import { PAR_Q_VAZIO } from '@/lib/parq'
import {
  AlertaEstagnacao,
  Anamnese,
  BodyMeasurement,
  BodyPhoto,
  Gamificacao,
  HistoricoCheckins,
  Message,
  ParQAnswers,
  PosturalAssessment,
  WorkoutReview,
  ResumoCheckins,
  Student,
  Workout,
  MomentoRefeicao,
  NutricaoDoAluno,
} from '@/lib/types'

/** Uma linha de "pergunta: resposta" na anamnese — não mostra nada se a resposta
 * estiver vazia, pra não poluir a ficha do aluno com campos que ele deixou em branco. */
function LinhaAnamnese({ pergunta, resposta }: { pergunta: string; resposta: string | null | undefined }) {
  if (!resposta) return null
  return (
    <p className="text-sm text-ink-soft">
      <span className="text-ink-muted">{pergunta}:</span> {resposta}
    </p>
  )
}

/** Fotos de evolução física ficam atrás de rota autenticada (JWT) — não dá pra
 * usar <img src> direto, então busca o blob e mantém a object URL local. */
function FotoAutenticada({ src, alt }: { src: string; alt: string }) {
  const [url, setUrl] = useState<string | null>(null)

  useEffect(() => {
    let objectUrl: string | null = null
    let cancelado = false
    fetchImagemAutenticada(src).then((resultado) => {
      if (cancelado) {
        URL.revokeObjectURL(resultado)
        return
      }
      objectUrl = resultado
      setUrl(resultado)
    })
    return () => {
      cancelado = true
      if (objectUrl) URL.revokeObjectURL(objectUrl)
    }
  }, [src])

  if (!url) {
    return <div className="flex h-full w-full items-center justify-center bg-ink/5 text-xs text-ink-muted">Carregando...</div>
  }
  // eslint-disable-next-line @next/next/no-img-element -- imagem vem de rota autenticada, não do next/image
  return <img src={url} alt={alt} className="h-full w-full object-cover" />
}

const ROTULO_MOMENTO: Record<MomentoRefeicao, string> = {
  cafe: 'Café da manhã',
  lanche: 'Lanche',
  almoco: 'Almoço',
  jantar: 'Jantar',
  pre_treino: 'Pré-treino',
  pos_treino: 'Pós-treino',
}

/** Miniatura da refeição — mesma rota autenticada das fotos de evolução. */
function ImagemRefeicao({ src, alt }: { src: string; alt: string }) {
  return (
    <div className="h-12 w-12 shrink-0 overflow-hidden rounded-lg">
      <FotoAutenticada src={src} alt={alt} />
    </div>
  )
}

export default function AlunoDetalheClient({ studentId }: { studentId: string }) {
  const router = useRouter()
  const [student, setStudent] = useState<Student | null>(null)
  const [workouts, setWorkouts] = useState<Workout[]>([])
  const [measurements, setMeasurements] = useState<BodyMeasurement[]>([])
  const [gamificacao, setGamificacao] = useState<Gamificacao | null>(null)
  const [alertasEstagnacao, setAlertasEstagnacao] = useState<AlertaEstagnacao[]>([])
  const [fotosEvolucao, setFotosEvolucao] = useState<BodyPhoto[]>([])
  const [posturais, setPosturais] = useState<PosturalAssessment[]>([])
  const [revisoes, setRevisoes] = useState<WorkoutReview[]>([])
  const [resumoCheckins, setResumoCheckins] = useState<ResumoCheckins | null>(null)
  const [nutricao, setNutricao] = useState<NutricaoDoAluno | null>(null)
  const [periodoCheckins, setPeriodoCheckins] = useState<'week' | 'month' | 'year'>('week')
  const [refCheckins, setRefCheckins] = useState<string | null>(null)
  const [historicoCheckins, setHistoricoCheckins] = useState<HistoricoCheckins | null>(null)
  const [novoPeso, setNovoPeso] = useState('')
  const [novaCintura, setNovaCintura] = useState('')
  const [novoQuadril, setNovoQuadril] = useState('')
  const [novaGordura, setNovaGordura] = useState('')
  const [salvandoPeso, setSalvandoPeso] = useState(false)
  // Só leitura nesta tela agora (o card editável de PAR-Q foi removido por ficar
  // redundante com a anamnese completa) — mantido pra alimentar o aviso de "respondeu
  // sim a um item do PAR-Q" logo acima da anamnese.
  const [parQ, setParQ] = useState<ParQAnswers>(PAR_Q_VAZIO)
  const [birthDate, setBirthDate] = useState('')
  const [anamnese, setAnamnese] = useState<Anamnese>(ANAMNESE_VAZIA)
  const [messages, setMessages] = useState<Message[]>([])
  const [autopilot, setAutopilot] = useState(true)
  const [erro, setErro] = useState<string | null>(null)
  const [copiado, setCopiado] = useState(false)
  const [enviandoFoto, setEnviandoFoto] = useState(false)
  const [alterandoStatus, setAlterandoStatus] = useState(false)
  const fotoInputRef = useRef<HTMLInputElement | null>(null)

  // Evita empilhar requisições do polling: se o GET anterior ainda não voltou
  // (rede lenta) quando o próximo setInterval dispara, pula esse ciclo em vez
  // de deixar duas respostas em voo — a mais lenta poderia resolver por último
  // e sobrescrever o estado com dado desatualizado.
  const carregandoMensagensRef = useRef(false)
  const carregarMensagens = useCallback(() => {
    if (carregandoMensagensRef.current) return
    carregandoMensagensRef.current = true
    api
      .get<{ messages: Message[]; ai_autopilot: boolean }>(`/alunos/${studentId}/mensagens`)
      .then((data) => {
        setMessages(data.messages)
        setAutopilot(data.ai_autopilot)
      })
      .catch(() => {})
      .finally(() => {
        carregandoMensagensRef.current = false
      })
  }, [studentId])

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    api
      .get<{
        student: Student
        workouts: Workout[]
        measurements: BodyMeasurement[]
        gamificacao: Gamificacao
        alertasEstagnacao: AlertaEstagnacao[]
      }>(`/alunos/${studentId}`)
      .then((data) => {
        setStudent(data.student)
        setWorkouts(data.workouts)
        setMeasurements(data.measurements)
        setGamificacao(data.gamificacao)
        setAlertasEstagnacao(data.alertasEstagnacao ?? [])
        setAutopilot(data.student.ai_autopilot)
        setParQ(data.student.par_q_answers ?? PAR_Q_VAZIO)
        setBirthDate(data.student.birth_date ?? '')
        setAnamnese(normalizarAnamnese(data.student.anamnese))
      })
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar aluno'))

    carregarMensagens()
    const intervalo = setInterval(carregarMensagens, 5000)

    api
      .get<{ photos: BodyPhoto[] }>(`/alunos/${studentId}/body-photos`)
      .then((data) => setFotosEvolucao(data.photos))
      .catch(() => {})

    api
      .get<{ assessments: PosturalAssessment[] }>(`/alunos/${studentId}/postural`)
      .then((data) => setPosturais(data.assessments))
      .catch(() => {})

    api
      .get<{ reviews: WorkoutReview[] }>(`/alunos/${studentId}/revisoes`)
      .then((data) => setRevisoes(data.reviews))
      .catch(() => {})

    api
      .get<ResumoCheckins>(`/alunos/${studentId}/checkins/summary`)
      .then(setResumoCheckins)
      .catch(() => {})

    api
      .get<NutricaoDoAluno>(`/alunos/${studentId}/nutricao`)
      .then(setNutricao)
      .catch(() => {})

    return () => clearInterval(intervalo)
  }, [studentId, router, carregarMensagens])

  const carregarHistoricoCheckins = useCallback(
    (period: 'week' | 'month' | 'year', ref: string | null) => {
      const params = new URLSearchParams({ period })
      if (ref) params.set('ref', ref)
      api
        .get<HistoricoCheckins>(`/alunos/${studentId}/checkins?${params.toString()}`)
        .then(setHistoricoCheckins)
        .catch(() => {})
    },
    [studentId]
  )

  useEffect(() => {
    carregarHistoricoCheckins(periodoCheckins, refCheckins)
  }, [periodoCheckins, refCheckins, carregarHistoricoCheckins])

  function irParaPeriodoCheckins(direcao: -1 | 1) {
    if (periodoCheckins === 'week') {
      const base = historicoCheckins?.semana?.inicio ?? resumoCheckins?.semana.inicio
      if (base) setRefCheckins(somarDias(base, direcao * 7))
    } else if (periodoCheckins === 'month') {
      const base = historicoCheckins?.mes ?? resumoCheckins?.mes
      if (base) setRefCheckins(primeiroDiaMes(base.ano, base.mes + direcao))
    } else {
      const base = historicoCheckins?.ano ?? resumoCheckins?.ano
      if (base) setRefCheckins(primeiroDiaAno(base.ano + direcao))
    }
  }

  async function enviarMensagem(texto: string) {
    const { message } = await api.post<{ message: Message }>(`/alunos/${studentId}/mensagens`, { content: texto })
    setMessages((prev) => [...prev, message])
  }

  async function registrarPeso(e: React.FormEvent) {
    e.preventDefault()
    if (!novoPeso) return
    setSalvandoPeso(true)
    try {
      const { measurement } = await api.post<{ measurement: BodyMeasurement }>(`/alunos/${studentId}/medicoes`, {
        weight_kg: Number(novoPeso),
        waist_cm: novaCintura ? Number(novaCintura) : undefined,
        hip_cm: novoQuadril ? Number(novoQuadril) : undefined,
        body_fat_pct: novaGordura ? Number(novaGordura) : undefined,
      })
      setMeasurements((prev) => [...prev, measurement])
      setNovoPeso('')
      setNovaCintura('')
      setNovoQuadril('')
      setNovaGordura('')
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao registrar medição')
    } finally {
      setSalvandoPeso(false)
    }
  }

  async function enviarFoto(file: File) {
    setEnviandoFoto(true)
    setErro(null)
    try {
      const formData = new FormData()
      formData.append('foto', file)
      const { student: atualizado } = await api.postFile<{ student: Student }>(`/alunos/${studentId}/foto`, formData)
      setStudent(atualizado)
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao enviar foto')
    } finally {
      setEnviandoFoto(false)
    }
  }

  // Desvincular tira o acesso do aluno ao portal na hora e libera a vaga no
  // limite de alunos do plano de assinatura (ver App\Support\Assinatura no
  // backend) — não apaga nada, o histórico continua na ficha, e reativar
  // devolve o acesso normalmente.
  async function alternarStatus() {
    if (!student) return
    const ativar = student.status === 'inactive'
    if (
      !ativar &&
      !confirm(
        `Desvincular ${student.name.split(' ')[0]}? O aluno perde o acesso ao portal e deixa de contar no limite de alunos do seu plano. O histórico dele continua salvo e você pode reativar quando quiser.`
      )
    ) {
      return
    }
    setAlterandoStatus(true)
    setErro(null)
    try {
      const { student: atualizado } = await api.patch<{ student: Student }>(`/alunos/${studentId}/status`, {
        ativo: ativar,
      })
      setStudent(atualizado)
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao atualizar status do aluno')
    } finally {
      setAlterandoStatus(false)
    }
  }

  async function alternarAutopilot() {
    const novo = !autopilot
    setAutopilot(novo)
    try {
      await api.patch(`/alunos/${studentId}/autopilot`, { enabled: novo })
    } catch {
      setAutopilot(!novo) // reverte se falhar
    }
  }

  if (erro) {
    return (
      <>
        <Navbar />
        <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8">
          <p className="text-sm text-danger">{erro}</p>
        </main>
      </>
    )
  }

  if (!student) {
    return (
      <>
        <Navbar />
        <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8">
          <p className="text-ink-muted">Carregando...</p>
        </main>
      </>
    )
  }

  const inviteLink = typeof window !== 'undefined' ? `${window.location.origin}/aluno/${student.invite_token}` : ''

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8">
        <BackLink href="/dashboard" label="Voltar ao painel" />

        <div className="mb-6 flex items-start gap-4">
          <div className="group relative shrink-0">
            <Avatar nome={student.name} fotoUrl={student.photo_url} tamanho="lg" />
            <button
              onClick={() => fotoInputRef.current?.click()}
              disabled={enviandoFoto}
              title="Enviar foto do aluno"
              className="absolute -bottom-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full bg-white text-xs shadow ring-1 ring-black/10 transition hover:bg-surface-soft"
            >
              {enviandoFoto ? '…' : <Camera size={12} strokeWidth={2} />}
            </button>
            <input
              ref={fotoInputRef}
              type="file"
              accept="image/*"
              className="hidden"
              onChange={(e) => {
                const file = e.target.files?.[0]
                if (file) enviarFoto(file)
                e.target.value = ''
              }}
            />
          </div>
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2">
              <h1 className="truncate font-display text-2xl font-bold tracking-tight text-ink">{student.name}</h1>
              {student.status === 'inactive' && (
                <span className="shrink-0 rounded-full bg-ink/8 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-ink-muted">
                  Desvinculado
                </span>
              )}
            </div>
            {(student.weight_kg || student.height_cm) && (
              <p className="mt-0.5 text-sm text-ink-muted">
                {student.weight_kg ? `${student.weight_kg} kg` : null}
                {student.weight_kg && student.height_cm ? ' · ' : null}
                {student.height_cm ? `${student.height_cm} cm` : null}
              </p>
            )}
            <p className="mt-1 whitespace-pre-wrap text-sm text-ink-muted">
              {student.objective || 'Sem objetivo definido'}
            </p>
          </div>
          <div className="hidden shrink-0 gap-2 sm:flex">
            <Link
              href={`/alunos/${studentId}/editar`}
              className="glass glass-hover rounded-xl px-4 py-2.5 text-sm text-ink-soft"
            >
              Editar
            </Link>
            <button
              onClick={async () => {
                if (!(await copiarTexto(inviteLink))) {
                  setErro('Não consegui copiar o link. Toque nele e copie na mão.')
                  return
                }
                setCopiado(true)
                setTimeout(() => setCopiado(false), 2000)
              }}
              className="glass glass-hover flex items-center gap-1.5 rounded-xl px-4 py-2.5 text-sm text-ink-soft"
            >
              {copiado ? (
                <>
                  <Check size={15} className="text-success" /> Link copiado
                </>
              ) : (
                'Copiar link'
              )}
            </button>
            <a
              href={linkWhatsApp(student.phone, mensagemConvite(student.name, inviteLink))}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-1.5 rounded-xl bg-[#25D366] px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90"
            >
              <MessageCircle size={16} />
              WhatsApp
            </a>
            <button
              onClick={alternarStatus}
              disabled={alterandoStatus}
              className="glass glass-hover flex items-center gap-1.5 rounded-xl px-4 py-2.5 text-sm text-ink-soft"
            >
              {student.status === 'inactive' ? (
                <>
                  <UserCheck size={15} className="text-success" /> Reativar
                </>
              ) : (
                <>
                  <UserX size={15} className="text-danger" /> Desvincular
                </>
              )}
            </button>
          </div>
        </div>

        {resumoCheckins && (
          <section className="glass mb-6 rounded-2xl p-4">
            <h2 className="mb-3 font-semibold text-ink">Check-in</h2>
            <div className="flex flex-wrap items-center gap-5">
              <div>
                <p className="text-xs uppercase tracking-wider text-ink-muted">Semana</p>
                <p className="text-lg font-bold text-ink">
                  {resumoCheckins.semana.dias_com_checkin}
                  <span className="text-sm font-normal text-ink-muted">/{resumoCheckins.semana.total_dias}</span>
                </p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-wider text-ink-muted">Mês</p>
                <p className="text-lg font-bold text-ink">
                  {resumoCheckins.mes.dias_com_checkin}
                  <span className="text-sm font-normal text-ink-muted">/{resumoCheckins.mes.total_dias_mes}</span>
                </p>
              </div>
              <div>
                <p className="text-xs uppercase tracking-wider text-ink-muted">Ano</p>
                <p className="text-lg font-bold text-ink">{resumoCheckins.ano.dias_com_checkin}</p>
              </div>
              <div className="flex gap-1.5">
                {resumoCheckins.semana.grid.map((d) => (
                  <span
                    key={d.date}
                    title={d.comment ? `${d.label}: ${d.comment}` : d.label}
                    className={`h-2.5 w-2.5 rounded-full ${d.checked ? 'bg-success' : 'bg-ink/10'}`}
                  />
                ))}
              </div>
            </div>
          </section>
        )}

        <section className="glass mb-6 rounded-2xl p-4">
          <div className="mb-3 flex items-center justify-between">
            <div className="flex gap-1 rounded-lg bg-ink/5 p-1">
              <button
                onClick={() => {
                  setPeriodoCheckins('week')
                  setRefCheckins(null)
                }}
                className={`rounded-md px-3 py-1 text-xs font-medium transition ${
                  periodoCheckins === 'week' ? 'bg-white text-ink shadow' : 'text-ink-muted'
                }`}
              >
                Semana
              </button>
              <button
                onClick={() => {
                  setPeriodoCheckins('month')
                  setRefCheckins(null)
                }}
                className={`rounded-md px-3 py-1 text-xs font-medium transition ${
                  periodoCheckins === 'month' ? 'bg-white text-ink shadow' : 'text-ink-muted'
                }`}
              >
                Mês
              </button>
              <button
                onClick={() => {
                  setPeriodoCheckins('year')
                  setRefCheckins(null)
                }}
                className={`rounded-md px-3 py-1 text-xs font-medium transition ${
                  periodoCheckins === 'year' ? 'bg-white text-ink shadow' : 'text-ink-muted'
                }`}
              >
                Ano
              </button>
            </div>
            <div className="flex items-center gap-1">
              <button
                onClick={() => irParaPeriodoCheckins(-1)}
                aria-label="Período anterior"
                className="flex h-7 w-7 items-center justify-center rounded-lg text-ink-muted transition hover:bg-ink/5"
              >
                <ChevronLeft size={16} />
              </button>
              {refCheckins && (
                <button onClick={() => setRefCheckins(null)} className="px-1 text-xs font-medium text-brand">
                  Hoje
                </button>
              )}
              <button
                onClick={() => irParaPeriodoCheckins(1)}
                aria-label="Próximo período"
                className="flex h-7 w-7 items-center justify-center rounded-lg text-ink-muted transition hover:bg-ink/5"
              >
                <ChevronRight size={16} />
              </button>
            </div>
          </div>

          {historicoCheckins?.period === 'week' && historicoCheckins.semana && (
            <p className="mb-3 text-xs text-ink-muted">
              {formatarDataCurta(historicoCheckins.semana.inicio)} a {formatarDataCurta(historicoCheckins.semana.fim)} ·{' '}
              {historicoCheckins.semana.dias_com_checkin} de {historicoCheckins.semana.total_dias} dias
            </p>
          )}
          {historicoCheckins?.period === 'month' && historicoCheckins.mes && (
            <p className="mb-3 text-xs text-ink-muted">
              {nomeMes(historicoCheckins.mes.mes)} de {historicoCheckins.mes.ano} · {historicoCheckins.mes.dias_com_checkin} dias treinados
            </p>
          )}
          {historicoCheckins?.period === 'year' && historicoCheckins.ano && (
            <p className="mb-3 text-xs text-ink-muted">
              {historicoCheckins.ano.dias_com_checkin} dias treinados em {historicoCheckins.ano.ano}
            </p>
          )}

          {historicoCheckins && historicoCheckins.fotos.length === 0 && (
            <p className="text-sm text-ink-muted">Nenhum check-in registrado nesse período.</p>
          )}

          {historicoCheckins && historicoCheckins.fotos.length > 0 && (
            <div className="grid grid-cols-3 gap-2 sm:grid-cols-4">
              {historicoCheckins.fotos.map((foto) => (
                <div key={foto.id} className="overflow-hidden rounded-xl">
                  <div className="aspect-square">
                    <FotoAutenticada
                      src={`/alunos/${studentId}/checkins/${foto.id}/imagem`}
                      alt="Foto do check-in"
                    />
                  </div>
                  <p className="mt-1 truncate text-[10px] text-ink-muted">{formatarDataLonga(foto.checkin_date)}</p>
                  {foto.comment && <p className="line-clamp-2 text-[11px] text-ink-soft">{foto.comment}</p>}
                </div>
              ))}
            </div>
          )}
        </section>

        {gamificacao && (
          <section className="glass mb-6 flex flex-wrap items-center gap-5 rounded-2xl p-4">
            <div>
              <p className="text-xs uppercase tracking-wider text-ink-muted">Sequência</p>
              <p className="text-lg font-bold text-ink">
                {gamificacao.streak > 0 ? `${gamificacao.streak} dia${gamificacao.streak === 1 ? '' : 's'}` : '—'}
              </p>
            </div>
            <div>
              <p className="text-xs uppercase tracking-wider text-ink-muted">Treinos concluídos</p>
              <p className="text-lg font-bold text-ink">{gamificacao.total_sessoes}</p>
            </div>
            {gamificacao.badges.length > 0 && (
              <div className="flex-1">
                <p className="mb-1 text-xs uppercase tracking-wider text-ink-muted">Medalhas</p>
                <div className="flex flex-wrap gap-2">
                  {gamificacao.badges.map((b) => (
                    <span
                      key={b.id}
                      title={b.label}
                      className="flex items-center gap-1 rounded-full bg-ink/5 px-2.5 py-1 text-sm"
                    >
                      {b.emoji} <span className="text-xs text-ink-soft">{b.label}</span>
                    </span>
                  ))}
                </div>
              </div>
            )}
          </section>
        )}

        {alertasEstagnacao.length > 0 && (
          <section className="glass mb-6 rounded-2xl border border-warning bg-warning p-4">
            <p className="mb-2 text-sm font-semibold text-warning">
              Sem aumento de carga nas duas últimas sessões
            </p>
            <div className="flex flex-wrap gap-2">
              {alertasEstagnacao.map((a) => (
                <span
                  key={a.exercise_id}
                  className="rounded-lg bg-white px-2.5 py-1 text-xs text-warning"
                  title={`Última: ${a.ultima}kg · Anterior: ${a.anterior}kg`}
                >
                  {a.exercise_name} ({a.ultima}kg, era {a.anterior}kg)
                </span>
              ))}
            </div>
          </section>
        )}

        <section className="mb-6">
          <h2 className="mb-3 font-semibold text-ink">Avaliação física</h2>

          {Object.values(parQ).some(Boolean) && (
            <div className="mb-4 rounded-2xl border border-warning/30 bg-warning-soft px-5 py-3 text-sm text-warning">
              Atenção: {student.name.split(' ')[0]} respondeu <strong>sim</strong> a um ou mais itens do PAR-Q —
              recomende avaliação médica antes de seguir com o treino.
            </div>
          )}

          <div className="grid gap-4">
            {/* Medidas */}
            <div className="glass rounded-2xl p-5">
              <h3 className="mb-3 text-sm font-semibold text-ink">Medidas</h3>
              <WeightChart pontos={measurements} />
              <form onSubmit={registrarPeso} className="mt-4 grid grid-cols-2 gap-2">
                <input
                  type="number"
                  step="0.1"
                  min={0}
                  inputMode="decimal"
                  placeholder="Peso (kg)"
                  value={novoPeso}
                  onChange={(e) => setNovoPeso(e.target.value)}
                  className="input-dark rounded-xl px-3 py-2 text-sm"
                />
                <input
                  type="number"
                  step="0.1"
                  min={0}
                  inputMode="decimal"
                  placeholder="Cintura (cm)"
                  value={novaCintura}
                  onChange={(e) => setNovaCintura(e.target.value)}
                  className="input-dark rounded-xl px-3 py-2 text-sm"
                />
                <input
                  type="number"
                  step="0.1"
                  min={0}
                  inputMode="decimal"
                  placeholder="Quadril (cm)"
                  value={novoQuadril}
                  onChange={(e) => setNovoQuadril(e.target.value)}
                  className="input-dark rounded-xl px-3 py-2 text-sm"
                />
                <input
                  type="number"
                  step="0.1"
                  min={0}
                  inputMode="decimal"
                  placeholder="% Gordura"
                  value={novaGordura}
                  onChange={(e) => setNovaGordura(e.target.value)}
                  className="input-dark rounded-xl px-3 py-2 text-sm"
                />
                <button
                  type="submit"
                  disabled={salvandoPeso || !novoPeso}
                  className="btn-primary col-span-2 rounded-xl px-4 py-2 text-sm"
                >
                  {salvandoPeso ? 'Salvando...' : 'Registrar medição'}
                </button>
              </form>
            </div>
          </div>
        </section>

        {(birthDate || anamneseTemConteudo(anamnese)) && (
          <section className="mb-6">
            <h2 className="mb-3 font-semibold text-ink">Anamnese completa</h2>
            <div className="glass grid gap-x-6 gap-y-4 rounded-2xl p-5 sm:grid-cols-2">
              {birthDate && (
                <div>
                  <h4 className="mb-1 text-xs font-semibold uppercase tracking-wider text-ink-muted">Dados pessoais</h4>
                  <LinhaAnamnese pergunta="Data de nascimento" resposta={new Date(`${birthDate}T00:00:00`).toLocaleDateString('pt-BR')} />
                </div>
              )}

              <div>
                <h4 className="mb-1 text-xs font-semibold uppercase tracking-wider text-ink-muted">
                  Histórico de atividade física
                </h4>
                <LinhaAnamnese pergunta="Já praticou" resposta={anamnese.historico_atividade_fisica.ja_praticou} />
                <LinhaAnamnese pergunta="Pratica atualmente" resposta={anamnese.historico_atividade_fisica.pratica_atualmente} />
                <LinhaAnamnese pergunta="Gosta de" resposta={anamnese.historico_atividade_fisica.modalidades_favoritas} />
                <LinhaAnamnese pergunta="Não gosta de" resposta={anamnese.historico_atividade_fisica.modalidades_nao_gosta} />
                <LinhaAnamnese
                  pergunta="Já treinou com personal"
                  resposta={
                    anamnese.historico_atividade_fisica.treinou_com_personal === null
                      ? null
                      : anamnese.historico_atividade_fisica.treinou_com_personal
                        ? 'Sim'
                        : 'Não'
                  }
                />
              </div>

              <div>
                <h4 className="mb-1 text-xs font-semibold uppercase tracking-wider text-ink-muted">Objetivos</h4>
                <LinhaAnamnese
                  pergunta="Selecionados"
                  resposta={anamnese.objetivos.selecionados
                    .map((v) => OBJETIVOS_OPCOES.find((o) => o.valor === v)?.label ?? v)
                    .join(', ')}
                />
                <LinhaAnamnese pergunta="Outro" resposta={anamnese.objetivos.outro} />
                <LinhaAnamnese pergunta="Prazo desejado" resposta={anamnese.objetivos.prazo} />
              </div>

              <div>
                <h4 className="mb-1 text-xs font-semibold uppercase tracking-wider text-ink-muted">
                  Condições de saúde
                </h4>
                <LinhaAnamnese pergunta="Restrição médica" resposta={anamnese.condicoes_saude.restricao_medica} />
                <LinhaAnamnese pergunta="Doença diagnosticada" resposta={anamnese.condicoes_saude.doenca_diagnosticada} />
                <LinhaAnamnese pergunta="Lesão" resposta={anamnese.condicoes_saude.lesao} />
                <LinhaAnamnese pergunta="Medicamentos" resposta={anamnese.condicoes_saude.medicamentos} />
                <LinhaAnamnese pergunta="Suplementos" resposta={anamnese.condicoes_saude.suplementos} />
                <LinhaAnamnese pergunta="Alergias" resposta={anamnese.condicoes_saude.alergias} />
              </div>

              <div>
                <h4 className="mb-1 text-xs font-semibold uppercase tracking-wider text-ink-muted">Estilo de vida</h4>
                <LinhaAnamnese pergunta="Profissão" resposta={anamnese.estilo_de_vida.profissao} />
                <LinhaAnamnese pergunta="Nível de estresse" resposta={anamnese.estilo_de_vida.nivel_estresse} />
                <LinhaAnamnese
                  pergunta="Sono"
                  resposta={
                    anamnese.estilo_de_vida.qualidade_sono
                      ? `${anamnese.estilo_de_vida.qualidade_sono}${anamnese.estilo_de_vida.horas_sono ? ` · ${anamnese.estilo_de_vida.horas_sono}h/noite` : ''}`
                      : null
                  }
                />
                <LinhaAnamnese pergunta="Alimentação" resposta={anamnese.estilo_de_vida.alimentacao} />
                <LinhaAnamnese pergunta="Plano alimentar" resposta={anamnese.estilo_de_vida.plano_alimentar} />
                <LinhaAnamnese pergunta="Álcool" resposta={anamnese.estilo_de_vida.frequencia_alcool} />
                <LinhaAnamnese
                  pergunta="Fumante"
                  resposta={
                    anamnese.estilo_de_vida.fumante === null
                      ? null
                      : anamnese.estilo_de_vida.fumante
                        ? `Sim${anamnese.estilo_de_vida.tempo_fumante ? ` (${anamnese.estilo_de_vida.tempo_fumante})` : ''}`
                        : 'Não'
                  }
                />
              </div>

              <div>
                <h4 className="mb-1 text-xs font-semibold uppercase tracking-wider text-ink-muted">
                  Motivação e preferências
                </h4>
                <LinhaAnamnese pergunta="Motivação" resposta={anamnese.motivacao.motivacao} />
                <LinhaAnamnese pergunta="Obstáculos" resposta={anamnese.motivacao.obstaculos} />
                <LinhaAnamnese
                  pergunta="Prefere treinos"
                  resposta={
                    anamnese.motivacao.preferencia_intensidade === 'curtos_intensos'
                      ? 'Curtos e intensos'
                      : anamnese.motivacao.preferencia_intensidade === 'longos_moderados'
                        ? 'Longos e moderados'
                        : null
                  }
                />
                <LinhaAnamnese
                  pergunta="Prefere treinar"
                  resposta={
                    anamnese.motivacao.preferencia_companhia === 'sozinho'
                      ? 'Sozinho'
                      : anamnese.motivacao.preferencia_companhia === 'grupo'
                        ? 'Em grupo'
                        : anamnese.motivacao.preferencia_companhia === 'acompanhamento'
                          ? 'Com acompanhamento constante'
                          : null
                  }
                />
                <LinhaAnamnese pergunta="Melhor horário" resposta={anamnese.motivacao.horario_disponivel} />
              </div>

              <div>
                <h4 className="mb-1 text-xs font-semibold uppercase tracking-wider text-ink-muted">Disponibilidade</h4>
                <LinhaAnamnese pergunta="Vezes por semana" resposta={anamnese.disponibilidade.vezes_por_semana} />
                <LinhaAnamnese pergunta="Tempo por treino" resposta={anamnese.disponibilidade.tempo_por_treino} />
                <LinhaAnamnese
                  pergunta="Onde treina"
                  resposta={anamnese.disponibilidade.local_treino
                    .map((v) => LOCAL_TREINO_OPCOES.find((o) => o.valor === v)?.label ?? v)
                    .join(', ')}
                />
              </div>

              {anamnese.historico_familiar && (
                <div className="sm:col-span-2">
                  <h4 className="mb-1 text-xs font-semibold uppercase tracking-wider text-ink-muted">
                    Histórico familiar
                  </h4>
                  <p className="text-sm text-ink-soft">{anamnese.historico_familiar}</p>
                </div>
              )}
            </div>
          </section>
        )}

        {fotosEvolucao.length > 0 && (
          <section className="mb-6">
            <h2 className="mb-3 font-semibold text-ink">Evolução (fotos)</h2>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
              {fotosEvolucao.map((foto) => (
                <div key={foto.id} className="glass overflow-hidden rounded-2xl">
                  <div className="aspect-square">
                    <FotoAutenticada src={`/alunos/${studentId}/body-photos/${foto.id}/imagem`} alt="Foto de evolução do aluno" />
                  </div>
                  <div className="p-3">
                    <p className="mb-1.5 text-[10px] uppercase tracking-wider text-ink-muted">
                      {new Date(foto.taken_at).toLocaleDateString('pt-BR')}{' '}
                      {new Date(foto.taken_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                    </p>
                    {foto.ai_feedback && <p className="line-clamp-3 text-xs text-ink-soft">{foto.ai_feedback}</p>}
                  </div>
                </div>
              ))}
            </div>
          </section>
        )}

        {posturais.length > 0 && (
          <section className="mb-6">
            <h2 className="mb-3 font-semibold text-ink">Avaliação postural</h2>
            <div className="space-y-3">
              {posturais.map((avaliacao) => (
                <div key={avaliacao.id} className="glass overflow-hidden rounded-2xl">
                  <div className="grid grid-cols-3 gap-0.5">
                    {(['frente', 'lado', 'costas'] as const).map((angulo) => (
                      <div key={angulo} className="aspect-[3/4]">
                        <FotoAutenticada
                          src={`/alunos/${studentId}/postural/${avaliacao.id}/imagem/${angulo}`}
                          alt={`Avaliação postural - ${angulo}`}
                        />
                      </div>
                    ))}
                  </div>
                  <div className="p-3">
                    <p className="mb-1.5 text-[10px] uppercase tracking-wider text-ink-muted">
                      {new Date(avaliacao.taken_at).toLocaleDateString('pt-BR')}
                    </p>
                    {avaliacao.ai_feedback && <p className="text-xs text-ink-soft">{avaliacao.ai_feedback}</p>}
                  </div>
                </div>
              ))}
            </div>
          </section>
        )}

        {revisoes.length > 0 && (
          <section className="mb-6">
            <h2 className="mb-3 font-semibold text-ink">Revisões de treino</h2>
            <div className="space-y-3">
              {revisoes.map((rev) => (
                <div key={rev.id} className="glass rounded-2xl p-5">
                  <div className="mb-3 flex items-center justify-between">
                    <p className="font-semibold text-ink">{rev.workout_name}</p>
                    <p className="text-xs text-ink-muted">
                      {new Date(rev.created_at).toLocaleDateString('pt-BR')}
                      {rev.tempo_acompanhamento_semanas !== null && ` · ${rev.tempo_acompanhamento_semanas} semanas de acompanhamento`}
                    </p>
                  </div>
                  <div className="space-y-1.5 text-sm text-ink-soft">
                    <LinhaAnamnese
                      pergunta="Avaliação"
                      resposta={
                        ({ excelente: 'Excelente', boa: 'Boa', regular: 'Regular', ruim: 'Ruim' } as Record<string, string>)[
                          rev.respostas.avaliacao_treino
                        ]
                      }
                    />
                    <LinhaAnamnese pergunta="Gostou mais de" resposta={rev.respostas.gostou_mais} />
                    <LinhaAnamnese pergunta="Não gostou de" resposta={rev.respostas.nao_gostou} />
                    <LinhaAnamnese
                      pergunta="Evolução percebida"
                      resposta={
                        (
                          {
                            sim_bastante: 'Sim, bastante',
                            sim_poderia_mais: 'Sim, mas poderia ser mais',
                            pouco: 'Pouco',
                            ainda_nao: 'Ainda não',
                          } as Record<string, string>
                        )[rev.respostas.percebeu_evolucao]
                      }
                    />
                    <LinhaAnamnese
                      pergunta="Maior progresso em"
                      resposta={rev.respostas.aspectos_progresso
                        .map((v) => ASPECTOS_PROGRESSO_OPCOES.find((o) => o.valor === v)?.label ?? v)
                        .concat(rev.respostas.aspectos_progresso_outro ? [rev.respostas.aspectos_progresso_outro] : [])
                        .join(', ')}
                    />
                    <LinhaAnamnese
                      pergunta="Manteve a frequência"
                      resposta={
                        ({ sim: 'Sim', parcialmente: 'Parcialmente', nao: 'Não' } as Record<string, string>)[
                          rev.respostas.manteve_frequencia
                        ]
                      }
                    />
                    <LinhaAnamnese pergunta="Treinos por semana" resposta={rev.respostas.treinos_por_semana} />
                    <LinhaAnamnese pergunta="Dificuldade na rotina" resposta={rev.respostas.dificuldade_rotina} />
                    <LinhaAnamnese pergunta="Sugestão de melhoria" resposta={rev.respostas.sugestao_melhoria} />
                    <LinhaAnamnese pergunta="Quer incluir" resposta={rev.respostas.sugestao_modalidade} />
                    <LinhaAnamnese pergunta="Sugestão geral" resposta={rev.respostas.sugestao_geral} />
                  </div>
                </div>
              ))}
            </div>
          </section>
        )}

        {/* Alimentação: só leitura. O personal olha o padrão e orienta de forma
            geral — montar cardápio é privativo do nutricionista. */}
        <section className="mb-6">
          <h2 className="mb-3 font-semibold text-ink">Alimentação</h2>

          {!nutricao && <p className="text-sm text-ink-muted">Carregando...</p>}

          {nutricao && nutricao.refeicoes.length === 0 && nutricao.sugestoes.length === 0 && (
            <div className="glass rounded-2xl p-6 text-center text-sm text-ink-muted">
              O aluno ainda não registrou nada. Ele registra pelo portal, na aba Alimentação.
            </div>
          )}

          {nutricao && (nutricao.refeicoes.length > 0 || nutricao.sugestoes.length > 0) && (
            <div className="grid gap-4 md:grid-cols-2">
              <div className="glass rounded-2xl p-4">
                <h3 className="mb-3 text-sm font-semibold text-ink">Últimos 7 dias</h3>

                {nutricao.agua.length > 0 && (
                  <p className="mb-3 text-xs text-ink-muted">
                    Água:{' '}
                    {nutricao.agua
                      .slice(0, 7)
                      .map((a) => `${formatarDataCurta(a.data)} ${a.copos}`)
                      .join(' · ')}{' '}
                    copos
                  </p>
                )}

                {nutricao.refeicoes.length === 0 && (
                  <p className="text-sm text-ink-muted">Nenhuma refeição registrada no período.</p>
                )}

                <div className="space-y-2">
                  {nutricao.refeicoes.slice(0, 12).map((r) => (
                    <div key={r.id} className="flex items-start gap-2.5">
                      {r.tem_foto && (
                        <ImagemRefeicao
                          src={`${API_URL}/alunos/${student.id}/nutricao/refeicoes/${r.id}/imagem`}
                          alt={ROTULO_MOMENTO[r.momento]}
                        />
                      )}
                      <div className="min-w-0 flex-1">
                        <p className="text-[10px] font-semibold uppercase tracking-wide text-ink-muted">
                          {r.data ? formatarDataCurta(r.data) : ''} · {ROTULO_MOMENTO[r.momento]}
                        </p>
                        {r.descricao && <p className="truncate text-sm text-ink-soft">{r.descricao}</p>}
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              <div className="glass rounded-2xl p-4">
                <h3 className="mb-1 text-sm font-semibold text-ink">Orientações que o app deu</h3>
                <p className="mb-3 text-xs text-ink-muted">
                  Você vê aqui tudo que a IA respondeu ao aluno sobre alimentação.
                </p>

                {nutricao.sugestoes.length === 0 && (
                  <p className="text-sm text-ink-muted">O aluno ainda não pediu nenhuma orientação.</p>
                )}

                <div className="space-y-2">
                  {nutricao.sugestoes.slice(0, 5).map((sg) => (
                    <div
                      key={sg.id}
                      className={`rounded-xl p-3 text-sm ${
                        sg.encaminhou_nutricionista ? 'bg-warning-soft text-ink-soft' : 'glass-flat text-ink-soft'
                      }`}
                    >
                      <p className="mb-1 text-[10px] font-semibold uppercase tracking-wide text-ink-muted">
                        {sg.momento === 'pre_treino' ? 'Antes de treinar' : 'Depois de treinar'}
                        {sg.encaminhou_nutricionista && ' · encaminhado ao nutricionista'}
                      </p>
                      {sg.resposta}
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}
        </section>

        <div className="grid gap-6 lg:grid-cols-2">
          {/* Coluna: treinos */}
          <section>
            <div className="mb-4 flex items-center justify-between">
              <h2 className="font-semibold text-ink">Treinos</h2>
              <Link href={`/treinos/novo?aluno=${student.id}`} className="btn-cta rounded-xl px-4 py-2 text-sm">
                + Novo treino
              </Link>
            </div>

            {workouts.length === 0 && (
              <div className="glass rounded-2xl p-8 text-center text-sm text-ink-muted">
                Nenhum treino criado ainda.
              </div>
            )}

            <div className="grid gap-3">
              {workouts.map((w) => (
                <Link
                  key={w.id}
                  href={`/treinos/${w.id}`}
                  className="glass glass-hover flex items-center justify-between rounded-2xl px-5 py-4"
                >
                  <div>
                    <p className="font-semibold text-ink">{w.name}</p>
                    {w.expires_at && (
                      <p className="mt-0.5 text-xs text-ink-muted">
                        Vence em {new Date(`${w.expires_at}T00:00:00`).toLocaleDateString('pt-BR')}
                      </p>
                    )}
                  </div>
                  <div className="flex items-center gap-2">
                    {w.archived_at && (
                      <span className="rounded-full bg-warning/15 px-2.5 py-1 text-xs font-medium text-warning">
                        Arquivado
                      </span>
                    )}
                    <span
                      className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                        w.status === 'sent'
                          ? 'bg-success/15 text-success'
                          : 'bg-ink/6 text-ink-muted'
                      }`}
                    >
                      {w.status === 'sent' ? 'Enviado' : 'Rascunho'}
                    </span>
                  </div>
                </Link>
              ))}
            </div>
          </section>

          {/* Coluna: chat */}
          <section id="conversa">
            <div className="mb-4 flex items-center justify-between">
              <h2 className="font-semibold text-ink">Conversa</h2>
              <button
                onClick={alternarAutopilot}
                className={`flex items-center gap-2 rounded-full px-3.5 py-1.5 text-xs font-medium transition ${
                  autopilot
                    ? 'border border-accent/30 bg-accent/10 text-accent-deep'
                    : 'border border-black/10 bg-black/4 text-ink-muted'
                }`}
                title="Quando ligado, a IA responde o aluno automaticamente como assistente do personal"
              >
                <span
                  className={`h-2 w-2 rounded-full ${autopilot ? 'bg-accent' : 'bg-ink-soft'}`}
                />
                Coach IA {autopilot ? 'ligado' : 'desligado'}
              </button>
            </div>

            <div className="glass flex h-[28rem] flex-col overflow-hidden rounded-2xl">
              <ChatBox
                messages={messages}
                perspective="professional"
                onSend={enviarMensagem}
                placeholder={`Mensagem para ${student.name.split(' ')[0]}...`}
                vazioTexto="Nenhuma mensagem ainda. O aluno também pode iniciar a conversa pelo portal."
              />
            </div>
          </section>
        </div>
      </main>
    </>
  )
}
