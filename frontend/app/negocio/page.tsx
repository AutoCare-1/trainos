'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { MessageCircle, ChevronRight } from 'lucide-react'
import Navbar from '@/components/Navbar'
import { api, ApiError } from '@/lib/api'
import { DashboardNegocio } from '@/lib/types'

const ESTILO_PRIORIDADE: Record<string, string> = {
  alta: 'border-danger [--glass-bg:var(--color-danger-soft)]',
  media: 'border-warning [--glass-bg:var(--color-warning-soft)]',
}

const LABEL_PRIORIDADE: Record<string, string> = {
  alta: 'Risco alto',
  media: 'Atenção',
}

const LABEL_TIPO_COBRANCA: Record<string, string> = {
  consultoria: 'Consultoria',
  presencial: 'Presencial',
}

function formatarMoeda(valor: number): string {
  return valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function formatarMesReferencia(mes: string): string {
  const [ano, mesNum] = mes.split('-')
  const nomes = [
    'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
    'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro',
  ]
  return `${nomes[Number(mesNum) - 1]} de ${ano}`
}

export default function NegocioPage() {
  const router = useRouter()
  const [dados, setDados] = useState<DashboardNegocio | null>(null)
  const [erro, setErro] = useState<string | null>(null)

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    api
      .get<DashboardNegocio>('/negocio')
      .then(setDados)
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar o painel'))
  }, [router])

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8">
        <div className="mb-8">
          <h1 className="font-display text-2xl font-bold tracking-tight text-ink">Meu Negócio</h1>
          <span className="title-accent" />
          <p className="mt-2 text-sm text-ink-muted">Saúde da sua base de alunos, num só lugar</p>
        </div>

        {erro && <p className="mb-4 text-sm text-danger">{erro}</p>}
        {dados === null && !erro && <p className="text-ink-muted">Carregando...</p>}

        {dados && (
          <div className="space-y-8">
            {/* Visão financeira — receita/despesa/resultado do mês corrente */}
            <section>
              <div className="mb-3 flex items-baseline justify-between">
                <h2 className="text-sm font-semibold uppercase tracking-wider text-ink-muted">Visão financeira</h2>
                <span className="text-xs text-ink-muted/70">{formatarMesReferencia(dados.financeiro.mes_referencia)}</span>
              </div>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div className="glass rounded-2xl p-4">
                  <p className="text-xs font-medium uppercase tracking-wider text-ink-muted">Receita do mês</p>
                  <p className="stat-number mt-1 text-2xl font-bold text-success">{formatarMoeda(dados.financeiro.receita.total)}</p>
                  {Object.keys(dados.financeiro.receita.por_tipo).length > 0 && (
                    <p className="mt-1 text-xs text-ink-muted">
                      {Object.entries(dados.financeiro.receita.por_tipo)
                        .map(([tipo, valor]) => `${LABEL_TIPO_COBRANCA[tipo] ?? tipo}: ${formatarMoeda(valor)}`)
                        .join(' · ')}
                    </p>
                  )}
                </div>
                <div className="glass rounded-2xl p-4">
                  <p className="text-xs font-medium uppercase tracking-wider text-ink-muted">Despesas do mês</p>
                  <p className="stat-number mt-1 text-2xl font-bold text-danger">{formatarMoeda(dados.financeiro.despesas.total)}</p>
                  <Link href="/gastos" className="mt-1 flex items-center gap-0.5 text-xs font-medium text-brand">
                    Ver despesas <ChevronRight size={13} />
                  </Link>
                </div>
                <div
                  className={`rounded-2xl p-4 ${
                    dados.financeiro.resultado_liquido >= 0 ? 'stat-hero' : 'bg-danger text-white shadow-lg shadow-danger/30'
                  }`}
                >
                  <p className={`text-xs font-medium uppercase tracking-wider ${dados.financeiro.resultado_liquido >= 0 ? 'stat-hero-label' : 'text-white/75'}`}>
                    Resultado líquido
                  </p>
                  <p className="stat-number mt-1 text-2xl font-bold text-white">
                    {formatarMoeda(dados.financeiro.resultado_liquido)}
                  </p>
                </div>
              </div>
            </section>

            {/* Base de alunos */}
            <section>
              <h2 className="mb-3 text-sm font-semibold uppercase tracking-wider text-ink-muted">Base de alunos</h2>
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div className="glass rounded-2xl p-4">
                  <p className="text-xs font-medium uppercase tracking-wider text-ink-muted">Alunos ativos</p>
                  <p className="stat-number mt-1 text-2xl font-bold text-ink">{dados.kpis.total_alunos}</p>
                </div>
                <div className="glass rounded-2xl p-4">
                  <p className="text-xs font-medium uppercase tracking-wider text-ink-muted">Novos no mês</p>
                  <p className="stat-number mt-1 bg-gradient-to-r from-brand to-accent bg-clip-text text-2xl font-bold text-transparent">
                    {dados.kpis.novos_no_mes}
                  </p>
                </div>
                <div className="glass rounded-2xl p-4">
                  <p className="text-xs font-medium uppercase tracking-wider text-ink-muted">Inativos</p>
                  <p className="stat-number mt-1 text-2xl font-bold text-ink">{dados.kpis.inativos}</p>
                </div>
                <div className="glass rounded-2xl p-4">
                  <p className="text-xs font-medium uppercase tracking-wider text-ink-muted">Retenção do mês</p>
                  <p className="stat-number mt-1 text-2xl font-bold text-ink">
                    {dados.kpis.retencao_pct === null ? '—' : `${dados.kpis.retencao_pct}%`}
                  </p>
                </div>
              </div>
            </section>

            {/* Alunos em risco */}
            <section>
              <h2 className="mb-3 text-sm font-semibold uppercase tracking-wider text-ink-muted">Alunos em risco</h2>

              {dados.alunos_em_risco.length === 0 && (
                <div className="glass-flat rounded-2xl border-dashed p-8 text-center">
                  <p className="text-ink-muted">Todos os seus alunos estão engajados.</p>
                </div>
              )}

              <div className="space-y-2">
                {dados.alunos_em_risco.map((a) => (
                  <div
                    key={a.id}
                    className={`glass flex items-center gap-4 rounded-2xl border-l-4 p-4 ${ESTILO_PRIORIDADE[a.prioridade]}`}
                  >
                    <div className="min-w-0 flex-1">
                      <div className="flex items-center gap-2">
                        <p className="truncate font-semibold text-ink">{a.name}</p>
                        {a.prioridade === 'alta' && (
                          <span className="shrink-0 rounded-full bg-danger px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
                            {LABEL_PRIORIDADE[a.prioridade]}
                          </span>
                        )}
                      </div>
                      <p className="truncate text-sm text-ink-soft">{a.motivo}</p>
                    </div>
                    <Link
                      href={`/alunos/${a.id}#conversa`}
                      className="btn-secondary flex shrink-0 items-center gap-1.5 rounded-xl px-4 py-2 text-sm"
                    >
                      <MessageCircle size={14} />
                      Mandar mensagem
                    </Link>
                  </div>
                ))}
              </div>
            </section>
          </div>
        )}
      </main>
    </>
  )
}
