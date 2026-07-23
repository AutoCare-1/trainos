-- Análise de forma em tempo real: o aluno filma uma série durante o treino e a
-- Coach IA analisa 3 frames-chave (início/meio/fim) do vídeo, retornando feedback
-- estruturado (amplitude, postura, tempo) em segundos, sem esperar o personal.
create table if not exists form_correction_videos (
  id uuid primary key default uuid_generate_v4(),
  student_id uuid not null references students(id) on delete cascade,
  workout_id uuid references workouts(id) on delete set null,
  exercise_id uuid not null references exercises(id) on delete cascade,
  video_file_path text not null,
  video_duration_seconds numeric(5, 2),
  created_at timestamptz not null default now()
);

create index if not exists idx_form_videos_student on form_correction_videos(student_id, created_at desc);
create index if not exists idx_form_videos_exercise on form_correction_videos(exercise_id);

create table if not exists form_analysis_results (
  id uuid primary key default uuid_generate_v4(),
  video_id uuid not null references form_correction_videos(id) on delete cascade,
  amplitude_assessment text,
  posture_assessment text,
  tempo_assessment text,
  compensations text,
  safety_notes text,
  three_key_feedback jsonb not null default '[]', -- [{title, feedback, priority: 'good'|'warning'|'critical'}]
  analysis_status text not null default 'completed' check (analysis_status in ('analyzing', 'completed', 'failed')),
  created_at timestamptz not null default now()
);

create index if not exists idx_form_analysis_video on form_analysis_results(video_id);

-- Contagem de quantas vezes o aluno já analisou a forma de cada exercício —
-- usado pra dar contexto de progresso ("já são 4 análises desse exercício").
create table if not exists form_feedback_history (
  id uuid primary key default uuid_generate_v4(),
  student_id uuid not null references students(id) on delete cascade,
  exercise_id uuid not null references exercises(id) on delete cascade,
  feedback_count integer not null default 0,
  last_feedback_at timestamptz,
  unique (student_id, exercise_id)
);
