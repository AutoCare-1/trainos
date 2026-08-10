'use client'

import { useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import Navbar from '@/components/Navbar'
import { api, ApiError } from '@/lib/api'
import { StatusAssinatura } from '@/lib/types'

function formatarMoeda(valor: number): string {
  return valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

function formatarData(data: string): string {
  const [ano, mes, dia] = data.split('-')
  return `${dia}/${mes}/${ano}`
}

const LABEL_STATUS: Record<string, string> = {
  ativa: 'Ativa',
  atrasada: 'Pagamento pendente',
  bloqueada: 'Suspensa',
  cancelada: 'Cancelada',
  pendente: 'Aguardando pagamento',
}

export default function PlanoPage() {
  const router = useRouter()
  const [dados, setDados] = useState<StatusAssinatura | null>(null)
  const [erro, setErro] = useState<string | null>(null)
  const [assinando, setAssinando] = useState<string | null>(null)
  const [cancelando, setCancelando] = useState(false)

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    api
      .get<StatusAssinatura>('/assinatura')
      .then(setDados)
      .catch((err) => setErro(err instanceof ApiError ? err.message : 'Erro ao carregar sua assinatura'))
  }, [router])

  async function assinar(planoChave: string) {
    setErro(null)
    setAssinando(planoChave)
    try {
      const { checkout_url } = await api.post<{ checkout_url: string }>('/assinatura/checkout', {
        plano_chave: planoChave,
      })
      window.location.href = checkout_url
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Não foi possível iniciar o checkout')
      setAssinando(null)
    }
  }

  async function cancelar() {
    if (
      !confirm(
        'Cancelar sua assinatura? Você para de ser cobrado a partir de agora, mas perde acesso pra cadastrar novos alunos até escolher um plano de novo.'
      )
    ) {
      return
    }
    setErro(null)
    setCancelando(true)
    try {
      await api.post('/assinatura/cancelar', {})
      const atualizado = await api.get<StatusAssinatura>('/assinatura')
      setDados(atualizado)
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Não foi possível cancelar sua assinatura')
    } finally {
      setCancelando(false)
    }
  }

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
        <div className="mb-8">
          <h1 className="font-display text-2xl font-bold tracking-tight text-ink">Meu Plano</h1>
          <span className="title-accent" />
          <p className="mt-2 text-sm text-ink-muted">Sua assinatura com o TrainOS e o limite de alunos do seu plano</p>
        </div>

        {erro && <p className="mb-4 text-sm text-danger">{erro}</p>}
        {dados === null && !erro && <p className="text-ink-muted">Carregando...</p>}

        {dados && (
          <div className="space-y-8">
            <section>
              <div
                className={`rounded-2xl p-5 ${
                  dados.status === 'atrasada' || dados.status === 'bloqueada' ? 'stat-hero-cta' : 'stat-hero'
                }`}
              >
                <p className="stat-hero-label text-xs font-medium uppercase tracking-wider">
                  {dados.em_teste ? 'Teste grátis' : dados.plano_chave ? dados.planos[dados.plano_chave]?.nome : 'Sem plano ativo'}
                </p>
                <p className="stat-number mt-1 text-2xl font-bold text-white">
                  {dados.em_teste
                    ? `${dados.dias_restantes_teste} dia${dados.dias_restantes_teste === 1 ? '' : 's'} restantes`
                    : dados.plano_chave
                      ? formatarMoeda(dados.planos[dados.plano_chave]?.valor_mensal ?? 0) + '/mês'
                      : '—'}
                </p>
                <p className="mt-2 text-sm text-white/80">
                  {dados.alunos_ativos} aluno{dados.alunos_ativos === 1 ? '' : 's'} ativo{dados.alunos_ativos === 1 ? '' : 's'}
                  {dados.limite_alunos !== null ? ` de até ${dados.limite_alunos}` : ' (sem limite no teste grátis)'}
                </p>
                {dados.status && (
                  <p className="mt-1 text-sm font-semibold text-white">
                    {LABEL_STATUS[dados.status]}
                    {dados.dias_restantes_carencia !== null &&
                      dados.status === 'atrasada' &&
                      ` — restam ${dados.dias_restantes_carencia} dia${dados.dias_restantes_carencia === 1 ? '' : 's'} de carência`}
                  </p>
                )}
              </div>
              {dados.status && dados.status !== 'cancelada' && (
                <button
                  onClick={cancelar}
                  disabled={cancelando}
                  className="mt-3 text-xs font-medium text-danger transition hover:underline"
                >
                  {cancelando ? 'Cancelando...' : 'Cancelar assinatura'}
                </button>
              )}
            </section>

            {dados.faturas.length > 0 && (
              <section>
                <h2 className="mb-3 text-sm font-semibold uppercase tracking-wider text-ink-muted">Faturas</h2>
                <div className="space-y-2">
                  {dados.faturas.map((f, i) => (
                    <div key={i} className="glass-flat flex items-center justify-between rounded-2xl px-4 py-3 text-sm">
                      <span className="text-ink-soft">{f.pago_em ? formatarData(f.pago_em) : formatarData(f.created_at.slice(0, 10))}</span>
                      <span className={f.status === 'aprovado' ? 'font-semibold text-success' : 'font-semibold text-danger'}>
                        {f.status === 'aprovado' ? 'Pago' : 'Recusado'}
                      </span>
                      <span className="font-medium text-ink">{formatarMoeda(Number(f.valor))}</span>
                    </div>
                  ))}
                </div>
              </section>
            )}

            <section>
              <h2 className="mb-3 text-sm font-semibold uppercase tracking-wider text-ink-muted">
                {dados.plano_chave && dados.status !== 'cancelada' ? 'Fazer upgrade' : 'Escolha um plano'}
              </h2>
              <div className="grid gap-3 sm:grid-cols-2">
                {Object.entries(dados.planos).map(([chave, plano]) => {
                  const ehAtual = chave === dados.plano_chave && dados.status !== 'cancelada'
                  return (
                    <div key={chave} className={`rounded-2xl p-4 ${ehAtual ? 'glass-elevated' : 'glass'}`}>
                      <p className="font-semibold text-ink">{plano.nome}</p>
                      <p className="text-sm text-ink-muted">Até {plano.limite_alunos} alunos</p>
                      <p className="stat-number mt-2 text-xl font-bold text-ink">{formatarMoeda(plano.valor_mensal)}/mês</p>
                      {ehAtual ? (
                        <p className="mt-3 text-xs font-semibold uppercase tracking-wide text-brand">Plano atual</p>
                      ) : (
                        <button
                          onClick={() => assinar(chave)}
                          disabled={assinando !== null}
                          className={`mt-3 w-full rounded-xl px-4 py-2 text-sm ${
                            chave === 'exclusive' ? 'btn-cta' : 'btn-secondary'
                          }`}
                        >
                          {assinando === chave
                            ? 'Redirecionando...'
                            : dados.plano_chave && dados.status !== 'cancelada'
                              ? 'Fazer upgrade'
                              : 'Assinar'}
                        </button>
                      )}
                    </div>
                  )
                })}
              </div>
            </section>
          </div>
        )}
      </main>
    </>
  )
}
