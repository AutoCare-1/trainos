'use client'

import { useCallback, useEffect, useState } from 'react'
import { useRouter } from 'next/navigation'
import { AlertTriangle, Table2, TrendingUp } from 'lucide-react'
import Navbar from '@/components/Navbar'
import GraficoLinhas from '@/components/crm/GraficoLinhas'
import GraficoBarras from '@/components/crm/GraficoBarras'
import { api, ApiError } from '@/lib/api'
import { CrmAdmin, CrmCusto, CrmDashboard, CrmSocio } from '@/lib/types'

// Cores das séries: tokens da marca já validados pra daltonismo (protanopia e
// deuteranopia, pior par ΔE 11,3). Ver comentário em GraficoLinhas.
const COR_FATURAMENTO = 'var(--brand-blue)'
const COR_CUSTO = 'var(--accent-amber)'
const COR_LUCRO = 'var(--accent-teal)'

const LABEL_PIPELINE: Record<string, string> = {
  chat_autopilot: 'Chat com aluno',
  consultor_ia: 'Consultor IA',
  ideias_conteudo: 'Ideias de conteúdo',
  evolucao_fisica: 'Evolução física',
  academia_analise: 'Análise de academia',
  academia_recomendacao: 'Recomendação de treino',
  analisar_forma: 'Análise de forma',
  avaliacao_postural: 'Avaliação postural',
}

const ABAS = [
  { id: 'visao', label: 'Visão geral' },
  { id: 'custos', label: 'Custos' },
  { id: 'socios', label: 'Divisão' },
  { id: 'acessos', label: 'Acessos' },
] as const

type Aba = (typeof ABAS)[number]['id']

function moeda(valor: number): string {
  return valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
}

/** Eixo Y: compacto pra caber sem virar uma parede de dígitos. */
function moedaCompacta(valor: number): string {
  const abs = Math.abs(valor)
  if (abs >= 1000) return `R$ ${(valor / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 })} mil`
  return `R$ ${valor.toLocaleString('pt-BR', { maximumFractionDigits: 0 })}`
}

function mesCurto(mes: string): string {
  const [, m] = mes.split('-')
  return ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'][Number(m) - 1] ?? mes
}

/** Data vinda como "YYYY-MM-DD" — nunca passar por new Date(), que joga pro dia
 *  anterior em fuso negativo (ver lib/checkinDates.ts). */
function dataBr(iso: string): string {
  const [ano, mes, dia] = iso.slice(0, 10).split('-')
  return `${dia}/${mes}/${ano}`
}

