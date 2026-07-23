import Anthropic from '@anthropic-ai/sdk'
import { MachinesJson, RecommendedItem } from '../types'

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

export interface ExercicioDisponivel {
  id: string
  name: string
  muscle_group: string
  equipment: string | null
}

export interface ContextoAlunoRecomendacao {
  nome: string
  objective: string | null
  healthNotes: string | null
  limitacoesParQ: string[]
  daysPerWeek: number
}

export interface RecomendacaoTreino {
  name: string
  split_type: string
  reasoning: string
  items: RecommendedItem[]
}

function buildSystemPrompt(exercicios: ExercicioDisponivel[], contexto: ContextoAlunoRecomendacao, machines: MachinesJson): string {
  const listaExercicios = exercicios
    .map((e) => `- id: ${e.id} | ${e.name} (${e.muscle_group}${e.equipment ? `, equipamento: ${e.equipment}` : ''})`)
    .join('\n')

  const listaMaquinas = machines.machines.map((m) => `- ${m.name} (${m.category})`).join('\n') || 'nenhuma detectada'

  return `Você é um personal trainer experiente, prescrevendo treino pra um aluno do Clube Mais
(público majoritariamente idoso — priorize sempre segurança, execução controlada e RPE moderado).

## MÁQUINAS/EQUIPAMENTOS DETECTADOS NA ACADEMIA DO ALUNO
${listaMaquinas}

## PERFIL DO ALUNO
- Nome: ${contexto.nome}
- Objetivo: ${contexto.objective ?? 'não informado'}
- Observações de saúde: ${contexto.healthNotes ?? 'nenhuma'}
- Limitações de segurança (PAR-Q): ${contexto.limitacoesParQ.join(', ') || 'nenhuma sinalizada'}
- Frequência: ${contexto.daysPerWeek} dias/semana

## BIBLIOTECA DE EXERCÍCIOS DISPONÍVEL (use SOMENTE exercícios desta lista, referenciando o "id" exato)
${listaExercicios}

## TAREFA
Este app mantém APENAS UM treino ativo por aluno (uma lista única de exercícios, sem divisão
por dia da semana) — o aluno repete essa mesma lista a cada sessão. Monte UM treino completo
(full-body, cobrindo os principais grupos musculares de forma equilibrada) com 6 a 10 exercícios,
usando SOMENTE exercícios da biblioteca acima cujo equipamento seja compatível com as máquinas
detectadas (ou que não exijam equipamento, tipo exercícios de peso corporal). Considere que o
aluno treina ${contexto.daysPerWeek}x/semana ao dosar volume total. Se limitações de saúde foram
sinalizadas, evite exercícios que as agravem. A ordem dos itens no array "items" é a ordem de
execução do treino.

Responda APENAS com um JSON válido, sem texto antes ou depois, neste formato exato:
{
  "name": "Treino Full Body — Clube Mais",
  "split_type": "full_body",
  "reasoning": "justificativa curta da escolha dos exercícios e do volume",
  "items": [
    {
      "exercise_id": "uuid-exato-da-lista",
      "sets": 3,
      "reps": "10-12",
      "rest_seconds": 60,
      "notes": "execução controlada, RPE 6-7"
    }
  ]
}`
}

/** Gera recomendação de treino a partir das máquinas detectadas, usando só exercícios da biblioteca real (evita texto livre não-acionável). */
export async function recomendarTreino(
  machines: MachinesJson,
  exercicios: ExercicioDisponivel[],
  contexto: ContextoAlunoRecomendacao
): Promise<RecomendacaoTreino> {
  const response = await getClient().messages.create({
    model: MODEL,
    max_tokens: 2500,
    system: buildSystemPrompt(exercicios, contexto, machines),
    messages: [{ role: 'user', content: 'Gere a recomendação conforme as instruções.' }],
  })

  const bloco = response.content[0]
  const texto = bloco?.type === 'text' ? bloco.text : ''
  const jsonMatch = texto.match(/\{[\s\S]*\}/)
  if (!jsonMatch) throw new Error('Resposta da IA não trouxe JSON válido')

  const parsed = JSON.parse(jsonMatch[0]) as {
    name: string
    split_type: string
    reasoning: string
    items: Array<{
      exercise_id: string
      sets: number
      reps: string
      rest_seconds: number
      notes?: string
    }>
  }

  const porId = new Map(exercicios.map((e) => [e.id, e]))
  const items: RecommendedItem[] = parsed.items
    .filter((item) => porId.has(item.exercise_id))
    .map((item) => ({
      exercise_id: item.exercise_id,
      exercise_name: porId.get(item.exercise_id)!.name,
      sets: item.sets,
      reps: item.reps,
      rest_seconds: item.rest_seconds,
      notes: item.notes ?? '',
    }))

  if (items.length === 0) throw new Error('IA não recomendou nenhum exercício válido da biblioteca')

  return { name: parsed.name, split_type: parsed.split_type, reasoning: parsed.reasoning, items }
}
