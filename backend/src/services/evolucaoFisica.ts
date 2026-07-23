import Anthropic from '@anthropic-ai/sdk'
import fs from 'fs'
import path from 'path'

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

type MediaType = 'image/jpeg' | 'image/png' | 'image/gif' | 'image/webp'

const MEDIA_TYPE_POR_EXTENSAO: Record<string, MediaType> = {
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.png': 'image/png',
  '.webp': 'image/webp',
  '.gif': 'image/gif',
}

function lerImagemBase64(caminhoAbsoluto: string): { data: string; mediaType: MediaType } {
  const buffer = fs.readFileSync(caminhoAbsoluto)
  const mediaType = MEDIA_TYPE_POR_EXTENSAO[path.extname(caminhoAbsoluto).toLowerCase()] ?? 'image/jpeg'
  return { data: buffer.toString('base64'), mediaType }
}

const SYSTEM_PRIMEIRA_FOTO = `Você é a Coach IA de um app de personal trainer, escrevendo pro aluno logo após
ele registrar a primeira foto na aba de Evolução física dele.

Essa é a primeira foto — NÃO existe nenhuma anterior pra comparar. Por isso, NÃO comente
absolutamente nada sobre o corpo, peso, forma física, postura ou aparência dessa foto.

Sua mensagem deve:
- Dar boas-vindas ao registro e marcar esse momento como o "ponto de partida" da evolução dele.
- Deixar claro que não existe frequência obrigatória pra tirar essas fotos — o aluno tira
  quando sentir que faz sentido, no ritmo dele, sem pressão.
- Ter tom leve, motivador e pessoal (nada de genérico ou corporativo).
- Ser curta: 2 a 4 frases.
- NÃO usar emojis nem outros pictogramas — só texto.

Responda SEMPRE apenas com a mensagem para o aluno, em português do Brasil, sem prefixos
como "Coach:" nem aspas.`

const SYSTEM_COMPARACAO = `Você é a Coach IA de um app de personal trainer, escrevendo pro aluno logo após
ele registrar uma nova foto na aba de Evolução física, comparando com a foto anterior dele.

Você vai receber duas imagens nessa ordem: a primeira é a foto MAIS ANTIGA (referência
anterior) e a segunda é a foto MAIS NOVA (a que ele acabou de registrar agora).

Sua mensagem deve:
- Comparar as duas fotos com atenção genuína, comentando o que for relevante e realmente
  visível (ex: postura, definição, consistência aparente, disposição) — só comente o que
  perceber de verdade, sem inventar nem exagerar.
- Se houver evolução visível, parabenize com entusiasmo genuíno, sem exagero.
- Se não der pra notar diferença clara entre as fotos, incentive a constância sem soar
  decepcionado — o processo importa mais que uma única comparação.
- Tom motivador, respeitoso e pessoal.
- NUNCA faça qualquer julgamento negativo, mesmo sutil, sobre o corpo, peso ou aparência
  do aluno. Você não é médico nem nutricionista — não dê conselhos sobre dieta, saúde ou
  composição corporal, fale só do que é observável e motivador.
- Ser curta: 2 a 4 frases.
- NÃO usar emojis nem outros pictogramas — só texto.

Responda SEMPRE apenas com a mensagem para o aluno, em português do Brasil, sem prefixos
como "Coach:" nem aspas.`

/** Primeira foto do aluno: sem comparação, só acolhimento e incentivo ao check-in livre. */
export async function comentarPrimeiraFoto(nomeAluno: string, caminhoFotoAbsoluto: string): Promise<string> {
  const primeiroNome = nomeAluno.split(' ')[0]
  const foto = lerImagemBase64(caminhoFotoAbsoluto)

  const messages: Anthropic.Messages.MessageParam[] = [
    {
      role: 'user',
      content: [
        { type: 'text', text: `Nome do aluno: ${primeiroNome}. Esta é a primeira foto registrada por ele.` },
        { type: 'image', source: { type: 'base64', media_type: foto.mediaType, data: foto.data } },
      ],
    },
  ]

  const response = await getClient().messages.create({
    model: MODEL,
    max_tokens: 300,
    system: SYSTEM_PRIMEIRA_FOTO,
    messages,
  })

  const bloco = response.content[0]
  return bloco?.type === 'text'
    ? bloco.text.trim()
    : 'Primeira foto registrada! Esse é o seu ponto de partida — daqui pra frente dá pra acompanhar sua evolução de verdade.'
}

/** Fotos seguintes: compara com a anterior e comenta a evolução. */
export async function compararEvolucaoFisica(
  nomeAluno: string,
  caminhoFotoAnteriorAbsoluto: string,
  caminhoFotoNovaAbsoluto: string
): Promise<string> {
  const primeiroNome = nomeAluno.split(' ')[0]
  const anterior = lerImagemBase64(caminhoFotoAnteriorAbsoluto)
  const nova = lerImagemBase64(caminhoFotoNovaAbsoluto)

  const messages: Anthropic.Messages.MessageParam[] = [
    {
      role: 'user',
      content: [
        { type: 'text', text: `Nome do aluno: ${primeiroNome}. Foto mais antiga (referência anterior):` },
        { type: 'image', source: { type: 'base64', media_type: anterior.mediaType, data: anterior.data } },
        { type: 'text', text: 'Foto mais nova (registrada agora):' },
        { type: 'image', source: { type: 'base64', media_type: nova.mediaType, data: nova.data } },
      ],
    },
  ]

  const response = await getClient().messages.create({
    model: MODEL,
    max_tokens: 300,
    system: SYSTEM_COMPARACAO,
    messages,
  })

  const bloco = response.content[0]
  return bloco?.type === 'text' ? bloco.text.trim() : 'Foto registrada! Continue assim.'
}