export default function AdminCrmPage() {
  const router = useRouter()
  const [aba, setAba] = useState<Aba>('visao')
  const [meses, setMeses] = useState(12)
  const [dados, setDados] = useState<CrmDashboard | null>(null)
  const [carregando, setCarregando] = useState(false)
  const [erro, setErro] = useState<string | null>(null)
  const [tabelaSerie, setTabelaSerie] = useState(false)
  const [tabelaCusto, setTabelaCusto] = useState(false)

  const [custos, setCustos] = useState<CrmCusto[]>([])
  const [socios, setSocios] = useState<CrmSocio[]>([])
  const [admins, setAdmins] = useState<CrmAdmin[]>([])

  // Contador de recarga: as mutações (novo custo, novo sócio) só incrementam
  // isso e o efeito refaz a busca — evita duplicar a lógica de fetch entre o
  // carregamento inicial e o refresh manual.
  const [versao, setVersao] = useState(0)
  const recarregarDashboard = useCallback(() => setVersao((v) => v + 1), [])

  useEffect(() => {
    if (!localStorage.getItem('trainos_token')) {
      router.replace('/login')
      return
    }
    api
      .get<CrmDashboard>(`/admin/dashboard?meses=${meses}`)
      .then((d) => {
        setDados(d)
        setErro(null)
      })
      .catch((err) => {
        // O backend responde 404 pra quem não é admin (não revela que o CRM
        // existe) — do lado da tela isso vira "volta pro app".
        if (err instanceof ApiError && err.status === 404) {
          router.replace('/dashboard')
          return
        }
        setErro(err instanceof ApiError ? err.message : 'Erro ao carregar o CRM')
      })
      .finally(() => setCarregando(false))
  }, [meses, versao, router])

  // Marcar "carregando" acontece aqui, no clique, e não dentro do efeito: o
  // gráfico segura o desenho anterior esmaecido enquanto os novos dados chegam,
  // sem esqueleto nem salto de layout.
  const trocarPeriodo = (m: number) => {
    setCarregando(true)
    setMeses(m)
  }

  useEffect(() => {
    if (aba === 'custos') api.get<{ custos: CrmCusto[] }>('/admin/custos').then((r) => setCustos(r.custos)).catch(() => {})
    if (aba === 'socios') api.get<{ socios: CrmSocio[] }>('/admin/socios').then((r) => setSocios(r.socios)).catch(() => {})
    if (aba === 'acessos') api.get<{ admins: CrmAdmin[] }>('/admin/admins').then((r) => setAdmins(r.admins)).catch(() => {})
  }, [aba])

  const serie = (dados?.serie_mensal ?? []).map((m) => ({
    rotulo: mesCurto(m.mes),
    faturamento: m.faturamento,
    custo: m.custo_ia + m.custo_plataforma,
    lucro: m.lucro,
  }))

  return (
    <>
      <Navbar />
      <main className="mx-auto w-full max-w-5xl flex-1 px-4 py-8">
        <div className="mb-6">
          <h1 className="font-display text-2xl font-bold tracking-tight text-ink">CRM do produto</h1>
          <span className="title-accent" />
          <p className="mt-2 text-sm text-ink-muted">
            Faturamento, custo e lucro do Clube Mais — visível só para os donos.
          </p>
        </div>

        {erro && <p className="mb-4 text-sm text-danger">{erro}</p>}

        <div className="mb-6 flex flex-wrap gap-2">
          {ABAS.map((a) => (
            <button
              key={a.id}
              onClick={() => setAba(a.id)}
              className={`rounded-full px-4 py-1.5 text-sm transition ${
                aba === a.id ? 'bg-brand text-white' : 'glass-flat text-ink-soft hover:text-ink'
              }`}
            >
              {a.label}
            </button>
          ))}
        </div>

        {aba === 'visao' && dados && (
          <VisaoGeral
            dados={dados}
            serie={serie}
            meses={meses}
            setMeses={trocarPeriodo}
            carregando={carregando}
            tabelaSerie={tabelaSerie}
            setTabelaSerie={setTabelaSerie}
            tabelaCusto={tabelaCusto}
            setTabelaCusto={setTabelaCusto}
          />
        )}

        {aba === 'custos' && (
          <AbaCustos
            custos={custos}
            recarregar={() => {
              recarregarDashboard()
              api.get<{ custos: CrmCusto[] }>('/admin/custos').then((r) => setCustos(r.custos))
            }}
          />
        )}
        {aba === 'socios' && (
          <AbaSocios
            socios={socios}
            recarregar={() => {
              recarregarDashboard()
              api.get<{ socios: CrmSocio[] }>('/admin/socios').then((r) => setSocios(r.socios))
            }}
          />
        )}
        {aba === 'acessos' && <AbaAcessos admins={admins} recarregar={() => api.get<{ admins: CrmAdmin[] }>('/admin/admins').then((r) => setAdmins(r.admins))} />}

        {!dados && !erro && <p className="text-ink-muted">Carregando...</p>}
      </main>
    </>
  )
}

