export interface Professional {
  id: string
  name: string
  email: string
  /** Acesso ao CRM do produto. Só controla a exibição do link no menu — a trava
   *  de verdade é o middleware AdminOnly no backend. */
  is_admin?: boolean
}

export interface ParQAnswers {
  cardiaco: boolean
  tontura: boolean
  articular: boolean
  pressao_medicacao: boolean
}

/** Respostas da anamnese de revisão — disparada quando um treino com prazo vence
 * (ver PortalController::show, revisaoPendente). Um registro por treino vencido. */
export interface RespostasRevisao {
  avaliacao_treino: 'excelente' | 'boa' | 'regular' | 'ruim' | ''
  gostou_mais: string
  nao_gostou: string
  percebeu_evolucao: 'sim_bastante' | 'sim_poderia_mais' | 'pouco' | 'ainda_nao' | ''
  aspectos_progresso: string[]
  aspectos_progresso_outro: string
  manteve_frequencia: 'sim' | 'parcialmente' | 'nao' | ''
  treinos_por_semana: string
  dificuldade_rotina: string
  sugestao_melhoria: string
  sugestao_modalidade: string
  sugestao_geral: string
}

export interface WorkoutReview {
  id: string
  student_id: string
  workout_id: string
  workout_name: string
  tempo_acompanhamento_semanas: number | null
  respostas: RespostasRevisao
  created_at: string
}

export interface RevisaoPendente {
  workout_id: string
  workout_name: string
}

/** Complemento da anamnese inicial (par_q_answers/health_notes já cobrem a parte de
 * segurança PAR-Q) — perguntas do formulário em papel do personal que ainda não
 * tinham campo no app. Tudo opcional: cada seção pode ficar em branco. */
export interface Anamnese {
  historico_atividade_fisica: {
    ja_praticou: string
    pratica_atualmente: string
    modalidades_favoritas: string
    modalidades_nao_gosta: string
    treinou_com_personal: boolean | null
  }
  objetivos: {
    selecionados: string[]
    outro: string
    prazo: string
  }
  condicoes_saude: {
    restricao_medica: string
    doenca_diagnosticada: string
    lesao: string
    medicamentos: string
    suplementos: string
    alergias: string
  }
  estilo_de_vida: {
    profissao: string
    nivel_estresse: 'baixo' | 'medio' | 'alto' | null
    qualidade_sono: 'boa' | 'regular' | 'ruim' | null
    horas_sono: string
    alimentacao: string
    plano_alimentar: string
    frequencia_alcool: string
    fumante: boolean | null
    tempo_fumante: string
  }
  motivacao: {
    motivacao: string
    obstaculos: string
    preferencia_intensidade: 'curtos_intensos' | 'longos_moderados' | null
    preferencia_companhia: 'sozinho' | 'grupo' | 'acompanhamento' | null
    horario_disponivel: string
  }
  disponibilidade: {
    vezes_por_semana: string
    tempo_por_treino: string
    local_treino: string[]
  }
  historico_familiar: string
}

export type TipoCobranca = 'consultoria' | 'presencial'

export interface Student {
  id: string
  name: string
  email: string | null
  phone: string | null
  objective: string | null
  birth_date: string | null
  weight_kg: number | null
  height_cm: number | null
  invite_token: string
  status: 'active' | 'inactive'
  ai_autopilot: boolean
  lembrar_pagamento_vencimento: boolean
  par_q_answers: ParQAnswers | null
  health_notes: string | null
  anamnese: Anamnese | null
  onboarding_completed_at: string | null
  photo_url: string | null
  created_at: string
  ultimo_treino?: string | null
  sessoes_concluidas?: number
  ultima_sessao_em?: string | null
  tem_treino_enviado?: boolean
  exercicios_sem_progresso?: number
}

/** Plano de cobrança vigente de um aluno — nunca editado in-place, só fechado/recriado (ver GET /alunos/:id). */
export interface StudentBillingPlan {
  id: string
  student_id: string
  professional_id: string
  billing_type: TipoCobranca
  monthly_value: string
  starts_on: string
  ends_on: string | null
}

export interface Expense {
  id: string
  professional_id: string
  description: string
  amount: string
  is_recurring: boolean
  starts_on: string
  ends_on: string | null
  previous_expense_id: string | null
}

