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
