import Anthropic from '@anthropic-ai/sdk'
import { pool } from '../db/pool'
import { TrendCache } from '../types'

let _client: Anthropic | null = null
function getClient(): Anthropic {
  if (!_client) {
    const apiKey = process.env.ANTHROPIC_API_KEY
    if (!apiKey) throw new Error('ANTHROPIC_API_KEY não configurada no .env')
    _client = new Anthropic({ apiKey })
  }
  return _client
}

const MODEL = 'claude-haiku-4-5-20251001'
const CACHE_VALIDADE_HORAS = 24

function extrairTexto(blocks: Anthropic.Messages.ContentBlock[]): string {
  return blocks
    .filter((b): b is Anthropic.Messages.TextBlock => b.type === 'text')
    .map((b) => b.text)
    .join('\n')
    .trim()
}

/** Chamada CARA (usa busca na web) — só roda quando o cache expira. Foca só em
 * FORMATO/tendência, nunca em dado de aluno (isso entra na chamada barata depois). */
async function buscarTendenciasNaWeb(): Promise<string> {
  const response = await getClient().messages.create({
    model: MODEL,
    max_tokens: 700,
    tools: [{ type: 'web_search_20250305', name: 'web_search', max_uses: 3 }],
    messages: [
      {
        role: 'user',
        content: `Pesquise na web quais são as tendências ATUAIS de formato de conteúdo pra Instagram
no nicho de fitness/personal trainer/academia: tipos de reels em alta, estilo de gancho
(hook) dos primeiros segundos, áudios/sons do momento, formatos de carrossel ou
antes-depois que estão performando bem.

Responda com um resumo curto e prático (bullet points), focado só em FORMATO — como o
conteúdo é feito e editado — não invente números de engajamento nem cite marcas
específicas. Não fale sobre um aluno ou personal específico, é sobre tendência de
formato em geral.`,
      },
    ],
  })

  const texto = extrairTexto(response.content)
  return (
    texto ||
    'Sem tendências específicas encontradas — use formatos fitness clássicos (bastidor de treino, antes/depois, dica rápida em reels curto).'
  )
}

/** Tendência de formato cacheada globalmente (reaproveitada entre todos os
 * personals por até 24h) — só refaz a busca na web quando o cache expira. */
export async function obterTendenciasFormato(): Promise<string> {
  const { rows } = await pool.query<TrendCache>('select * from trend_cache order by cached_at desc limit 1')
  const cache = rows[0]
  const cacheValido = cache && Date.now() - new Date(cache.cached_at).getTime() < CACHE_VALIDADE_HORAS * 60 * 60 * 1000

  if (cacheValido) return cache.content_snapshot

  const conteudo = await buscarTendenciasNaWeb()
  await pool.query('insert into trend_cache (content_snapshot) values ($1)', [conteudo])
  return conteudo
}

export interface IdeiaGerada {
  format: 'post' | 'story' | 'reels'
  title: string
  description: string
  caption_suggestion: string
}

const SYSTEM_GERAR_IDEIAS = `Você é um assistente de marketing de conteúdo pra Instagram de um personal trainer.

Sua tarefa é gerar ideias de conteúdo (post, story ou reels) que FUNDEM duas coisas:
1. Uma tendência de FORMATO em alta (tipo de edição, gancho, áudio, estilo de reels) — é a
   "embalagem" da ideia.
2. Um dado real e agregado da base de alunos desse personal — é o "conteúdo" que preenche
   essa embalagem.

Regras importantes:
- NUNCA gere duas listas separadas. Cada ideia já deve vir pronta mostrando a fusão: como
  aplicar aquele formato em alta usando aquele dado real da base de alunos.
- NUNCA cite nome, foto, ou qualquer detalhe que identifique um aluno específico — os dados
  que você recebe já são agregados e anônimos, use-os só como número/padrão (ex: "3 alunos
  bateram recorde essa semana", sem inventar quem).
- Se o personal der um direcionamento (assunto específico), priorize esse tema nas ideias,
  mas ainda assim funda com o formato em alta e o dado agregado.
- Gere entre 3 e 5 ideias, variando os formatos (post, story, reels) quando fizer sentido.
- Tom: direto, prático, como alguém que entende de marketing fitness — nada de textão.
- NÃO usar emojis em nenhum campo, nem na legenda sugerida — só texto.

Responda SOMENTE com um array JSON válido, sem markdown, sem texto antes ou depois, no formato:
[{"format": "reels", "title": "...", "description": "...", "caption_suggestion": "..."}]

- "format": um de "post", "story", "reels".
- "title": título curto da ideia (até 8 palavras).
- "description": como executar (2 a 4 frases, prático, já citando a fusão formato+dado).
- "caption_suggestion": uma legenda pronta pra usar, curta, sem hashtags genéricas demais.`

/** Chamada BARATA (sem tools) — roda toda vez, sempre com o dado do aluno fresco. */
export async function gerarIdeiasConteudo(resumoAgregado: string, direcionamento: string | null): Promise<IdeiaGerada[]> {
  const tendencias = await obterTendenciasFormato()

  const mensagemUsuario = `Tendência de formato em alta (pesquisada na web):
${tendencias}

${resumoAgregado}

${direcionamento ? `Direcionamento do personal: ${direcionamento}` : 'Sem direcionamento específico — explore livremente os dados acima.'}`

  const response = await getClient().messages.create({
    model: MODEL,
    max_tokens: 1200,
    system: SYSTEM_GERAR_IDEIAS,
    messages: [{ role: 'user', content: mensagemUsuario }],
  })

  const texto = extrairTexto(response.content)
  const jsonLimpo = texto
    .replace(/^```json\s*/i, '')
    .replace(/```$/, '')
    .trim()

  let ideias: unknown
  try {
    ideias = JSON.parse(jsonLimpo)
  } catch {
    throw new Error('A IA não retornou um JSON válido de ideias de conteúdo')
  }
  if (!Array.isArray(ideias)) throw new Error('Resposta da IA não é uma lista de ideias')

  return ideias as IdeiaGerada[]
}
