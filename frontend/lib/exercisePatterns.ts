// Mapeia cada exercício para um "padrão de movimento" — usado para escolher
// qual animação (ExerciseAnimation) exibir. Vários exercícios parecidos
// reaproveitam o mesmo padrão (ex: todas as roscas usam 'curl').

export type MovementPattern =
  | 'squat'
  | 'lunge'
  | 'hinge'
  | 'legExtension'
  | 'legCurl'
  | 'calfRaise'
  | 'hipThrust'
  | 'hipAbduction'
  | 'horizontalPress'
  | 'flye'
  | 'verticalPull'
  | 'horizontalRow'
  | 'overheadPress'
  | 'lateralRaise'
  | 'frontRaise'
  | 'curl'
  | 'tricepsExtension'
  | 'shrug'
  | 'plank'
  | 'crunch'
  | 'twist'
  | 'cardio'
  | 'generic'

function normalize(s: string): string {
  return s
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .trim()
}

const EXACT_MAP: Record<string, MovementPattern> = {
  // Peito
  'supino reto': 'horizontalPress',
  'supino inclinado': 'horizontalPress',
  'supino declinado': 'horizontalPress',
  'supino com halteres': 'horizontalPress',
  'supino maquina': 'horizontalPress',
  'crucifixo reto': 'flye',
  'crucifixo inclinado': 'flye',
  'peck deck (voador)': 'flye',
  crossover: 'flye',
  'flexao de braco': 'horizontalPress',
  'paralelas (mergulho)': 'tricepsExtension',
  pullover: 'flye',

  // Costas
  'puxada frontal': 'verticalPull',
  'puxada por tras': 'verticalPull',
  'puxador triangulo': 'verticalPull',
  'barra fixa (pull-up)': 'verticalPull',
  'remada curvada': 'horizontalRow',
  'remada baixa': 'horizontalRow',
  'remada cavalinho': 'horizontalRow',
  'remada unilateral com halter': 'horizontalRow',
  'remada maquina': 'horizontalRow',
  'face pull': 'horizontalRow',

  // Ombros
  'desenvolvimento militar': 'overheadPress',
  'desenvolvimento com halteres': 'overheadPress',
  'desenvolvimento arnold': 'overheadPress',
  'elevacao lateral': 'lateralRaise',
  'elevacao frontal': 'frontRaise',
  'crucifixo invertido': 'flye',
  'remada alta': 'lateralRaise',

  // Bíceps
  'rosca direta': 'curl',
  'rosca alternada': 'curl',
  'rosca martelo': 'curl',
  'rosca scott': 'curl',
  'rosca concentrada': 'curl',
  'rosca 21': 'curl',
  'rosca inversa': 'curl',

  // Tríceps
  'triceps corda': 'tricepsExtension',
  'triceps testa': 'tricepsExtension',
  'triceps frances': 'tricepsExtension',
  'triceps coice': 'tricepsExtension',
  'triceps pulley barra reta': 'tricepsExtension',
  'mergulho no banco': 'tricepsExtension',
  'extensao de triceps unilateral': 'tricepsExtension',

  // Pernas — quadríceps
  'agachamento livre': 'squat',
  'leg press 45°': 'squat',
  'cadeira extensora': 'legExtension',
  'agachamento sumo': 'squat',
  'agachamento bulgaro': 'lunge',
  afundo: 'lunge',
  'passada (walking lunge)': 'lunge',
  'hack machine': 'squat',
  'agachamento no smith': 'squat',
  'agachamento livre com halteres (goblet squat)': 'squat',

  // Posterior de coxa
  'levantamento terra': 'hinge',
  'mesa flexora': 'legCurl',
  stiff: 'hinge',
  'cadeira flexora': 'legCurl',
  'levantamento terra romeno': 'hinge',
  'good morning': 'hinge',

  // Glúteos
  'elevacao de quadril (hip thrust)': 'hipThrust',
  'gluteo no cabo (coice)': 'hipThrust',
  'gluteo quatro apoios': 'hipThrust',
  'abducao de quadril na maquina': 'hipAbduction',
  'cadeira abdutora': 'hipAbduction',
  'cadeira adutora': 'hipAbduction',

  // Panturrilha
  'panturrilha em pe': 'calfRaise',
  'panturrilha sentado': 'calfRaise',
  'panturrilha no leg press': 'calfRaise',

  // Core / abdômen
  'prancha abdominal': 'plank',
  'prancha lateral': 'plank',
  'abdominal supra (crunch)': 'crunch',
  'abdominal infra (elevacao de pernas)': 'crunch',
  'abdominal na maquina': 'crunch',
  'abdominal obliquo': 'twist',
  'rotacao russa (russian twist)': 'twist',
  'elevacao de pernas na barra': 'crunch',
  'roda abdominal (ab wheel)': 'plank',

  // Trapézio
  'encolhimento de ombros com barra': 'shrug',
  'encolhimento com halteres': 'shrug',

  // Antebraço
  'rosca de punho': 'curl',
  'rosca de punho invertida': 'curl',

  // Funcional / cardio
  burpee: 'cardio',
  'corda naval': 'cardio',
  'mountain climber': 'cardio',
  polichinelo: 'cardio',
  'pular corda': 'cardio',
  'corrida na esteira': 'cardio',
}

