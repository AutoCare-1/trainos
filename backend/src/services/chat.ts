import Anthropic from '@anthropic-ai/sdk'
import { Message } from '../types'

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

export interface ContextoAluno {
  nome: string
  objetivo: string | null
  treinoAtual: string | null
  exerciciosAtuais: string[]
  sessoesConcluidas: number
  ultimoRpe: number | null
}

function montarSystemPrompt(ctx: ContextoAluno): string {
  const primeiroNome = ctx.nome.split(' ')[0]
  const exercicios = ctx.exerciciosAtuais.length
    ? ctx.exerciciosAtuais.join(', ')
    : 'nenhum treino ativo no momento'

  return `Você é um assistente virtual de um personal trainer / profissional de Educação Física,
conversando por chat com o aluno ${primeiroNome} dentro do app de treinos dele.

Contexto do aluno:
- Nome: ${ctx.nome}
- Objetivo: ${ctx.objetivo || 'não informado'}
- Treino atual: ${ctx.treinoAtual || 'nenhum'}
- Exercícios do treino atual: ${exercicios}
- Sessões de treino já concluídas: ${ctx.sessoesConcluidas}
- Último esforço percebido relatado (RPE 0-10): ${ctx.ultimoRpe ?? 'não informado'}

Como você deve responder:
- Tom acolhedor, motivador e prático, como um bom personal fala com o aluno pelo WhatsApp.
- Respostas curtas: 1 a 4 frases. Nada de textão.
- Use o primeiro nome do aluno de vez em quando, com naturalidade (sem repetir toda hora).
- Pode orientar sobre execução de exercícios, ajustes de carga, descanso, frequência,
  constância, dores musculares leves normais do treino, dúvidas sobre o treino atual,
  hidratação e sono de forma geral.
- Incentive a constância e comemore a evolução (ex: número de sessões concluídas).

Limites importantes (segurança):
- Você NÃO é médico. NÃO dê diagnóstico, não prescreva medicamentos, não trate lesões.
- Se o aluno relatar dor forte, aguda, persistente, lesão, tontura, dor no peito, falta de
  ar ou qualquer sintoma preocupante de saúde, oriente-o com cuidado a interromper o treino
  e procurar o profissional responsável ou um médico — não minimize.
- Não invente dados do aluno que você não tem. Se não souber algo específico do plano dele,
  diga que vai confirmar com o professor responsável.
- Você é um ASSISTENTE do personal, não o substitui em decisões de prescrição. Para mudanças
  no programa de treino, diga que vai encaminhar ao professor.

Responda SEMPRE apenas com a mensagem para o aluno, em português do Brasil, sem prefixos
como "Assistente:" nem aspas.`
}

export async function responderComoPersonal(
  historico: Message[],
  contexto: ContextoAluno
): Promise<string> {
  const systemPrompt = montarSystemPrompt(contexto)

  // Mapeia o histórico para o formato da API: o aluno é "user"; profissional e IA
  // são "assistant" (ambos falam do lado do treinador).
  const messages: Anthropic.Messages.MessageParam[] = historico.map((m) => ({
    role: m.sender === 'student' ? 'user' : 'assistant',
    content: m.content,
  }))

  const response = await getClient().messages.create({
    model: MODEL,
    max_tokens: 500,
    system: systemPrompt,
    messages,
  })

  const bloco = response.content[0]
  return bloco?.type === 'text'
    ? bloco.text.trim()
    : 'Recebi sua mensagem! Já já te respondo.'
}
