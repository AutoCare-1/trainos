export interface Professional {
  id: string
  name: string
  email: string
}

export interface Student {
  id: string
  name: string
  email: string | null
  phone: string | null
  objective: string | null
  invite_token: string
  status: 'active' | 'inactive'
  ai_autopilot: boolean
  created_at: string
  ultimo_treino?: string | null
  sessoes_concluidas?: number
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
  exercise_name: string
  muscle_group: string
  instructions: string | null
  video_url: string | null
}
