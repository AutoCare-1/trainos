import Anthropic from '@anthropic-ai/sdk'
import fs from 'fs/promises'
import path from 'path'
import { FormFeedbackItem } from '../types'

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

type MediaType = 'image/jpeg' | 'image/png' | 'image/webp'
const MEDIA_TYPE_POR_EXTENSAO: Record<string, MediaType> = {
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.png': 'image/png',
  '.webp': 'image/webp',
}

async function lerImagemBase64(caminhoAbsoluto: string): Promise<{ data: string; mediaType: MediaType }> {
  const buffer = await fs.readFile(caminhoAbsoluto)
  const mediaType = MEDIA_TYPE_POR_EXTENSAO[path.extname(caminhoAbsoluto).toLowerCase()] ?? 'image/jpeg'
  return { data: buffer.toString('base64'), mediaType }
}

function buildSystemPrompt(exerciseName: string, nivelAluno: string): string {
  return `Você é um personal trainer experiente analisando a FORMA de execução de um exercício,
pra um app cujo público é majoritariamente idoso (Clube Mais).

## EXERCÍCIO
${exerciseName}

## NÍVEL DO ALUNO
${nivelAluno}

## MÍDIA RECEBIDA
3 frames de um vídeo curto de uma única série: início, meio e fim do movimento.

## SUA TAREFA

Avalie:
1. **Amplitude**: usa a amplitude completa e segura pro nível dele?
2. **Postura/Alinhamento**: coluna, articulações, posição inicial corretas?
3. **Tempo/Velocidade**: desce/sobe controlado ou usa impulso?
4. **Compensações**: tá "trapaceando" ou desviando do padrão seguro?
5. **Segurança**: há risco de lesão visível?

## RESPONDA APENAS COM JSON, sem texto antes ou depois, neste formato exato:

{
  "amplitude": "descrição breve (1-2 linhas)",
  "posture": "descrição breve",
  "tempo": "descrição breve",
  "compensations": "descrição ou 'nenhuma compensação detectada'",
  "safety_notes": "risco detectado ou 'seguro'",
  "three_key_feedback": [
    { "title": "Amplitude", "feedback": "descrição específica e acionável", "priority": "good" },
    { "title": "Postura", "feedback": "...", "priority": "warning" },
    { "title": "Velocidade", "feedback": "...", "priority": "good" }
  ]
}

## CRÍTICO

- Seja ESPECÍFICO e ACIONÁVEL: "coluna boa" é ruim, "coluna inclinando ~20°, tenta manter neutra" é bom.
- Respeite o nível informado — um iniciante ou idoso não tem a mesma amplitude/mobilidade de um avançado.
- Priorize segurança antes de performance.
- Escolha os 3 feedbacks que mais importam, nunca mais que isso.
- "priority": "good" = algo funcionando bem (motivar); "warning" = ajuste importante sem risco imediato;
  "critical" = parar e corrigir antes da próxima série.
- NÃO use emojis em nenhum campo de texto.`
}

export interface FormAnalysisOutcome {
  amplitude_assessment: string
  posture_assessment: string
  tempo_assessment: string
  compensations: string
  safety_notes: string
  three_key_feedback: FormFeedbackItem[]
}

/** Analisa 3 frames-chave (início/meio/fim) de uma série e retorna feedback estruturado de forma. */
export async function analisarForma(
  framesPaths: string[],
  exerciseName: string,
  nivelAluno: string
): Promise<FormAnalysisOutcome> {
  const blocos: Anthropic.Messages.ContentBlockParam[] = []
  for (const caminho of framesPaths) {
    const { data, mediaType } = await lerImagemBase64(caminho)
    blocos.push({ type: 'image', source: { type: 'base64', media_type: mediaType, data } })
  }
  blocos.push({ type: 'text', text: 'Analise a forma conforme as instruções.' })

  const response = await getClient().messages.create({
    model: MODEL,
    max_tokens: 800,
    system: buildSystemPrompt(exerciseName, nivelAluno),
    messages: [{ role: 'user', content: blocos }],
  })

  const bloco = response.content[0]
  const texto = bloco?.type === 'text' ? bloco.text : ''
  const jsonMatch = texto.match(/\{[\s\S]*\}/)
  if (!jsonMatch) throw new Error('Resposta da IA não trouxe JSON válido')

  const parsed = JSON.parse(jsonMatch[0]) as {
    amplitude?: string
    posture?: string
    tempo?: string
    compensations?: string
    safety_notes?: string
    three_key_feedback?: FormFeedbackItem[]
  }

  return {
    amplitude_assessment: parsed.amplitude ?? '',
    posture_assessment: parsed.posture ?? '',
    tempo_assessment: parsed.tempo ?? '',
    compensations: parsed.compensations ?? 'Nenhuma',
    safety_notes: parsed.safety_notes ?? 'Seguro',
    three_key_feedback: parsed.three_key_feedback ?? [],
  }
}
