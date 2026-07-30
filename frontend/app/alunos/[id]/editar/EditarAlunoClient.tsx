'use client'

import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import BackLink from '@/components/BackLink'
import { api, ApiError } from '@/lib/api'
import { Student, StudentBillingPlan, TipoCobranca } from '@/lib/types'

export default function EditarAlunoClient({ studentId }: { studentId: string }) {
  const router = useRouter()
  const [carregando, setCarregando] = useState(true)
  const [salvando, setSalvando] = useState(false)
  const [erro, setErro] = useState<string | null>(null)

  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [phone, setPhone] = useState('')
  const [weight, setWeight] = useState('')
  const [height, setHeight] = useState('')
  const [objective, setObjective] = useState('')
  const [billingType, setBillingType] = useState<TipoCobranca | ''>('')
  const [monthlyValue, setMonthlyValue] = useState('')
  const [temCobrancaVigente, setTemCobrancaVigente] = useState(false)
  const [encerrando, setEncerrando] = useState(false)
  const [lembrarPagamento, setLembrarPagamento] = useState(false)

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    api
      .get<{ student: Student; billing_plan: StudentBillingPlan | null }>(`/alunos/${studentId}`)
      .then((data) => {
        setName(data.student.name)
        setEmail(data.student.email ?? '')
        setPhone(data.student.phone ?? '')
        setWeight(data.student.weight_kg?.toString() ?? '')
        setHeight(data.student.height_cm?.toString() ?? '')
        setObjective(data.student.objective ?? '')
        setLembrarPagamento(data.student.lembrar_pagamento_vencimento)
        if (data.billing_plan) {
          setBillingType(data.billing_plan.billing_type)
          setMonthlyValue(data.billing_plan.monthly_value)
          setTemCobrancaVigente(true)
        }
      })
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar aluno'))
      .finally(() => setCarregando(false))
  }, [studentId, router])

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setErro(null)
    setSalvando(true)
    try {
      await api.patch(`/alunos/${studentId}`, {
        name,
        email,
        phone,
        objective,
        weight_kg: weight ? Number(weight) : undefined,
        height_cm: height ? Number(height) : undefined,
        billing_type: billingType || undefined,
        monthly_value: monthlyValue ? Number(monthlyValue) : undefined,
      })
      await api.patch(`/alunos/${studentId}/lembrete-pagamento`, { enabled: lembrarPagamento })
      router.push(`/alunos/${studentId}`)
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao salvar alterações')
    } finally {
      setSalvando(false)
    }
  }

  async function encerrarCobranca() {
    if (!confirm('Encerrar a cobrança deste aluno? A receita já calculada de meses anteriores não muda — só deixa de contar a partir de hoje.')) {
      return
    }
    setEncerrando(true)
    setErro(null)
    try {
      await api.patch(`/alunos/${studentId}/cobranca/encerrar`, {})
      setTemCobrancaVigente(false)
      setBillingType('')
      setMonthlyValue('')
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao encerrar cobrança')
    } finally {
      setEncerrando(false)
    }
  }

  if (carregando) {
    return (
      <>
        <Navbar />
        <main className="mx-auto w-full max-w-lg flex-1 px-4 py-10">
          <p className="text-ink-muted">Carregando...</p>
        </main>
      </>
    )
  }

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-lg flex-1 px-4 py-10">
        <BackLink href={`/alunos/${studentId}`} label="Voltar ao perfil" />
        <h1 className="mb-1 font-display text-2xl font-bold tracking-tight text-ink">Editar aluno</h1>
        <p className="mb-6 text-sm text-ink-muted">
          Mudar o tipo/valor de cobrança não altera meses já calculados — o histórico anterior fica preservado.
        </p>

        <form onSubmit={handleSubmit} className="glass space-y-4 rounded-2xl p-6">
          <div>
            <label className="mb-1.5 block text-sm font-medium text-ink-soft">Nome</label>
            <input
              type="text"
              required
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
            />
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-ink-soft">E-mail (opcional)</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
            />
          </div>
          <div>
            <label className="mb-1.5 block text-sm font-medium text-ink-soft">Telefone (opcional)</label>
            <input
              type="text"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="mb-1.5 block text-sm font-medium text-ink-soft">Peso (kg)</label>
              <input
                type="number"
                min={0}
                step="0.1"
                inputMode="decimal"
                value={weight}
                onChange={(e) => setWeight(e.target.value)}
                className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
              />
            </div>
            <div>
              <label className="mb-1.5 block text-sm font-medium text-ink-soft">Altura (cm)</label>
              <input
                type="number"
                min={0}
                step="1"
                inputMode="numeric"
                value={height}
                onChange={(e) => setHeight(e.target.value)}
                className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
              />
            </div>
          </div>

          <div>
            <label className="mb-1.5 block text-sm font-medium text-ink-soft">Objetivo</label>
            <textarea
              value={objective}
              onChange={(e) => setObjective(e.target.value)}
              rows={3}
              className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
            />
          </div>

          <div className="grid grid-cols-2 gap-3 border-t border-black/6 pt-4">
            <div className="col-span-2">
              <p className="mb-1 text-xs font-semibold uppercase tracking-wider text-ink-muted">Cobrança</p>
              <p className="text-xs text-ink-muted">
                Mudar aqui fecha o valor atual e passa a valer o novo a partir de hoje — a receita de meses
                anteriores não muda.
              </p>
            </div>
            <div>
              <label className="mb-1.5 block text-sm font-medium text-ink-soft">Tipo</label>
              <select
                value={billingType}
                onChange={(e) => setBillingType(e.target.value as TipoCobranca | '')}
                className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
              >
                <option value="">Não informar</option>
                <option value="consultoria">Consultoria</option>
                <option value="presencial">Presencial</option>
              </select>
            </div>
            <div>
              <label className="mb-1.5 block text-sm font-medium text-ink-soft">Valor mensal (R$)</label>
              <input
                type="number"
                min={0}
                step="0.01"
                inputMode="decimal"
                value={monthlyValue}
                onChange={(e) => setMonthlyValue(e.target.value)}
                className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
              />
            </div>
            {temCobrancaVigente && (
              <div className="col-span-2">
                <button
                  type="button"
                  onClick={encerrarCobranca}
                  disabled={encerrando}
                  className="text-xs font-medium text-danger transition hover:underline"
                >
                  {encerrando ? 'Encerrando...' : 'Encerrar cobrança (aluno cancelou/saiu)'}
                </button>
              </div>
            )}
            <div className="col-span-2">
              <label className="flex cursor-pointer items-start gap-2 text-sm text-ink-soft">
                <input
                  type="checkbox"
                  checked={lembrarPagamento}
                  onChange={(e) => setLembrarPagamento(e.target.checked)}
                  className="mt-0.5 h-4 w-4 shrink-0 accent-brand"
                />
                <span>
                  Incluir lembrete de pagamento no aviso de treino vencido
                  <span className="block text-xs text-ink-muted">
                    Quando o treino desse aluno vencer, a notificação dele vai incluir um lembrete pra regularizar o
                    pagamento com você.
                  </span>
                </span>
              </label>
            </div>
          </div>

          {erro && <p className="text-sm text-danger">{erro}</p>}

          <button type="submit" disabled={salvando} className="btn-primary w-full rounded-xl px-4 py-3 text-sm">
            {salvando ? 'Salvando...' : 'Salvar alterações'}
          </button>
        </form>
      </main>
    </>
  )
}
