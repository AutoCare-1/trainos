'use client'

import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import { api, ApiError } from '@/lib/api'
import { Expense } from '@/lib/types'

// Nunca usar toISOString() aqui — ela converte pra UTC, e à noite (fuso do
// Brasil, UTC-3) isso adianta a data pro dia seguinte. Usa os getters locais.
function hoje(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function formatarMoeda(valor: string): string {
  return Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

// O backend serializa colunas `date` como ISO completo ("2026-07-01T00:00:00Z").
// new Date(iso).toLocaleDateString() interpretaria isso como meia-noite UTC e
// mostraria o dia anterior pra quem está num fuso atrás de UTC (Brasil) — mesmo
// cuidado já documentado em lib/checkinDates.ts. Extrai a data como string, sem
// nunca passar pelo construtor de Date.
function formatarData(iso: string): string {
  const [ano, mes, dia] = iso.slice(0, 10).split('-')
  return `${dia}/${mes}/${ano}`
}

export default function GastosPage() {
  const router = useRouter()
  const [expenses, setExpenses] = useState<Expense[]>([])
  const [carregando, setCarregando] = useState(true)
  const [erro, setErro] = useState<string | null>(null)

  const [description, setDescription] = useState('')
  const [amount, setAmount] = useState('')
  const [isRecurring, setIsRecurring] = useState(true)
  const [startsOn, setStartsOn] = useState(hoje())
  const [criando, setCriando] = useState(false)

  const [editandoId, setEditandoId] = useState<string | null>(null)
  const [editDescription, setEditDescription] = useState('')
  const [editAmount, setEditAmount] = useState('')
  const [salvandoEdicao, setSalvandoEdicao] = useState(false)

  function carregar() {
    api
      .get<{ expenses: Expense[] }>('/gastos')
      .then((data) => setExpenses(data.expenses))
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar despesas'))
      .finally(() => setCarregando(false))
  }

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    carregar()
  }, [router])

  async function criarDespesa(e: React.FormEvent) {
    e.preventDefault()
    if (!description || !amount) return
    setCriando(true)
    setErro(null)
    try {
      const { expense } = await api.post<{ expense: Expense }>('/gastos', {
        description,
        amount: Number(amount),
        is_recurring: isRecurring,
        starts_on: startsOn,
      })
      setExpenses((prev) => [expense, ...prev])
      setDescription('')
      setAmount('')
      setIsRecurring(true)
      setStartsOn(hoje())
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao criar despesa')
    } finally {
      setCriando(false)
    }
  }

  function comecarEdicao(exp: Expense) {
    setEditandoId(exp.id)
    setEditDescription(exp.description)
    setEditAmount(exp.amount)
  }

  async function salvarEdicao(id: string) {
    if (!editDescription.trim() || !editAmount) {
      setErro('Descrição e valor são obrigatórios')
      return
    }
    setSalvandoEdicao(true)
    setErro(null)
    try {
      const { expense } = await api.patch<{ expense: Expense }>(`/gastos/${id}`, {
        description: editDescription,
        amount: Number(editAmount),
      })
      setExpenses((prev) => prev.map((e) => (e.id === id || e.id === expense.id ? expense : e)).filter((e, i, arr) => arr.findIndex((x) => x.id === e.id) === i))
      // A edição de uma recorrente com valor novo troca o id (fecha a antiga, cria
      // outra) — recarrega a lista pra refletir isso certinho em vez de tentar
      // adivinhar a substituição no estado local.
      carregar()
      setEditandoId(null)
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao salvar edição')
    } finally {
      setSalvandoEdicao(false)
    }
  }

  async function encerrar(id: string) {
    if (!confirm('Encerrar esta despesa recorrente? Ela continua contando neste mês, mas para a partir do mês que vem.')) return
    try {
      const { expense } = await api.patch<{ expense: Expense }>(`/gastos/${id}/encerrar`, {})
      setExpenses((prev) => prev.map((e) => (e.id === id ? expense : e)))
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao encerrar despesa')
    }
  }

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
        <div className="mb-6">
          <h1 className="font-display text-2xl font-bold tracking-tight text-ink">Meus Gastos</h1>
          <p className="mt-1 text-sm text-ink-muted">
            Custos de operar o negócio (aluguel, equipamento, software...) — usados pra calcular o resultado
            líquido em &quot;Meu Negócio&quot;.
          </p>
        </div>

        <form onSubmit={criarDespesa} className="glass mb-6 space-y-4 rounded-2xl p-6">
          <div>
            <label className="mb-1.5 block text-sm font-medium text-ink-soft">Descrição</label>
            <input
              type="text"
              required
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Ex: Aluguel da sala, Software de agenda..."
              className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="mb-1.5 block text-sm font-medium text-ink-soft">Valor (R$)</label>
              <input
                type="number"
                required
                min={0}
                step="0.01"
                inputMode="decimal"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
              />
            </div>
            <div>
              <label className="mb-1.5 block text-sm font-medium text-ink-soft">A partir de</label>
              <input
                type="date"
                required
                value={startsOn}
                onChange={(e) => setStartsOn(e.target.value)}
                className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
              />
            </div>
          </div>

          <label className="flex cursor-pointer items-center gap-2.5 text-sm text-ink-soft">
            <input
              type="checkbox"
              checked={isRecurring}
              onChange={(e) => setIsRecurring(e.target.checked)}
              className="h-4 w-4 accent-brand"
            />
            Recorrente (repete todo mês até eu encerrar)
          </label>

          {erro && <p className="text-sm text-danger">{erro}</p>}

          <button type="submit" disabled={criando} className="btn-primary w-full rounded-xl px-4 py-3 text-sm">
            {criando ? 'Salvando...' : 'Adicionar despesa'}
          </button>
        </form>

        {carregando && <p className="text-ink-muted">Carregando...</p>}

        {!carregando && expenses.length === 0 && (
          <div className="glass rounded-2xl border-dashed p-8 text-center">
            <p className="text-ink-muted">Nenhuma despesa cadastrada ainda.</p>
          </div>
        )}

        <div className="space-y-2">
          {expenses.map((exp) => (
            <div key={exp.id} className="glass rounded-2xl p-4">
              {editandoId === exp.id ? (
                <div className="space-y-3">
                  <input
                    type="text"
                    required
                    value={editDescription}
                    onChange={(e) => setEditDescription(e.target.value)}
                    className="input-dark w-full rounded-xl px-3 py-2 text-sm"
                  />
                  <input
                    type="number"
                    required
                    min={0.01}
                    step="0.01"
                    inputMode="decimal"
                    value={editAmount}
                    onChange={(e) => setEditAmount(e.target.value)}
                    className="input-dark w-full rounded-xl px-3 py-2 text-sm"
                  />
                  <div className="flex gap-2">
                    <button
                      onClick={() => salvarEdicao(exp.id)}
                      disabled={salvandoEdicao || !editDescription.trim() || !editAmount}
                      className="btn-primary rounded-xl px-4 py-2 text-sm"
                    >
                      {salvandoEdicao ? 'Salvando...' : 'Salvar'}
                    </button>
                    <button
                      onClick={() => setEditandoId(null)}
                      className="rounded-xl px-4 py-2 text-sm font-medium text-ink-muted"
                    >
                      Cancelar
                    </button>
                  </div>
                </div>
              ) : (
                <div className="flex items-center justify-between gap-3">
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                      <p className="truncate font-semibold text-ink">{exp.description}</p>
                      <span
                        className={`shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide ${
                          exp.is_recurring ? 'bg-accent/10 text-accent-deep' : 'bg-ink/6 text-ink-muted'
                        }`}
                      >
                        {exp.is_recurring ? 'Recorrente' : 'Avulsa'}
                      </span>
                      {exp.ends_on && (
                        <span className="shrink-0 rounded-full bg-warning-soft px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-warning">
                          Encerrada
                        </span>
                      )}
                    </div>
                    <p className="text-sm text-ink-muted">
                      {formatarMoeda(exp.amount)}
                      {exp.is_recurring ? '/mês' : ''} · desde {formatarData(exp.starts_on)}
                      {exp.ends_on && <> · última cobrança {formatarData(exp.ends_on)}</>}
                    </p>
                  </div>
                  <div className="flex shrink-0 gap-2">
                    <button
                      onClick={() => comecarEdicao(exp)}
                      className="glass glass-hover rounded-xl px-3 py-1.5 text-xs font-medium text-ink-soft"
                    >
                      Editar
                    </button>
                    {exp.is_recurring && !exp.ends_on && (
                      <button
                        onClick={() => encerrar(exp.id)}
                        className="rounded-xl px-3 py-1.5 text-xs font-medium text-danger transition hover:bg-danger"
                      >
                        Encerrar
                      </button>
                    )}
                  </div>
                </div>
              )}
            </div>
          ))}
        </div>
      </main>
    </>
  )
}