export interface BodyPhoto {
  id: string
  student_id: string
  taken_at: string
  ai_feedback: string | null
  compared_to_photo_id: string | null
  created_at: string
}

/** Avaliação postural — 3 fotos (frente/lado/costas) num único registro, opcional
 * e separada da Evolução física (1 foto). Ver PosturalAssessment no backend. */
export interface PosturalAssessment {
  id: string
  student_id: string
  taken_at: string
  ai_feedback: string | null
  compared_to_assessment_id: string | null
  created_at: string
}

export interface DiaSemanaCheckin {
  date: string
  label: string
  checked: boolean
  comment: string | null
}

export interface ResumoSemanaCheckins {
  inicio: string
  fim: string
  dias_com_checkin: number
  total_dias: number
  grid: DiaSemanaCheckin[]
}

export interface ResumoMesCheckins {
  ano: number
  mes: number
  dias_com_checkin: number
  total_dias_mes: number
  dias_marcados: number[]
}

export interface ResumoAnoCheckins {
  ano: number
  dias_com_checkin: number
}

export interface ResumoCheckins {
  semana: ResumoSemanaCheckins
  mes: ResumoMesCheckins
  ano: ResumoAnoCheckins
  checkinHoje?: boolean
}

export interface FotoCheckin {
  id: string
  checkin_date: string
  comment: string | null
}

export interface HistoricoCheckins {
  period: 'week' | 'month' | 'year'
  semana?: ResumoSemanaCheckins
  mes?: ResumoMesCheckins
  ano?: ResumoAnoCheckins
  fotos: FotoCheckin[]
}

export interface AlertaEstagnacao {
  exercise_id: string
  exercise_name: string
  ultima: string
  anterior: string
}

export interface BodyMeasurement {
  id: string
  student_id: string
  recorded_at: string
  weight_kg: number | null
  waist_cm: number | null
  hip_cm: number | null
  body_fat_pct: number | null
  notes: string | null
  created_at: string
}

export interface ExternalActivity {
  id: string
  provider: 'strava'
  activity_type: string
  name: string | null
  started_at: string
  duration_seconds: number | null
  distance_meters: number | null
  calories: number | null
  avg_heart_rate: number | null
}

export interface WorkoutTemplate {
  id: string
  name: string
  created_at: string
  total_exercicios?: number
}

export interface WorkoutTemplateExerciseDetail {
  id: string
  exercise_id: string
  order_index: number
  sets: number
  reps: string
  load_kg: string | null
  rest_seconds: number | null
  notes: string | null
  structure_type: string
  group_label: string | null
  exercise_name: string
  muscle_group: string
  image_url: string | null
  image_credit: string | null
}

export interface Message {
  id: string
  student_id: string
  professional_id: string
  sender: 'student' | 'professional' | 'ai'
  content: string
  created_at: string
}

export interface Exercise {
  id: string
  name: string
  muscle_group: string
  equipment: string | null
  instructions: string | null
  video_url: string | null
  image_url: string | null
  image_credit: string | null
  video_customizado?: boolean
}

export interface Workout {
  id: string
  student_id: string
  name: string
  status: 'draft' | 'sent'
  sent_at: string | null
  duration_weeks: number | null
  expires_at: string | null
  archived_at: string | null
  created_at: string
}

/** Versão enxuta devolvida em /portal/:token — só o necessário pro aluno escolher
 * entre os treinos vigentes (não-arquivados). */
export interface WorkoutResumo {
  id: string
  name: string
  sent_at: string
  expires_at: string | null
}

export interface WorkoutExerciseDetail {
  id: string
  exercise_id: string
  order_index: number
  sets: number
  reps: string
  load_kg: string | null
  rest_seconds: number | null
  notes: string | null
  structure_type: string
  group_label: string | null
  exercise_name: string
  muscle_group: string
  instructions: string | null
  video_url: string | null
  image_url: string | null
  image_credit: string | null
}

/**
 * Sugestão de progressão calculada em App\Support\Progressao (determinística,
 * sem IA). O personal decide se aceita, ignora ou ajusta — nada é aplicado
 * automaticamente.
 */
