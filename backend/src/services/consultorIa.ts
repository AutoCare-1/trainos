import Anthropic from '@anthropic-ai/sdk'
import {
  buscarResumoAluno,
  listarAlunosMaisConsistentes,
  listarAlunosSemCheckin,
  listarEstagnados,
  listarPrsRecentes,
} from './consultorFerramentas'
import { ConsultorIaMessage } from '../types'

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
const MAX_RODADAS_TOOL_USE = 5

const TOOLS: Anthropic.Messages.Tool[] = [
  {
    name: 'buscar_resumo_aluno',
    description:
      'Busca o resumo de um aluno específico pelo nome (ou id): frequência de check-in da semana atual, recordes pessoais (PR) batidos nos últimos 14 dias, e o último comentário salvo na aba Evolução física dele.',
    input_schema: {
      type: 'object',
      properties: {
        nome_ou_id: { type: 'string', description: 'Nome (ou parte do nome) do aluno, ou o id dele' },
      },
      required: ['nome_ou_id'],
    },
  },
  {
    name: 'listar_alunos_sem_checkin',
    description: 'Lista os alunos que NÃO fizeram nenhum check-in de treino nos últimos N dias.',
    input_schema: {
      type: 'object',
      properties: {
        dias: { type: 'number', description: 'Quantidade de dias pra trás a considerar (ex: 7)' },
      },
      required: ['dias'],
    },
  },
  {
    name: 'listar_prs_recentes',
    description: 'Lista os recordes pessoais (PRs) de carga batidos pelos alunos nos últimos N dias, com nome do aluno, exercício e carga.',
    input_schema: {
      type: 'object',
      properties: {
        dias: { type: 'number', description: 'Quantidade de dias pra trás a considerar (ex: 7)' },
      },
      required: ['dias'],
    },
  },
  {
    name: 'listar_estagnados',
    description:
      'Lista os alunos (e o exercício específico) que não aumentaram a carga entre as duas últimas sessões concluídas — sinal de estagnação.',
    input_schema: { type: 'object', properties: {} },
  },
  {
    name: 'listar_alunos_mais_consistentes',
    description:
      'Retorna um ranking dos alunos por consistência de check-in (dias com check-in na semana e no mês atuais), do mais consistente pro menos.',
    input_schema: { type: 'object', properties: {} },
  },
]

async function executarFerramenta(professionalId: string, nome: string, input: Record<string, unknown>): Promise<object> {
  switch (nome) {
    case 'buscar_resumo_aluno':
      return buscarResumoAluno(professionalId, String(input.nome_ou_id ?? ''))
    case 'listar_alunos_sem_checkin':
      return listarAlunosSemCheckin(professionalId, Number(input.dias) || 7)
    case 'listar_prs_recentes':
      return listarPrsRecentes(professionalId, Number(input.dias) || 7)
    case 'listar_estagnados':
      return listarEstagnados(professionalId)
    case 'listar_alunos_mais_consistentes':
      return listarAlunosMaisConsistentes(professionalId)
    default:
      return { erro: `Ferramenta desconhecida: ${nome}` }
  }
}

const SYSTEM_PROMPT = `Você é o Consultor IA de um app de personal trainer — um assistente que responde perguntas do
PERSONAL (não do aluno) sobre a própria base de alunos, em linguagem natural.

Regras fundamentais:
- Você NUNCA acessa o banco de dados diretamente e NUNCA inventa informação. A única forma de
  saber algo sobre os alunos é chamando uma das ferramentas disponíveis.
- Se a pergunta puder ser respondida com uma ou mais ferramentas, chame-as antes de responder.
- Se a pergunta NÃO se encaixar em nenhuma ferramenta disponível (ex: pedir pra alterar um
  treino, dar conselho médico, ou qualquer dado que as ferramentas não cobrem), diga
  claramente que você não tem essa informação ou não consegue fazer isso — nunca invente
  uma resposta genérica pra parecer útil.
- Sempre que tiver o dado, cite números e nomes concretos (ex: "o João não faz check-in há
  5 dias" em vez de "alguns alunos estão inativos").
- Se buscar_resumo_aluno retornar encontrado: false, informe que não achou esse aluno — não
  tente adivinhar quem seria.
- Tom direto e prático, como um colega analisando os dados junto com o personal. Respostas
  curtas quando possível, mas completas.
- Não usar emojis.

Responda sempre em português do Brasil.`

export async function responderConsultor(professionalId: string, historico: ConsultorIaMessage[]): Promise<string> {
  const messages: Anthropic.Messages.MessageParam[] = historico.map((m) => ({
    role: m.role === 'personal' ? 'user' : 'assistant',
    content: m.content,
  }))

  for (let rodada = 0; rodada < MAX_RODADAS_TOOL_USE; rodada++) {
    const response = await getClient().messages.create({
      model: MODEL,
      max_tokens: 1000,
      system: SYSTEM_PROMPT,
      tools: TOOLS,
      messages,
    })

    if (response.stop_reason !== 'tool_use') {
      const textos = response.content.filter((b): b is Anthropic.Messages.TextBlock => b.type === 'text')
      return textos.map((b) => b.text).join('\n').trim() || 'Não consegui gerar uma resposta agora.'
    }

    messages.push({ role: 'assistant', content: response.content })

    const toolUseBlocks = response.content.filter(
      (b): b is Anthropic.Messages.ToolUseBlock => b.type === 'tool_use'
    )
    const toolResults: Anthropic.Messages.ToolResultBlockParam[] = []
    for (const bloco of toolUseBlocks) {
      const resultado = await executarFerramenta(professionalId, bloco.name, (bloco.input as Record<string, unknown>) ?? {})
      toolResults.push({ type: 'tool_result', tool_use_id: bloco.id, content: JSON.stringify(resultado) })
    }
    messages.push({ role: 'user', content: toolResults })
  }

  return 'Não consegui concluir essa análise agora — tenta reformular a pergunta ou perguntar de novo em instantes.'
}
