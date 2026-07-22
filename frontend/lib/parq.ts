import { ParQAnswers } from '@/lib/types'

export const PAR_Q_PERGUNTAS: { chave: keyof ParQAnswers; texto: string }[] = [
  { chave: 'cardiaco', texto: 'Problema cardíaco ou dor no peito provocada por exercício?' },
  { chave: 'tontura', texto: 'Já perdeu a consciência ou sofreu queda por tontura?' },
  { chave: 'articular', texto: 'Problema ósseo ou articular que pode agravar com exercício?' },
  { chave: 'pressao_medicacao', texto: 'Usa medicação para pressão ou coração?' },
]

export const PAR_Q_VAZIO: ParQAnswers = {
  cardiaco: false,
  tontura: false,
  articular: false,
  pressao_medicacao: false,
}
