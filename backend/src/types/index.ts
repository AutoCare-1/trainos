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

export type StructureType =
  | 'tradicional'
  | 'bi-set'
  | 'tri-set'
  | 'superset'
  | 'circuito'
  | 'drop-set'
  | 'rest-pause'
  | 'cluster'
  | 'amrap'
  | 'emom'

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
  structure_type: StructureType
  group_label: string | null
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

export interface BodyPhoto {
  id: string
  student_id: string
  file_path: string
  taken_at: string
  ai_feedback: string | null
  compared_to_photo_id: string | null
  created_at: string
}

export interface CheckIn {
  id: string
  student_id: string
  checkin_date: string
  file_path: string
  comment: string | null
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
  structure_type: StructureType
  group_label: string | null
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

export type GymSubmissionType = 'photo' | 'video' | 'album'
export type GymSubmissionStatus = 'analyzing' | 'completed' | 'failed'

export interface GymMediaSubmission {
  id: string
  student_id: string
  professional_id: string
  submission_type: GymSubmissionType
  days_per_week: number | null
  status: GymSubmissionStatus
  error_message: string | null
  created_at: string
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

export interface MachinesJson {
  machines: MachineDetectado[]
}

export interface GymAnalysisResult {
  id: string
  submission_id: string
  machines_json: MachinesJson
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
}

export type GymApprovalStatus = 'pending' | 'approved' | 'rejected'

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

export interface FormCorrectionVideo {
  id: string
  student_id: string
  workout_id: string | null
  exercise_id: string
  video_file_path: string
  video_duration_seconds: number | null
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
