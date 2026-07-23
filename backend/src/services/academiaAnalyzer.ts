import Anthropic from '@anthropic-ai/sdk'
import fs from 'fs/promises'
import path from 'path'
import { MachinesJson } from '../types'

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

const SYSTEM_PROMPT = `Você é um especialista em avaliação de academias e prescrição de exercícios pra um
app de personal trainer cujo público é majoritariamente idosos (Clube Mais).

Analise TODAS as imagens recebidas (podem ser fotos separadas ou frames extraídos de um vídeo
da mesma academia) e identifique todas as máquinas, equipamentos e superfícies de treino
visíveis, consolidando o que aparece repetido em mais de uma imagem em uma única entrada.

Regras:
- Seja específico ("Leg press 45°" em vez de "máquina de perna").
- Não invente máquinas que não estejam claramente visíveis.
- Para cada máquina, avalie se é segura e adequada pra um público mais velho (evite marcar
  como recomendável equipamentos de alto impacto ou alta complexidade técnica).
- Agrupe as zonas identificadas (ex: "Musculação", "Cardio", "Piso livre").
- Aponte lacunas relevantes de cobertura muscular.

Responda APENAS com um JSON válido, sem texto antes ou depois, neste formato exato:
{
  "machines": [
    {
      "name": "Leg Press 45°",
      "category": "lower_body",
      "primary_muscles": ["quadríceps", "glúteos"],
      "secondary_muscles": ["panturrilha"],
      "confidence": 0.9,
      "notes": "máquina com seletor de pino, segura pra idosos"
    }
  ],
  "zones_identified": ["Musculação", "Cardio"],
  "coverage_estimate": "boa",
  "gaps": ["Pouca opção de cardio de baixo impacto"],
  "notes": "observação geral sobre a academia"
}`

export interface AcademiaAnalysisResult {
  machines: MachinesJson
  zones_identified: string[]
  coverage_estimate: string | null
  gaps: string[]
  notes: string | null
}

/** Analisa um conjunto de imagens da mesma submissão (fotos ou frames de vídeo) num único chamado. */
export async function analisarMidiaAcademia(caminhosImagens: string[]): Promise<AcademiaAnalysisResult> {
  const blocos: Anthropic.Messages.ContentBlockParam[] = []
  for (const caminho of caminhosImagens) {
    const { data, mediaType } = await lerImagemBase64(caminho)
    blocos.push({ type: 'image', source: { type: 'base64', media_type: mediaType, data } })
  }
  blocos.push({ type: 'text', text: `Total de imagens: ${caminhosImagens.length}. Analise conforme as instruções.` })

  const response = await getClient().messages.create({
    model: MODEL,
    max_tokens: 2000,
    system: SYSTEM_PROMPT,
    messages: [{ role: 'user', content: blocos }],
  })

  const bloco = response.content[0]
  const texto = bloco?.type === 'text' ? bloco.text : ''
  const jsonMatch = texto.match(/\{[\s\S]*\}/)
  if (!jsonMatch) throw new Error('Resposta da IA não trouxe JSON válido')

  const parsed = JSON.parse(jsonMatch[0]) as {
    machines: MachinesJson['machines']
    zones_identified?: string[]
    coverage_estimate?: string
    gaps?: string[]
    notes?: string
  }

  return {
    machines: { machines: parsed.machines ?? [] },
    zones_identified: parsed.zones_identified ?? [],
    coverage_estimate: parsed.coverage_estimate ?? null,
    gaps: parsed.gaps ?? [],
    notes: parsed.notes ?? null,
  }
}