export interface SugestaoProgressao {
  exercise_id: string
  exercise_name: string
  equipment: string | null
  ultima_sessao_em: string
  reps_prescritas: string
  series_registradas: number
  series_prescritas: number
  carga_anterior: number | null
  carga_sugerida: number | null
  delta_kg: number
  reps_sugeridas: string | null
  acao: 'aumentar_carga' | 'aumentar_reps' | 'manter' | 'reduzir'
  motivo: string
  estagnado: boolean
}

export interface Badge {
  id: string
  emoji: string
  label: string
}

export interface Gamificacao {
  total_sessoes: number
  streak: number
  badges: Badge[]
}

export interface LeaderboardEntry {
  student_id: string
  name: string
  photo_url: string | null
  pontos: string
}

export type GymSubmissionType = 'photo' | 'video' | 'album'
export type GymSubmissionStatus = 'analyzing' | 'completed' | 'failed'
export type GymApprovalStatus = 'pending' | 'approved' | 'rejected'

export interface GymMediaSubmission {
  id: string
  student_id: string
  professional_id: string
  submission_type: GymSubmissionType
  days_per_week: number | null
  status: GymSubmissionStatus
  error_message: string | null
  created_at: string
  approval_status?: GymApprovalStatus | null
  recommendation_name?: string | null
  recommendation_id?: string
  student_name?: string
  student_photo_url?: string | null
}

export interface GymMediaAsset {
  id: string
  submission_id: string
  asset_type: 'photo' | 'video_frame'
  file_path: string
  frame_index: number | null
  created_at: string
}

export interface MachineDetectado {
  name: string
  category: string
  primary_muscles: string[]
  secondary_muscles: string[]
  confidence: number
  notes: string
}

export interface GymAnalysisResult {
  id: string
  submission_id: string
  machines_json: { machines: MachineDetectado[] }
  zones_identified: string[]
  total_unique_machines: number
  coverage_estimate: string | null
  gaps: string[]
  notes: string | null
  created_at: string
}

export interface RecommendedItem {
  exercise_id: string
  exercise_name: string
  sets: number
  reps: string
  rest_seconds: number
  notes: string
  muscle_group?: string
  image_url?: string | null
}

export interface GymWorkoutRecommendation {
  id: string
  submission_id: string
  analysis_result_id: string
  name: string
  split_type: string | null
  reasoning: string | null
  recommended_items: RecommendedItem[]
  approval_status: GymApprovalStatus
  approved_workout_id: string | null
  professional_notes: string | null
  approved_at: string | null
  created_at: string
}

export type FormFeedbackPriority = 'good' | 'warning' | 'critical'

export interface FormFeedbackItem {
  title: string
  feedback: string
  priority: FormFeedbackPriority
}

export interface FormAnalysisResult {
  id: string
  video_id: string
  amplitude_assessment: string | null
  posture_assessment: string | null
  tempo_assessment: string | null
  compensations: string | null
  safety_notes: string | null
  three_key_feedback: FormFeedbackItem[]
  analysis_status: 'analyzing' | 'completed' | 'failed'
  created_at: string
}

export type ContentFormat = 'post' | 'story' | 'reels'

export interface ContentIdea {
  id: string
  professional_id: string
  batch_id: string
  format: ContentFormat
  title: string
  description: string
  caption_suggestion: string
  saved: boolean
  created_at: string
}

export type ConsultorIaRole = 'personal' | 'ai'

export interface ConsultorIaMessage {
  id: string
  professional_id: string
  role: ConsultorIaRole
  content: string
  created_at: string
}

export interface Challenge {
  id: string
  professional_id: string
  name: string
  description: string | null
  start_date: string
  end_date: string
  created_at: string
  total_participantes?: number
  status?: 'agendado' | 'ativo' | 'encerrado'
  leaderboard?: LeaderboardEntry[]
}

export interface DashboardNegocioKpis {
  total_alunos: number
  novos_no_mes: number
  inativos: number
  retencao_pct: number | null
}

export interface AlunoEmRisco {
  id: string
  name: string
  prioridade: 'alta' | 'media'
  motivo: string
}

export interface FinanceiroMes {
  mes_referencia: string
  receita: { total: number; por_tipo: Record<string, number> }
  despesas: { total: number }
  resultado_liquido: number
}

