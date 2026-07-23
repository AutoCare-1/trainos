export interface Professional {
  id: string
  name: string
  email: string
}

export interface ParQAnswers {
  cardiaco: boolean
  tontura: boolean
  articular: boolean
  pressao_medicacao: boolean
}

export interface Student {
  id: string
  name: string
  email: string | null
  phone: string | null
  objective: string | null
  weight_kg: number | null
  height_cm: number | null
  invite_token: string
  status: 'active' | 'inactive'
  ai_autopilot: boolean
  par_q_answers: ParQAnswers | null
  health_notes: string | null
  onboarding_completed_at: string | null
  photo_url: string | null
  created_at: string
  ultimo_treino?: string | null
  sessoes_concluidas?: number
  ultima_sessao_em?: string | null
  tem_treino_enviado?: boolean
  exercicios_sem_progresso?: number
}

export interface BodyPhoto {
  id: string
  student_id: string
  taken_at: string
  ai_feedback: string | null
  compared_to_photo_id: string | null
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
  created_at: string
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
