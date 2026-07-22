'use client'

import { useCallback, useEffect, useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import BackLink from '@/components/BackLink'
import Avatar from '@/components/Avatar'
import ChatBox from '@/components/ChatBox'
import WeightChart from '@/components/WeightChart'
import { api, ApiError } from '@/lib/api'
import { BodyMeasurement, Message, ParQAnswers, Student, Workout } from '@/lib/types'

const PAR_Q_PERGUNTAS: { chave: keyof ParQAnswers; texto: string }[] = [
  { chave: 'cardiaco', texto: 'Problema cardíaco ou dor no peito provocada por exercício?' },
  { chave: 'tontura', texto: 'Já perdeu a consciência ou sofreu queda por tontura?' },
  { chave: 'articular', texto: 'Problema ósseo ou articular que pode agravar com exercício?' },
  { chave: 'pressao_medicacao', texto: 'Usa medicação para pressão ou coração?' },
]

const PAR_Q_VAZIO: ParQAnswers = { cardiaco: false, tontura: false, articular: false, pressao_medicacao: false }

export default function AlunoDetalheClient({ studentId }: { studentId: string }) {
  const router = useRouter()
  const [student, setStudent] = useState<Student | null>(null)
  const [workouts, setWorkouts] = useState<Workout[]>([])
  const [measurements, setMeasurements] = useState<BodyMeasurement[]>([])
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
      .get<{ student: Student; workouts: Workout[]; measurements: BodyMeasurement[] }>(`/alunos/${studentId}`)
      .then((data) => {
        setStudent(data.student)
        setWorkouts(data.workouts)
        setMeasurements(data.measurements)
        setAutopilot(data.student.ai_autopilot)
        setParQ(data.student.par_q_answers ?? PAR_Q_VAZIO)
        setHealthNotes(data.student.health_notes ?? '')
      })
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar aluno'))

    carregarMensagens()
    const intervalo = setInterval(carregarMensagens, 5000)
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
          <Avatar nome={student.name} tamanho="lg" />
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
          <button
            onClick={() => {
              navigator.clipboard.writeText(inviteLink)
              setCopiado(true)
              setTimeout(() => setCopiado(false), 2000)
            }}
            className="glass glass-hover hidden shrink-0 rounded-xl px-4 py-2.5 text-sm text-slate-700 sm:block"
          >
            {copiado ? 'Link copiado ✓' : 'Copiar link do aluno'}
          </button>
        </div>

        <section className="mb-6">
          <h2 className="mb-3 font-semibold text-slate-900">Avaliação física</h2>

          {Object.values(parQ).some(Boolean) && (
            <div className="mb-4 rounded-2xl border border-amber-300 bg-amber-50 px-5 py-3 text-sm text-amber-800">
              ⚠️ Atenção: {student.name.split(' ')[0]} respondeu <strong>sim</strong> a um ou mais itens do PAR-Q —
              recomende avaliação médica antes de seguir com o treino.
            </div>
          )}

          <div className="grid gap-4 lg:grid-cols-2">
            {/* Saúde / PAR-Q */}
            <div className="glass rounded-2xl p-5">
              <h3 className="mb-3 text-sm font-semibold text-slate-900">Saúde (PAR-Q)</h3>
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
                ✦ Coach IA {autopilot ? 'ligado' : 'desligado'}
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