const KEYWORD_RULES: [RegExp, MovementPattern][] = [
  [/agachamento|leg press|hack/, 'squat'],
  [/afundo|passada|bulgaro|lunge/, 'lunge'],
  [/terra|stiff|good morning/, 'hinge'],
  [/extensora|extensao de perna/, 'legExtension'],
  [/flexora|leg curl/, 'legCurl'],
  [/panturrilha|gemeos|calf/, 'calfRaise'],
  [/quadril|hip thrust|gluteo/, 'hipThrust'],
  [/abdutora|adutora|abducao/, 'hipAbduction'],
  [/crucifixo|voador|peck deck|crossover|pullover|fly/, 'flye'],
  [/supino|flexao de braco|press de peito|chest press/, 'horizontalPress'],
  [/puxada|puxador|barra fixa|pull-up|pulldown/, 'verticalPull'],
  [/remada|face pull|row/, 'horizontalRow'],
  [/desenvolvimento|overhead press|press militar/, 'overheadPress'],
  [/elevacao lateral|remada alta/, 'lateralRaise'],
  [/elevacao frontal/, 'frontRaise'],
  [/rosca de punho/, 'curl'],
  [/rosca/, 'curl'],
  [/triceps|mergulho/, 'tricepsExtension'],
  [/encolhimento|shrug/, 'shrug'],
  [/prancha|ab wheel|roda abdominal/, 'plank'],
  [/obliquo|russa|twist|rotacao/, 'twist'],
  [/abdominal|crunch|elevacao de pernas/, 'crunch'],
  [/burpee|corda|polichinelo|mountain climber|esteira|jumping/, 'cardio'],
]

const MUSCLE_GROUP_FALLBACK: Record<string, MovementPattern> = {
  peito: 'horizontalPress',
  costas: 'horizontalRow',
  ombros: 'lateralRaise',
  biceps: 'curl',
  triceps: 'tricepsExtension',
  pernas: 'squat',
  posterior: 'hinge',
  gluteos: 'hipThrust',
  panturrilha: 'calfRaise',
  core: 'plank',
  trapezio: 'shrug',
  antebraco: 'curl',
  funcional: 'cardio',
}

export function getMovementPattern(name: string, muscleGroup?: string): MovementPattern {
  const key = normalize(name)
  if (EXACT_MAP[key]) return EXACT_MAP[key]

  for (const [regex, pattern] of KEYWORD_RULES) {
    if (regex.test(key)) return pattern
  }

  if (muscleGroup) {
    const groupKey = normalize(muscleGroup)
    if (MUSCLE_GROUP_FALLBACK[groupKey]) return MUSCLE_GROUP_FALLBACK[groupKey]
  }

  return 'generic'
}
