'use client'

import { useCallback, useEffect, useRef, useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import BackLink from '@/components/BackLink'
import Avatar from '@/components/Avatar'
import ChatBox from '@/components/ChatBox'
import WeightChart from '@/components/WeightChart'
import { api, ApiError, fetchImagemAutenticada } from '@/lib/api'
import { PAR_Q_PERGUNTAS, PAR_Q_VAZIO } from '@/lib/parq'
import {
  AlertaEstagnacao,
  BodyMeasurement,
  BodyPhoto,
  Gamificacao,
  Message,
  ParQAnswers,
  Student,
  Workout,
} from '@/lib/types'

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
    return <div className="flex h-full w-full items-center justify-center bg-slate-900/5 text-xs text-slate-400">Carregando...</div>
  }
  // eslint-disable-next-line @next/next/no-img-element -- imagem vem de rota autenticada, não do next/image
  return <img src={url} alt={alt} className="h-full w-full object-cover" />
}

export default function AlunoDetalheClient({ studentId }: { studentId: string }) {
  const router = useRouter()
  const [student, setStudent] = useState<Student | null>(null)
  const [workouts, setWorkouts] = useState<Workout[]>([])
  const [measurements, setMeasurements] = useState<BodyMeasurement[]>([])
  const [gamificacao, setGamificacao] = useState<Gamificacao | null>(null)
  const [alertasEstagnacao, setAlertasEstagnacao] = useState<AlertaEstagnacao[]>([])
  const [fotosEvolucao, setFotosEvolucao] = useState<BodyPhoto[]>([])
  const [novoPeso, setNovoPeso] = useState('')
  const [novaCintura, setNovaCintura] = useState('')
  const [novoQuadril, setNovoQuadril] = useState('')
  const [novaGordura, setNovaGordura] = useState('')
  const [salvandoPeso, setSalvandoPeso] = useState(false)
  const [parQ, setParQ] = useState<ParQAnswers>(PAR_Q_VAZIO)
  const [healthNotes, setHealthNotes] = useState('')
  const [salvandoAvaliacao, setSalvandoAvaliacao] = useState(false)
  const [avaliacaoSalva, setAvaliacaoSalva] = useState(false)
  const [messages, setMessages] = useState<Message[]>([])
  const [autopilot, setAutopilot] = useState(true)
  const [erro, setErro] = useState<string | null>(null)
  const [copiado, setCopiado] = useState(false)
  const [enviandoFoto, setEnviandoFoto] = useState(false)
  const fotoInputRef = useRef<HTMLInputElement | null>(null)

  const carregarMensagens = useCallback(() => {
    api
      .get<{ messages: Message[]; ai_autopilot: boolean }>(`/alunos/${studentId}/mensagens`)
      .then((data) => {
        setMessages(data.messages)
        setAutopilot(data.ai_autopilot)
      })
      .catch(() => {})
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
        setHealthNotes(data.student.health_notes ?? '')
      })
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar aluno'))

    carregarMensagens()
    const intervalo = setInterval(carregarMensagens, 5000)

    api
      .get<{ photos: BodyPhoto[] }>(`/alunos/${studentId}/body-photos`)
      .then((data) => setFotosEvolucao(data.photos))
      .catch(() => {})

    return () => clearInterval(intervalo)
  }, [studentId, router, carregarMensagens])

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

  async function salvarAvaliacao() {
    setSalvandoAvaliacao(true)
    try {
      const { student: atualizado } = await api.patch<{ student: Student }>(`/alunos/${studentId}/avaliacao`, {
        par_q_answers: parQ,
        health_notes: healthNotes,
      })
      setStudent(atualizado)
      setAvaliacaoSalva(true)
      setTimeout(() => setAvaliacaoSalva(false), 2000)
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao salvar avaliação')
    } finally {
      setSalvandoAvaliacao(false)
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
          <p className="text-sm text-rose-400">{erro}</p>
        </main>
      </>
    )
  }

  if (!student) {
    return (
      <>
        <Navbar />
        <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8">
          <p className="text-slate-500">Carregando...</p>
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
              className="absolute -bottom-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full bg-white text-xs shadow ring-1 ring-black/10 transition hover:bg-slate-50"
            >
              {enviandoFoto ? (
                '…'
              ) : (
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z" />
                  <circle cx="12" cy="13" r="4" />
                </svg>
              )}
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
            <h1 className="truncate text-2xl font-bold tracking-tight text-slate-900">{student.name}</h1>
            {(student.weight_kg || student.height_cm) && (
              <p className="mt-0.5 text-sm text-slate-500">
                {student.weight_kg ? `${student.weight_kg} kg` : null}
                {student.weight_kg && student.height_cm ? ' · ' : null}
                {student.height_cm ? `${student.height_cm} cm` : null}
              </p>
            )}
            <p className="mt-1 whitespace-pre-wrap text-sm text-slate-500">
              {student.objective || 'Sem objetivo definido'}
            </p>
          </div>
          <div className="hidden shrink-0 gap-2 sm:flex">
            <button
              onClick={() => {
                navigator.clipboard.writeText(inviteLink)
                setCopiado(true)
                setTimeout(() => setCopiado(false), 2000)
              }}
              className="glass glass-hover rounded-xl px-4 py-2.5 text-sm text-slate-700"
            >
              {copiado ? 'Link copiado ✓' : 'Copiar link'}
            </button>
            <a
              href={`https://wa.me/?text=${encodeURIComponent(
                `Oi, ${student.name.split(' ')[0]}! Aqui está seu acesso ao Clube Mais: ${inviteLink}`
              )}`}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center gap-1.5 rounded-xl bg-[#25D366] px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90"
            >
              WhatsApp
            </a>
          </div>
        </div>

        {gamificacao && (
          <section className="glass mb-6 flex flex-wrap items-center gap-5 rounded-2xl p-4">
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
            {gamificacao.badges.length > 0 && (
              <div className="flex-1">
                <p className="mb-1 text-xs uppercase tracking-wider text-slate-500">Medalhas</p>
                <div className="flex flex-wrap gap-2">
                  {gamificacao.badges.map((b) => (
                    <span
                      key={b.id}
                      title={b.label}
                      className="flex items-center gap-1 rounded-full bg-slate-900/5 px-2.5 py-1 text-sm"
                    >
                      {b.emoji} <span className="text-xs text-slate-600">{b.label}</span>
                    </span>
                  ))}
                </div>
              </div>
            )}
          </section>
        )}

        {alertasEstagnacao.length > 0 && (
          <section className="glass mb-6 rounded-2xl border border-orange-200 bg-orange-50 p-4">
            <p className="mb-2 text-sm font-semibold text-orange-800">
              Sem aumento de carga nas duas últimas sessões
            </p>
            <div className="flex flex-wrap gap-2">
              {alertasEstagnacao.map((a) => (
                <span
                  key={a.exercise_id}
                  className="rounded-lg bg-white px-2.5 py-1 text-xs text-orange-700"
                  title={`Última: ${a.ultima}kg · Anterior: ${a.anterior}kg`}
                >
                  {a.exercise_name} ({a.ultima}kg, era {a.anterior}kg)
                </span>
              ))}
            </div>
          </section>
        )}

        <section className="mb-6">
          <h2 className="mb-3 font-semibold text-slate-900">Avaliação física</h2>

          {Object.values(parQ).some(Boolean) && (
            <div className="mb-4 rounded-2xl border border-amber-300 bg-amber-50 px-5 py-3 text-sm text-amber-800">
              Atenção: {student.name.split(' ')[0]} respondeu <strong>sim</strong> a um ou mais itens do PAR-Q —
              recomende avaliação médica antes de seguir com o treino.
            </div>
          )}

          <div className="grid gap-4 lg:grid-cols-2">
            {/* Saúde / PAR-Q */}
            <div className="glass rounded-2xl p-5">
              <div className="mb-3 flex items-center justify-between">
                <h3 className="text-sm font-semibold text-slate-900">Saúde (PAR-Q)</h3>
                <span
                  className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                    student.onboarding_completed_at
                      ? 'bg-emerald-500/15 text-emerald-600'
                      : 'bg-amber-500/15 text-amber-600'
                  }`}
                >
                  {student.onboarding_completed_at ? 'Preenchido pelo aluno' : 'Aguardando o aluno'}
                </span>
              </div>
              <div className="space-y-2.5">
                {PAR_Q_PERGUNTAS.map(({ chave, texto }) => (
                  <label key={chave} className="flex cursor-pointer items-start gap-2.5 text-sm text-slate-600">
                    <input
                      type="checkbox"
                      checked={parQ[chave]}
                      onChange={(e) => setParQ({ ...parQ, [chave]: e.target.checked })}
                      className="mt-0.5 h-4 w-4 shrink-0 accent-[#2648b3]"
                    />
                    {texto}
                  </label>
                ))}
              </div>
              <label className="mb-1.5 mt-4 block text-xs text-slate-500">
                Cirurgias, medicamentos, observações
              </label>
              <textarea
                value={healthNotes}
                onChange={(e) => setHealthNotes(e.target.value)}
                rows={2}
                className="input-dark w-full rounded-xl px-3 py-2 text-sm"
              />
              <button
                onClick={salvarAvaliacao}
                disabled={salvandoAvaliacao}
                className="btn-primary mt-3 rounded-xl px-4 py-2 text-sm"
              >
                {salvandoAvaliacao ? 'Salvando...' : avaliacaoSalva ? 'Salvo ✓' : 'Salvar avaliação'}
              </button>
            </div>

            {/* Medidas */}
            <div className="glass rounded-2xl p-5">
              <h3 className="mb-3 text-sm font-semibold text-slate-900">Medidas</h3>
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

        {fotosEvolucao.length > 0 && (
          <section className="mb-6">
            <h2 className="mb-3 font-semibold text-slate-900">Evolução (fotos)</h2>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
              {fotosEvolucao.map((foto) => (
                <div key={foto.id} className="glass overflow-hidden rounded-2xl">
                  <div className="aspect-square">
                    <FotoAutenticada src={`/alunos/${studentId}/body-photos/${foto.id}/imagem`} alt="Foto de evolução do aluno" />
                  </div>
                  <div className="p-3">
                    <p className="mb-1.5 text-[10px] uppercase tracking-wider text-slate-500">
                      {new Date(foto.taken_at).toLocaleDateString('pt-BR')}{' '}
                      {new Date(foto.taken_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}
                    </p>
                    {foto.ai_feedback && <p className="line-clamp-3 text-xs text-slate-600">{foto.ai_feedback}</p>}
                  </div>
                </div>
              ))}
            </div>
          </section>
        )}

        <div className="grid gap-6 lg:grid-cols-2">
          {/* Coluna: treinos */}
          <section>
            <div className="mb-4 flex items-center justify-between">
              <h2 className="font-semibold text-slate-900">Treinos</h2>
              <Link href={`/treinos/novo?aluno=${student.id}`} className="btn-primary rounded-xl px-4 py-2 text-sm">
                + Novo treino
              </Link>
            </div>

            {workouts.length === 0 && (
              <div className="glass rounded-2xl p-8 text-center text-sm text-slate-500">
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
                  <p className="font-semibold text-slate-900">{w.name}</p>
                  <span
                    className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                      w.status === 'sent'
                        ? 'bg-emerald-500/15 text-emerald-600'
                        : 'bg-slate-900/6 text-slate-500'
                    }`}
                  >
                    {w.status === 'sent' ? 'Enviado' : 'Rascunho'}
                  </span>
                </Link>
              ))}
            </div>
          </section>

          {/* Coluna: chat */}
          <section>
            <div className="mb-4 flex items-center justify-between">
              <h2 className="font-semibold text-slate-900">Conversa</h2>
              <button
                onClick={alternarAutopilot}
                className={`flex items-center gap-2 rounded-full px-3.5 py-1.5 text-xs font-medium transition ${
                  autopilot
                    ? 'border border-violet-300 bg-violet-50 text-violet-700'
                    : 'border border-black/10 bg-black/4 text-slate-500'
                }`}
                title="Quando ligado, a IA responde o aluno automaticamente como assistente do personal"
              >
                <span
                  className={`h-2 w-2 rounded-full ${autopilot ? 'bg-violet-400' : 'bg-slate-600'}`}
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
