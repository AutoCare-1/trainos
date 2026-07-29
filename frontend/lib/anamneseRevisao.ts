import { RespostasRevisao } from '@/lib/types'

export const RESPOSTAS_REVISAO_VAZIA: RespostasRevisao = {
  avaliacao_treino: '',
  gostou_mais: '',
  nao_gostou: '',
  percebeu_evolucao: '',
  aspectos_progresso: [],
  aspectos_progresso_outro: '',
  manteve_frequencia: '',
  treinos_por_semana: '',
  dificuldade_rotina: '',
  sugestao_melhoria: '',
  sugestao_modalidade: '',
  sugestao_geral: '',
}

export const ASPECTOS_PROGRESSO_OPCOES: { valor: string; label: string }[] = [
  { valor: 'forca', label: 'Força' },
  { valor: 'resistencia', label: 'Resistência' },
  { valor: 'flexibilidade', label: 'Flexibilidade' },
  { valor: 'estetica', label: 'Estética' },
  { valor: 'disposicao', label: 'Disposição/energia' },
]
