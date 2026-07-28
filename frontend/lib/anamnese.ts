import { Anamnese } from '@/lib/types'

export const ANAMNESE_VAZIA: Anamnese = {
  historico_atividade_fisica: {
    ja_praticou: '',
    pratica_atualmente: '',
    modalidades_favoritas: '',
    modalidades_nao_gosta: '',
    treinou_com_personal: null,
  },
  objetivos: {
    selecionados: [],
    outro: '',
    prazo: '',
  },
  condicoes_saude: {
    restricao_medica: '',
    doenca_diagnosticada: '',
    lesao: '',
    medicamentos: '',
    suplementos: '',
    alergias: '',
  },
  estilo_de_vida: {
    profissao: '',
    nivel_estresse: null,
    qualidade_sono: null,
    horas_sono: '',
    alimentacao: '',
    plano_alimentar: '',
    frequencia_alcool: '',
    fumante: null,
    tempo_fumante: '',
  },
  motivacao: {
    motivacao: '',
    obstaculos: '',
    preferencia_intensidade: null,
    preferencia_companhia: null,
    horario_disponivel: '',
  },
  disponibilidade: {
    vezes_por_semana: '',
    tempo_por_treino: '',
    local_treino: [],
  },
  historico_familiar: '',
}

export const OBJETIVOS_OPCOES: { valor: string; label: string }[] = [
  { valor: 'emagrecimento', label: 'Emagrecimento' },
  { valor: 'ganho_massa', label: 'Ganho de massa muscular' },
  { valor: 'condicionamento', label: 'Condicionamento físico' },
  { valor: 'saude_geral', label: 'Saúde geral' },
  { valor: 'reducao_estresse', label: 'Redução de estresse' },
  { valor: 'performance_esportiva', label: 'Performance esportiva' },
]

export const LOCAL_TREINO_OPCOES: { valor: string; label: string }[] = [
  { valor: 'academia', label: 'Academia' },
  { valor: 'casa', label: 'Casa' },
  { valor: 'condominio', label: 'Condomínio' },
  { valor: 'ar_livre', label: 'Ao ar livre' },
]

/** Some deep merge simples: garante que anamnese vinda do backend (pode ter seções
 * ausentes, ex: registro criado antes desse campo existir) sempre tenha todas as
 * chaves esperadas, sem quebrar o formulário controlado. */
export function normalizarAnamnese(valor: Partial<Anamnese> | null | undefined): Anamnese {
  return {
    historico_atividade_fisica: { ...ANAMNESE_VAZIA.historico_atividade_fisica, ...valor?.historico_atividade_fisica },
    objetivos: { ...ANAMNESE_VAZIA.objetivos, ...valor?.objetivos },
    condicoes_saude: { ...ANAMNESE_VAZIA.condicoes_saude, ...valor?.condicoes_saude },
    estilo_de_vida: { ...ANAMNESE_VAZIA.estilo_de_vida, ...valor?.estilo_de_vida },
    motivacao: { ...ANAMNESE_VAZIA.motivacao, ...valor?.motivacao },
    disponibilidade: { ...ANAMNESE_VAZIA.disponibilidade, ...valor?.disponibilidade },
    historico_familiar: valor?.historico_familiar ?? '',
  }
}

/** Verifica todas as seções de uma vez (em vez de uma lista manual campo a campo,
 * que já ficou desatualizada uma vez e escondeu dado real de um aluno que só
 * respondeu campos "de fora" da lista) — usado pra decidir se a seção "Anamnese
 * completa" deve aparecer na ficha do aluno. */
export function anamneseTemConteudo(anamnese: Anamnese): boolean {
  return Object.values(anamnese).some((secao) => {
    if (typeof secao === 'string') return secao !== ''
    if (Array.isArray(secao)) return secao.length > 0
    return Object.values(secao).some((v) => (Array.isArray(v) ? v.length > 0 : v !== '' && v !== null))
  })
}