function VisaoGeral({
  dados, serie, meses, setMeses, carregando,
  tabelaSerie, setTabelaSerie, tabelaCusto, setTabelaCusto,
}: {
  dados: CrmDashboard
  serie: { rotulo: string; faturamento: number; custo: number; lucro: number }[]
  meses: number
  setMeses: (m: number) => void
  carregando: boolean
  tabelaSerie: boolean
  setTabelaSerie: (v: boolean) => void
  tabelaCusto: boolean
  setTabelaCusto: (v: boolean) => void
}) {
  const { resumo, assinantes } = dados
  const custoTotal = resumo.custo_ia + resumo.custo_plataforma

  const pipelines = dados.custo_ia_por_pipeline.map((p) => ({
    rotulo: LABEL_PIPELINE[p.pipeline] ?? p.pipeline,
    valor: p.custo_brl,
    detalhe: `${p.chamadas.toLocaleString('pt-BR')} chamada${p.chamadas === 1 ? '' : 's'} · ${(p.input_tokens + p.output_tokens).toLocaleString('pt-BR')} tokens`,
  }))

  return (
    <div className={`space-y-8 transition-opacity ${carregando ? 'opacity-60' : 'opacity-100'}`}>
      {/* Uma única linha de filtro, acima de tudo que ela afeta. */}
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-xs font-medium uppercase tracking-wider text-ink-muted">Período</span>
        {[3, 6, 12].map((m) => (
          <button
            key={m}
            onClick={() => setMeses(m)}
            className={`rounded-xl px-3 py-1.5 text-sm transition ${
              meses === m ? 'bg-brand text-white' : 'glass-flat text-ink-soft hover:text-ink'
            }`}
          >
            {m} meses
          </button>
        ))}
      </div>

      {dados.modelos_sem_preco.length > 0 && (
        <div className="glass flex items-start gap-3 rounded-2xl border-warning p-4 [--glass-bg:var(--color-warning-soft)]">
          <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-warning" />
          <p className="text-sm text-ink-soft">
            Rodaram chamadas com modelo sem preço cadastrado ({dados.modelos_sem_preco.join(', ')}). O custo delas
            entrou como zero, então o lucro abaixo está otimista. Cadastre o preço em <code>config/ia_precos.php</code>.
          </p>
        </div>
      )}

      {/* Figura heroica: o número que a tela existe pra responder. */}
      <section className="stat-hero rounded-2xl p-6">
        <p className="stat-hero-label text-xs font-medium uppercase tracking-wider">
          Lucro deste mês
        </p>
        <p className="stat-number mt-1 text-5xl font-bold text-white [font-variant-numeric:proportional-nums]">
          {moeda(resumo.lucro)}
        </p>
        <p className="mt-2 text-sm text-white/80">
          {moeda(resumo.faturamento)} de faturamento − {moeda(custoTotal)} de custo
          {resumo.margem_pct !== null && ` · margem de ${resumo.margem_pct}%`}
        </p>
      </section>

      <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Tile rotulo="Receita recorrente (MRR)" valor={moeda(resumo.mrr)} nota="se ninguém cancelar" />
        <Tile rotulo="Custo de IA" valor={moeda(resumo.custo_ia)} nota={`US$ ${resumo.custo_ia_usd.toFixed(2)} · dólar a ${moeda(dados.cotacao_usd_brl)}`} />
        <Tile rotulo="Custo de plataforma" valor={moeda(resumo.custo_plataforma)} nota="hospedagem e ferramentas" />
        <Tile
          rotulo="Assinaturas ativas"
          valor={String(assinantes.ativas)}
          nota={`${assinantes.em_teste_gratis} em teste · ${assinantes.total_personais} contas`}
        />
      </section>

      {assinantes.teste_expirado_sem_assinar > 0 && (
        <p className="text-xs text-ink-muted">
          <strong className="font-semibold text-ink-soft">{assinantes.teste_expirado_sem_assinar}</strong>{' '}
          {assinantes.teste_expirado_sem_assinar === 1 ? 'conta testou' : 'contas testaram'} o app, o prazo grátis
          acabou e nunca chegaram a assinar — bom público pra retomar contato.
        </p>
      )}

      <section className="glass rounded-2xl p-5">
        <div className="mb-4 flex items-start justify-between gap-3">
          <div>
            <h2 className="flex items-center gap-2 font-display text-base font-semibold text-ink">
              <TrendingUp className="h-4 w-4 text-brand" /> Faturamento, custo e lucro
            </h2>
            <p className="mt-0.5 text-xs text-ink-muted">Últimos {meses} meses</p>
          </div>
          <BotaoTabela ativo={tabelaSerie} onClick={() => setTabelaSerie(!tabelaSerie)} />
        </div>

        {tabelaSerie ? (
          <TabelaSerie linhas={serie} />
        ) : (
          <GraficoLinhas
            dados={serie}
            series={[
              { key: 'faturamento', label: 'Faturamento', cor: COR_FATURAMENTO },
              { key: 'custo', label: 'Custo', cor: COR_CUSTO },
              { key: 'lucro', label: 'Lucro', cor: COR_LUCRO },
            ]}
            formatarValor={moeda}
            formatarEixo={moedaCompacta}
            destaque="lucro"
          />
        )}
      </section>

      <section className="glass rounded-2xl p-5">
        <div className="mb-4 flex items-start justify-between gap-3">
          <div>
            <h2 className="font-display text-base font-semibold text-ink">Custo de IA por funcionalidade</h2>
            <p className="mt-0.5 text-xs text-ink-muted">Neste mês, convertido para reais</p>
          </div>
          <BotaoTabela ativo={tabelaCusto} onClick={() => setTabelaCusto(!tabelaCusto)} />
        </div>

        {tabelaCusto ? (
          <TabelaCusto itens={dados.custo_ia_por_pipeline} />
        ) : (
          <GraficoBarras
            itens={pipelines}
            cor={COR_CUSTO}
            formatarValor={moeda}
            rotuloAcessivel="Custo de IA por funcionalidade"
          />
        )}
      </section>

      {dados.planos.length > 0 && (
        <section className="glass rounded-2xl p-5">
          <h2 className="mb-4 font-display text-base font-semibold text-ink">Receita por plano</h2>
          <GraficoBarras
            itens={dados.planos.map((p) => ({
              rotulo: p.nome,
              valor: p.mrr,
              detalhe: `${p.assinantes} assinante${p.assinantes === 1 ? '' : 's'}`,
            }))}
            cor={COR_FATURAMENTO}
            formatarValor={moeda}
            rotuloAcessivel="Receita recorrente por plano"
          />
        </section>
      )}

      <section className="glass rounded-2xl p-5">
        <h2 className="font-display text-base font-semibold text-ink">Divisão do lucro deste mês</h2>
        {dados.rateio_lucro.length === 0 ? (
          <p className="mt-2 text-sm text-ink-muted">
            Nenhum sócio cadastrado ainda — configure na aba &quot;Divisão&quot;.
          </p>
        ) : (
          <div className="mt-4 space-y-2">
            {dados.rateio_lucro.map((s) => (
              <div key={s.id} className="glass-flat flex items-center justify-between rounded-xl px-4 py-3">
                <span className="text-sm text-ink-soft">
                  {s.nome} <span className="text-ink-muted">· {s.percentual}%</span>
                </span>
                <span className="font-semibold text-ink [font-variant-numeric:tabular-nums]">{moeda(s.valor)}</span>
              </div>
            ))}
          </div>
        )}
      </section>
    </div>
  )
}