export interface DashboardNegocio {
  financeiro: FinanceiroMes
  kpis: DashboardNegocioKpis
  alunos_em_risco: AlunoEmRisco[]
}

export interface TipoNotificacao {
  chave: string
  nome: string
  descricao: string | null
  categoria: string
  publico: 'aluno' | 'personal'
  enabled: boolean
}

/** Faixa de plano da assinatura do personal com o TrainOS (config/planos_assinatura.php) —
 *  não confundir com StudentBillingPlan, que é a cobrança do aluno pelo personal. */
export interface PlanoAssinatura {
  nome: string
  limite_alunos: number
  valor_mensal: number
}

export interface FaturaAssinatura {
  valor: string
  status: 'aprovado' | 'recusado'
  pago_em: string | null
  created_at: string
}

/** CRM interno do produto (/admin) — financeiro do Clube Mais como empresa.
 *  Não confundir com DashboardNegocio, que é o financeiro de UM personal. */
export interface CrmResumo {
  faturamento: number
  custo_ia: number
  custo_ia_usd: number
  custo_plataforma: number
  lucro: number
  margem_pct: number | null
  mrr: number
}

export interface CrmAssinantes {
  ativas: number
  atrasadas: number
  bloqueadas: number
  canceladas: number
  pendentes: number
  em_teste_gratis: number
  teste_expirado_sem_assinar: number
  total_personais: number
}

export interface CrmMesSerie {
  mes: string
  faturamento: number
  custo_ia: number
  custo_plataforma: number
  lucro: number
}

export interface CrmPlano {
  plano_chave: string
  nome: string
  assinantes: number
  mrr: number
}

export interface CrmCustoPipeline {
  pipeline: string
  custo_usd: number
  custo_brl: number
  chamadas: number
  input_tokens: number
  output_tokens: number
}

export interface CrmRateio {
  id: string
  nome: string
  percentual: number
  valor: number
}

export interface CrmCustoProfissional {
  professional_id: string | null
  nome: string
  custo_usd: number
  custo_brl: number
  chamadas: number
}

export interface CrmDashboard {
  mes_referencia: string
  cotacao_usd_brl: number
  resumo: CrmResumo
  assinantes: CrmAssinantes
  planos: CrmPlano[]
  serie_mensal: CrmMesSerie[]
  custo_ia_por_pipeline: CrmCustoPipeline[]
  custo_ia_por_profissional: CrmCustoProfissional[]
  rateio_lucro: CrmRateio[]
  modelos_sem_preco: string[]
}

export interface CrmCusto {
  id: string
  description: string
  amount: string
  is_recurring: boolean
  starts_on: string
  ends_on: string | null
}

export interface CrmSocio {
  id: string
  nome: string
  percentual: string
  starts_on: string
}

export interface CrmAdmin {
  id: string
  name: string
  email: string
}

export interface StatusAssinatura {
  plano_chave: string | null
  status: 'pendente' | 'ativa' | 'atrasada' | 'bloqueada' | 'cancelada' | null
  em_teste: boolean
  dias_restantes_teste: number
  dias_restantes_carencia: number | null
  limite_alunos: number | null
  alunos_ativos: number
  proxima_cobranca_em: string | null
  planos: Record<string, PlanoAssinatura>
  faturas: FaturaAssinatura[]
}

/** Agenda semanal do personal — organização da própria rotina (horário fixo +
 *  troca pontual numa data específica), não frequência do aluno na academia. */
export interface AgendaSlot {
  id: string
  professional_id: string
  student_id: string | null
  titulo: string | null
  dia_semana: number
  hora: string
  duracao_minutos: number
  ativo: boolean
}

export interface HorarioAgenda {
  slot_id: string
  hora: string
  duracao_minutos: number
  student: { id: string; name: string } | null
  titulo: string | null
  presenca: 'presente' | 'falta' | null
  observacao: string | null
  eh_excecao: boolean
}

export interface DiaAgenda {
  data: string
  dia_semana: number
  horarios: HorarioAgenda[]
}

export interface SemanaAgenda {
  inicio_semana: string
  dias: DiaAgenda[]
}
