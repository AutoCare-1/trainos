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
  // Chegaram com a leva de mobilidade/alongamento/equilíbrio: sem eles, 143
  // exercícios novos animariam como a figura genérica balançando no lugar.
  | 'stretch'
  | 'balance'
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

// A ORDEM IMPORTA: a primeira regra que casar vence. Regras mais específicas
// vêm antes das genéricas, porque muito nome de exercício contém a palavra de
// outro ("Panturrilha no hack machine" tem "hack"; "Prancha com remada" tem
// "remada"; "Abdução de quadril" tem "quadril"). Ao mexer aqui, rode a
// checagem de padrões dos exercícios da biblioteca antes de commitar.
const KEYWORD_RULES: [RegExp, MovementPattern][] = [
  // --- Alongamento, mobilidade e equilíbrio vêm ANTES de tudo ---
  // Estes nomes carregam a palavra do exercício de musculação que eles
  // alongam ou estabilizam: "Alongamento de tríceps acima da cabeça" cairia
  // em tricepsExtension, "Prancha com apoio no bosu" em plank, e
  // "Propriocepção de joelho em semiagachamento" em squat.
  [/alongamento|postura d[ao] |sleeper stretch|liberacao (miofascial|de )/, 'stretch'],
  [/mobilidade|mobilizacao|gato e camelo|escorpiao|open book|90\/90|balanco de perna|respiracao diafragmatica/, 'stretch'],
  [/apoio unipodal|apoio unico|unipodal|equilibrio|tandem|bosu|cegonha|propriocep|transferencia de peso/, 'balance'],

  // --- Precisam vir antes das regras amplas de perna/quadril/costas ---
  // "no hack machine", "no leg press" apareceriam como agachamento.
  [/panturrilha|gemeos|calf|tibial|soleu|aquiles|elevacao de calcanhar|arco plantar/, 'calfRaise'],
  // "abducao/aducao de quadril" bateria na regra de 'quadril' (hipThrust).
  [/abdutora|adutora|abducao|aducao|clamshell|fire hydrant|caminhada lateral|monstro/, 'hipAbduction'],
  // "Prancha lateral com elevação de quadril" e "Prancha com remada".
  [/prancha|ab wheel|roda abdominal|hollow|dead bug|bird dog|pallof|rollout|bear crawl|crab walk|inchworm/, 'plank'],
  // "Encolhimento na barra fixa" bateria na regra de puxada.
  [/encolhimento|shrug|escapula/, 'shrug'],
  // "Flexão" é ambíguo em português (flexão de braço x flexão de joelho x
  // flexão lateral de tronco) — os sentidos não-peitoral saem primeiro.
  [/flexao lateral/, 'twist'],
  [/flexora|leg curl|nordica|glute ham|flexao de joelho|flexao de calcanhar|isquiotibiais/, 'legCurl'],
  [/extensora|extensao de joelho|extensao de perna/, 'legExtension'],

  // --- Membros inferiores ---
  [/agachamento|leg press|hack|wall sit|sentar e levantar|pistol|sissy/, 'squat'],
  [/afundo|passada|bulgaro|lunge|avanco|step-?up|subida no step/, 'lunge'],
  [/terra|stiff|good morning|bom dia|romeno|swing|clean|snatch|arranco/, 'hinge'],
  // Sem "coice" solto: "Tríceps coice" cairia aqui. Os coices de glúteo já
  // casam por "gluteo".
  [/quadril|hip thrust|gluteo|ponte de|frog pump|reverse hyper/, 'hipThrust'],

  // --- Tronco ---
  [/crucifixo|voador|peck deck|crossover|pullover|fly/, 'flye'],
  [/supino|flexao|press de peito|chest press|floor press|svend|thruster/, 'horizontalPress'],
  // "Barra australiana" fica de fora: é remada invertida (puxada horizontal),
  // e o próprio nome já casa na regra de remada logo abaixo.
  [/puxada|puxador|barra fixa|pull-?up|chin-?up|pulldown|toes to bar/, 'verticalPull'],
  [/remada|face pull|row|renegade/, 'horizontalRow'],
  [/desenvolvimento|overhead press|press militar|push press|bradford|halo/, 'overheadPress'],
  [/elevacao lateral|remada alta|elevacao em [tw]\b/, 'lateralRaise'],
  [/elevacao frontal|elevacao em y/, 'frontRaise'],
  [/rotacao externa|rotacao interna|cubano/, 'lateralRaise'],

  // --- Braços ---
  [/rosca de punho/, 'curl'],
  [/rosca|curl/, 'curl'],
  [/triceps|mergulho|dips|jm press|tate press|skull/, 'tricepsExtension'],

  // --- Core ---
  [/obliquo|russa|twist|rotacao|woodchop|lenhador/, 'twist'],
  [/abdominal|crunch|elevacao de pernas|elevacao de joelhos|sit-?up|canivete|v-?up|remador|borboleta|estrela/, 'crunch'],
  [/hiperextensao|extensao lombar|superman/, 'hinge'],

  // --- Condicionamento ---
  [/burpee|corda|polichinelo|mountain climber|esteira|jumping|salto|pular|sprint|corrida|bicicleta|eliptico|assault|escada|shadow|treno|prowler|wall ball|arremesso|passe de|agilidade|ziguezague|skater|deslocamento|escalador|figure eight|remo ergometro/, 'cardio'],

  // Carregamentos e pegada: a figura em pé sustentando carga é o que mais se
  // parece com o movimento real — melhor que forçar um padrão de outro grupo.
  [/farmer walk|caminhada do fazendeiro|caminhada do garcom|carregamento|rack carry|suspensao|dead hang|pinca|preensao|hand grip|enrolamento|turkish|get-?up/, 'generic'],
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
  // Grupos da leva complementar.
  esportivo: 'cardio',
  mobilidade: 'stretch',
  alongamento: 'stretch',
  equilibrio: 'balance',
  // Ativação e Prevenção são heterogêneos demais pra um padrão só (tem
  // escápula, tornozelo, cervical e core no mesmo grupo): quem não casar em
  // nenhuma regra por nome fica melhor na figura genérica do que forçado.
  ativacao: 'generic',
  prevencao: 'generic',
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
