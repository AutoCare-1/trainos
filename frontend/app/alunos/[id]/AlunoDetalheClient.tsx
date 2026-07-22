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
import { BodyMeasurement, Message, Student, Workout } from '@/lib/types'

export default function AlunoDetalheClient({ studentId }: { studentId: string }) {
  const router = useRouter()
  const [student, setStudent] = useState<Student | null>(null)
  const [workouts, setWorkouts] = useState<Workout[]>([])
  const [measurements, setMeasurements] = useState<BodyMeasurement[]>([])
  const [novoPeso, setNovoPeso] = useState('')
  const [salvandoPeso, setSalvandoPeso] = useState(false)
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
      })
      setMeasurements((prev) => [...prev, measurement])
      setNovoPeso('')
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao registrar medição')
    } finally {
      setSalvandoPeso(false)
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

        <section className="glass mb-6 rounded-2xl p-5">
          <h2 className="mb-3 font-semibold text-slate-900">Evolução física</h2>
          <div className="grid gap-5 sm:grid-cols-2">
            <WeightChart pontos={measurements} />
            <form onSubmit={registrarPeso} className="flex flex-col justify-center gap-2">
              <label className="text-xs text-slate-500">Registrar peso de hoje</label>
              <div className="flex gap-2">
                <input
                  type="number"
                  step="0.1"
                  min={0}
                  inputMode="decimal"
                  placeholder="kg"
                  value={novoPeso}
                  onChange={(e) => setNovoPeso(e.target.value)}
                  className="input-dark w-28 rounded-xl px-3 py-2 text-sm"
                />
                <button
                  type="submit"
                  disabled={salvandoPeso || !novoPeso}
                  className="btn-primary rounded-xl px-4 py-2 text-sm"
                >
                  {salvandoPeso ? 'Salvando...' : 'Registrar'}
                </button>
              </div>
            </form>
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