function Tile({ rotulo, valor, nota }: { rotulo: string; valor: string; nota: string }) {
  return (
    <div className="glass rounded-2xl p-4">
      <p className="text-xs font-medium uppercase tracking-wider text-ink-muted">{rotulo}</p>
      <p className="stat-number mt-1 text-2xl font-bold text-ink [font-variant-numeric:proportional-nums]">{valor}</p>
      <p className="mt-1 text-xs text-ink-muted">{nota}</p>
    </div>
  )
}

function BotaoTabela({ ativo, onClick }: { ativo: boolean; onClick: () => void }) {
  return (
    <button
      onClick={onClick}
      className="flex shrink-0 items-center gap-1.5 rounded-xl px-3 py-1.5 text-xs text-ink-soft transition hover:bg-surface-soft"
      aria-pressed={ativo}
    >
      <Table2 className="h-3.5 w-3.5" />
      {ativo ? 'Ver gráfico' : 'Ver tabela'}
    </button>
  )
}

function TabelaSerie({ linhas }: { linhas: { rotulo: string; faturamento: number; custo: number; lucro: number }[] }) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-[380px] text-sm [font-variant-numeric:tabular-nums]">
        <thead>
          <tr className="border-b border-line text-left text-xs uppercase tracking-wider text-ink-muted">
            <th className="py-2 pr-3 font-medium">Mês</th>
            <th className="py-2 pr-3 text-right font-medium">Faturamento</th>
            <th className="py-2 pr-3 text-right font-medium">Custo</th>
            <th className="py-2 text-right font-medium">Lucro</th>
          </tr>
        </thead>
        <tbody>
          {linhas.map((l) => (
            <tr key={l.rotulo} className="border-b border-line-soft last:border-0">
              <td className="py-2 pr-3 text-ink-soft">{l.rotulo}</td>
              <td className="py-2 pr-3 text-right text-ink">{moeda(l.faturamento)}</td>
              <td className="py-2 pr-3 text-right text-ink">{moeda(l.custo)}</td>
              <td className="py-2 text-right font-semibold text-ink">{moeda(l.lucro)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function TabelaCusto({ itens }: { itens: CrmDashboard['custo_ia_por_pipeline'] }) {
  if (itens.length === 0) return <p className="py-6 text-center text-sm text-ink-muted">Nenhuma chamada neste mês.</p>
  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-[420px] text-sm [font-variant-numeric:tabular-nums]">
        <thead>
          <tr className="border-b border-line text-left text-xs uppercase tracking-wider text-ink-muted">
            <th className="py-2 pr-3 font-medium">Funcionalidade</th>
            <th className="py-2 pr-3 text-right font-medium">Chamadas</th>
            <th className="py-2 pr-3 text-right font-medium">Tokens</th>
            <th className="py-2 text-right font-medium">Custo</th>
          </tr>
        </thead>
        <tbody>
          {itens.map((p) => (
            <tr key={p.pipeline} className="border-b border-line-soft last:border-0">
              <td className="py-2 pr-3 text-ink-soft">{LABEL_PIPELINE[p.pipeline] ?? p.pipeline}</td>
              <td className="py-2 pr-3 text-right text-ink">{p.chamadas.toLocaleString('pt-BR')}</td>
              <td className="py-2 pr-3 text-right text-ink">{(p.input_tokens + p.output_tokens).toLocaleString('pt-BR')}</td>
              <td className="py-2 text-right font-semibold text-ink">{moeda(p.custo_brl)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

function AbaCustos({ custos, recarregar }: { custos: CrmCusto[]; recarregar: () => void }) {
  const [descricao, setDescricao] = useState('')
  const [valor, setValor] = useState('')
  const [recorrente, setRecorrente] = useState(true)
  const [erro, setErro] = useState<string | null>(null)
  const [salvando, setSalvando] = useState(false)

  async function adicionar(e: React.FormEvent) {
    e.preventDefault()
    if (!descricao.trim() || !valor) {
      setErro('Preencha descrição e valor.')
      return
    }
    setErro(null)
    setSalvando(true)
    try {
      await api.post('/admin/custos', {
        description: descricao.trim(),
        amount: Number(valor),
        is_recurring: recorrente,
        starts_on: new Date().toISOString().slice(0, 10),
      })
      setDescricao('')
      setValor('')
      recarregar()
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Não foi possível salvar')
    } finally {
      setSalvando(false)
    }
  }

  async function encerrar(id: string) {
    if (!confirm('Encerrar este custo? Ele ainda conta neste mês e para a partir do mês que vem.')) return
    try {
      await api.patch(`/admin/custos/${id}/encerrar`, {})
      recarregar()
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Não foi possível encerrar')
    }
  }

  return (
    <div className="space-y-6">
      <section className="glass rounded-2xl p-5">
        <h2 className="mb-1 font-display text-base font-semibold text-ink">Custos da plataforma</h2>
        <p className="mb-4 text-xs text-ink-muted">
          Hospedagem, banco, domínio, ferramentas. O custo de IA entra sozinho — não precisa lançar aqui.
        </p>

        <form onSubmit={adicionar} className="grid gap-3 sm:grid-cols-[1fr_140px_auto]">
          <input
            value={descricao}
            onChange={(e) => setDescricao(e.target.value)}
            placeholder="Ex: Servidor de produção"
            className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
          />
          <input
            type="number"
            min={0}
            step="0.01"
            inputMode="decimal"
            value={valor}
            onChange={(e) => setValor(e.target.value)}
            placeholder="R$"
            className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
          />
          <button type="submit" disabled={salvando} className="btn-primary rounded-xl px-4 py-2.5 text-sm">
            {salvando ? 'Salvando...' : 'Adicionar'}
          </button>
          <label className="flex cursor-pointer items-center gap-2 text-sm text-ink-soft sm:col-span-3">
            <input type="checkbox" checked={recorrente} onChange={(e) => setRecorrente(e.target.checked)} />
            Cobrado todo mês
          </label>
        </form>

        {erro && <p className="mt-3 text-sm text-danger">{erro}</p>}
      </section>

      <div className="space-y-2">
        {custos.length === 0 && <p className="text-sm text-ink-muted">Nenhum custo cadastrado ainda.</p>}
        {custos.map((c) => (
          <div key={c.id} className="glass flex items-center justify-between gap-3 rounded-2xl px-4 py-3">
            <div className="min-w-0">
              <p className="truncate text-sm font-medium text-ink">{c.description}</p>
              <p className="text-xs text-ink-muted">
                {c.is_recurring ? 'Todo mês' : 'Avulso'} · desde {dataBr(c.starts_on)}
                {c.ends_on && ` · encerrado, última cobrança ${dataBr(c.ends_on)}`}
              </p>
            </div>
            <div className="flex shrink-0 items-center gap-3">
              <span className="font-semibold text-ink [font-variant-numeric:tabular-nums]">
                {moeda(Number(c.amount))}
              </span>
              {/* Só recorrente tem o que encerrar — avulso já conta uma vez só
                  no mês de starts_on, então "Encerrar" nele seria um botão sem
                  efeito nenhum no cálculo (mesmo padrão de gastos/page.tsx). */}
              {c.is_recurring && !c.ends_on && (
                <button onClick={() => encerrar(c.id)} className="text-xs font-medium text-danger hover:underline">
                  Encerrar
                </button>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}

function AbaSocios({ socios, recarregar }: { socios: CrmSocio[]; recarregar: () => void }) {
  const [nome, setNome] = useState('')
  const [percentual, setPercentual] = useState('')
  const [erro, setErro] = useState<string | null>(null)

  const total = socios.reduce((s, x) => s + Number(x.percentual), 0)

  async function adicionar(e: React.FormEvent) {
    e.preventDefault()
    if (!nome.trim() || !percentual) {
      setErro('Preencha nome e percentual.')
      return
    }
    setErro(null)
    try {
      await api.post('/admin/socios', { nome: nome.trim(), percentual: Number(percentual) })
      setNome('')
      setPercentual('')
      recarregar()
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Não foi possível salvar')
    }
  }

  async function remover(id: string, nomeSocio: string) {
    if (!confirm(`Remover ${nomeSocio} da divisão? O rateio dos meses passados não muda.`)) return
    try {
      await api.delete(`/admin/socios/${id}`)
      recarregar()
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Não foi possível remover')
    }
  }

  return (
    <div className="space-y-6">
      <section className="glass rounded-2xl p-5">
        <h2 className="mb-1 font-display text-base font-semibold text-ink">Divisão do lucro</h2>
        <p className="mb-4 text-xs text-ink-muted">
          Mudar um percentual não reescreve o passado: o rateio de meses anteriores continua com o acordo daquele mês.
        </p>

        <form onSubmit={adicionar} className="grid gap-3 sm:grid-cols-[1fr_120px_auto]">
          <input
            value={nome}
            onChange={(e) => setNome(e.target.value)}
            placeholder="Nome do sócio"
            className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
          />
          <input
            type="number"
            min={0}
            max={100}
            step="0.01"
            inputMode="decimal"
            value={percentual}
            onChange={(e) => setPercentual(e.target.value)}
            placeholder="%"
            className="input-dark w-full rounded-xl px-4 py-2.5 text-sm"
          />
          <button type="submit" className="btn-primary rounded-xl px-4 py-2.5 text-sm">
            Adicionar
          </button>
        </form>

        <p className={`mt-3 text-xs ${total > 100 ? 'text-danger' : 'text-ink-muted'}`}>
          Total distribuído: {total}% {total < 100 && `· ${(100 - total).toFixed(2)}% ainda não atribuído`}
        </p>
        {erro && <p className="mt-2 text-sm text-danger">{erro}</p>}
      </section>

      <div className="space-y-2">
        {socios.map((s) => (
          <div key={s.id} className="glass flex items-center justify-between gap-3 rounded-2xl px-4 py-3">
            <div>
              <p className="text-sm font-medium text-ink">{s.nome}</p>
              <p className="text-xs text-ink-muted">desde {dataBr(s.starts_on)}</p>
            </div>
            <div className="flex items-center gap-3">
              <span className="font-semibold text-ink [font-variant-numeric:tabular-nums]">{Number(s.percentual)}%</span>
              <button onClick={() => remover(s.id, s.nome)} className="text-xs font-medium text-danger hover:underline">
                Remover
              </button>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}

function AbaAcessos({ admins, recarregar }: { admins: CrmAdmin[]; recarregar: () => void }) {
  const [email, setEmail] = useState('')
  const [erro, setErro] = useState<string | null>(null)
  const [ok, setOk] = useState<string | null>(null)

  async function promover(e: React.FormEvent) {
    e.preventDefault()
    setErro(null)
    setOk(null)
    try {
      await api.post('/admin/admins', { email: email.trim() })
      setOk(`${email.trim()} agora tem acesso ao CRM.`)
      setEmail('')
      recarregar()
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Não foi possível promover')
    }
  }

  async function revogar(id: string, nome: string) {
    if (!confirm(`Tirar o acesso de ${nome} ao CRM? A conta de personal continua funcionando normalmente.`)) return
    setErro(null)
    try {
      await api.delete(`/admin/admins/${id}`)
      recarregar()
    } catch (err) {
      setErro(err instanceof ApiError ? err.message : 'Não foi possível remover')
    }
  }

  return (
    <div className="space-y-6">
      <section className="glass rounded-2xl p-5">
        <h2 className="mb-1 font-display text-base font-semibold text-ink">Quem vê o CRM</h2>
        <p className="mb-4 text-xs text-ink-muted">
          A pessoa precisa já ter uma conta no app. Quem não é admin recebe &quot;não encontrado&quot; e nem descobre
          que estas telas existem.
        </p>

        <form onSubmit={promover} className="flex flex-wrap gap-3">
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="email@daconta.com"
            className="input-dark min-w-[220px] flex-1 rounded-xl px-4 py-2.5 text-sm"
          />
          <button type="submit" className="btn-primary rounded-xl px-4 py-2.5 text-sm">
            Dar acesso
          </button>
        </form>

        {erro && <p className="mt-3 text-sm text-danger">{erro}</p>}
        {ok && <p className="mt-3 text-sm text-success">{ok}</p>}
      </section>

      <div className="space-y-2">
        {admins.map((a) => (
          <div key={a.id} className="glass flex items-center justify-between gap-3 rounded-2xl px-4 py-3">
            <div className="min-w-0">
              <p className="truncate text-sm font-medium text-ink">{a.name}</p>
              <p className="truncate text-xs text-ink-muted">{a.email}</p>
            </div>
            <button onClick={() => revogar(a.id, a.name)} className="shrink-0 text-xs font-medium text-danger hover:underline">
              Remover
            </button>
          </div>
        ))}
      </div>
    </div>
  )
}
