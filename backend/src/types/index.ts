export interface Professional {
  id: string
  name: string
  email: string
  password_hash: string
  created_at: string
}

export interface ParQAnswers {
  cardiaco: boolean
  tontura: boolean
  articular: boolean
  pressao_medicacao: boolean
}

export interface Student {
  id: string
  professional_id: string
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
}

export type MessageSender = 'student' | 'professional' | 'ai'

export interface Message {
  id: string
  student_id: string
  professional_id: string
  sender: MessageSender
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
  created_at: string
}

export interface Workout {
  id: string
  professional_id: string
  student_id: string
  name: string
  status: 'draft' | 'sent'
  sent_at: string | null
  created_at: string
  updated_at: string
}

export interface WorkoutExercise {
  id: string
  workout_id: string
  exercise_id: string
  order_index: number
  sets: number
  reps: string
  load_kg: number | null
  rest_seconds: number | null
  notes: string | null
}

export interface TrainingSession {
  id: string
  workout_id: string
  student_id: string
  status: 'in_progress' | 'completed'
  started_at: string
  finished_at: string | null
}

export interface SessionEntry {
  id: string
  training_session_id: string
  workout_exercise_id: string
  set_number: number
  reps_done: number | null
  load_kg_done: number | null
  notes: string | null
  created_at: string
}

export interface Feedback {
  id: string
  training_session_id: string
  effort_rpe: number | null
  satisfaction: number | null
  discomfort: string | null
  comment: string | null
  created_at: string
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

export interface WorkoutTemplate {
  id: string
  professional_id: string
  name: string
  created_at: string
}

export interface WorkoutTemplateExercise {
  id: string
  template_id: string
  exercise_id: string
  order_index: number
  sets: number
  reps: string
  load_kg: number | null
  rest_seconds: number | null
  notes: string | null
}

export interface DeviceConnection {
  id: string
  student_id: string
  provider: 'strava'
  provider_athlete_id: string
  access_token: string
  refresh_token: string
  expires_at: string
  scope: string | null
  connected_at: string
}

export interface ExternalActivity {
  id: string
  student_id: string
  provider: 'strava'
  external_id: string
  activity_type: string
  name: string | null
  started_at: string
  duration_seconds: number | null
  distance_meters: number | null
  calories: number | null
  avg_heart_rate: number | null
  raw_payload: unknown
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
}

export interface ChallengeParticipant {
  id: string
  challenge_id: string
  student_id: string
}
