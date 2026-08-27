'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { Check, MessageCircle } from 'lucide-react'
import Navbar from '@/components/Navbar'
import BackLink from '@/components/BackLink'
import { api, ApiError } from '@/lib/api'
import { copiarTexto, linkWhatsApp, mensagemConvite } from '@/lib/compartilharLink'
import { Student, TipoCobranca } from '@/lib/types'

export default function NovoAlunoPage() {
  const router = useRouter()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [phone, setPhone] = useState('')
  const [weight, setWeight] = useState('')
  const [height, setHeight] = useState('')
  const [objective, setObjective] = useState('')
  const [billingType, setBillingType] = useState<TipoCobranca | ''>('')
  const [monthlyValue, setMonthlyValue] = useState('')
  const [erro, setErro] = useState<string | null>(null)
  const [carregando, setCarregando] = useState(false)
  const [criado, setCriado] = useState<Student | null>(null)
  const [copiado, setCopiado] = useState(false)

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
    }
  }, [router])

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault()
    setErro(null)
    setCarregando(true)
    try {
      const data = await api.post<{ student: Student }>('/alunos', {
        name,
        email,
        phone,
        objective,
        weight_kg: weight ? Number(weight) : undefined,
        height_cm: height ? Number(height) : undefined,
        billing_type: billingType || undefined,
        monthly_value: monthlyValue ? Number(monthlyValue) : undefined,
      })
      setCriado(data.student)
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Erro ao cadastrar aluno')
    } finally {
      setCarregando(false)
    }
  }

  if (criado) {
    const link = `${window.location.origin}/aluno/${criado.invite_token}`
    return (
      <>
        <Navbar />
        <main className="mx-auto w-full max-w-lg flex-1 px-4 py-10">
          <div className="glass rounded-2xl p-7">
            <span className="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-success-soft text-success">
              <Check size={24} strokeWidth={2.5} />
            </span>
            <h1 className="font-display text-xl font-bold text-ink">Aluno cadastrado!</h1>
            <p className="mt-2 text-sm text-ink-muted">
              Envie este link para <strong className="text-ink">{criado.name}</strong> acessar o portal, ver os
              treinos e conversar com você:
            </p>
            <div className="mt-4 flex items-center gap-2">
              <code className="input-dark flex-1 truncate rounded-xl px-4 py-3 text-sm text-brand">
                {link}
              </code>
              <button
                onClick={async () => {
                  if (!(await copiarTexto(link))) {
                    setErro('Não consegui copiar o link. Toque nele e copie na mão.')
                    return
                  }
                  setCopiado(true)
                  setTimeout(() => setCopiado(false), 2000)
                }}
                className="glass glass-hover flex items-center gap-1.5 rounded-xl px-4 py-3 text-sm text-ink-soft"
              >
                {copiado ? (
                  <>
                    <Check size={15} className="text-success" /> Copiado
                  </>
                ) : (
                  'Copiar'
                )}
              </button>
            </div>
            <a
              href={linkWhatsApp(criado.phone, mensagemConvite(criado.name, link))}
              target="_blank"
              rel="noopener noreferrer"
              className="mt-3 inline-flex items-center gap-1.5 rounded-xl bg-[#25D366] px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-90"
            >
              <MessageCircle size={16} />
              Enviar por WhatsApp
            </a>
            <div className="mt-6 flex gap-3">
              <Link href={`/treinos/novo?aluno=${criado.id}`} className="btn-primary rounded-xl px-5 py-2.5 text-sm">
                Criar treino agora
              </Link>
              <Link
                href="/dashboard"
                className="rounded-xl px-5 py-2.5 text-sm font-medium text-ink-muted transition hover:text-ink"
              >
                Voltar ao painel
              </Link>
            </div>
          </div>
        </main>
      </>
    )
  }

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-lg flex-1 px-4 py-10">
        <BackLink href="/dashboard" label="Voltar ao painel" />
        <h1 className="mb-1 font-display text-2xl font-bold tracking-tight text-ink">Cadastrar aluno</h1>
        <p className="mb-6 text-sm text-ink-muted">O aluno recebe um link de acesso — sem senha, sem fricção.</p>

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
              placeholder="Ex: hipertrofia, emagrecimento, condicionamento. Vale contar mais: rotina, restrições, prazo de meta etc — isso ajuda a direcionar o treino."
              value={objective}
              onChange={(e) => setObjective(e.target.value)}
              rows={3}
              className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
            />
          </div>

          <div className="grid grid-cols-2 gap-3 border-t border-black/6 pt-4">
            <div className="col-span-2">
              <p className="mb-1 text-xs font-semibold uppercase tracking-wider text-ink-muted">
                Cobrança (opcional)
              </p>
              <p className="text-xs text-ink-muted">
                Usada só pra calcular sua receita no painel &quot;Meu Negócio&quot; — não gera cobrança nenhuma de verdade.
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
          </div>

          {erro && <p className="text-sm text-danger">{erro}</p>}

          <button type="submit" disabled={carregando} className="btn-cta w-full rounded-xl px-4 py-3 text-sm">
            {carregando ? 'Salvando...' : 'Cadastrar aluno'}
          </button>
        </form>
      </main>
    </>
  )
}
