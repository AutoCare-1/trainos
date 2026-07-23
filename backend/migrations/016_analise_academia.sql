-- Análise de academia por mídia (foto, vídeo ou álbum): o aluno envia mídia da
-- academia dele, a IA detecta máquinas/equipamentos disponíveis e recomenda um
-- treino usando a biblioteca real de exercícios; o personal revisa e aprova.

create table if not exists gym_media_submissions (
  id uuid primary key default uuid_generate_v4(),
  student_id uuid not null references students(id) on delete cascade,
  professional_id uuid not null references professionals(id) on delete cascade,
  submission_type text not null check (submission_type in ('photo', 'video', 'album')),
  days_per_week integer,
  status text not null default 'analyzing' check (status in ('analyzing', 'completed', 'failed')),
  error_message text,
  created_at timestamptz not null default now()
);

create index if not exists idx_gym_submissions_student on gym_media_submissions(student_id, created_at desc);
create index if not exists idx_gym_submissions_professional on gym_media_submissions(professional_id, status);

-- Cada asset é uma imagem original enviada ou um frame extraído de vídeo.
create table if not exists gym_media_assets (
  id uuid primary key default uuid_generate_v4(),
  submission_id uuid not null references gym_media_submissions(id) on delete cascade,
  asset_type text not null check (asset_type in ('photo', 'video_frame')),
  file_path text not null,
  frame_index integer,
  created_at timestamptz not null default now()
);

create index if not exists idx_gym_assets_submission on gym_media_assets(submission_id);

create table if not exists gym_analysis_results (
  id uuid primary key default uuid_generate_v4(),
  submission_id uuid not null references gym_media_submissions(id) on delete cascade,
  machines_json jsonb not null,
  zones_identified text[] not null default '{}',
  total_unique_machines integer not null default 0,
  coverage_estimate text,
  gaps text[] not null default '{}',
  notes text,
  created_at timestamptz not null default now()
);

create index if not exists idx_gym_analysis_submission on gym_analysis_results(submission_id);

create table if not exists gym_workout_recommendations (
  id uuid primary key default uuid_generate_v4(),
  submission_id uuid not null references gym_media_submissions(id) on delete cascade,
  analysis_result_id uuid not null references gym_analysis_results(id) on delete cascade,
  name text not null,
  split_type text,
  reasoning text,
  recommended_items jsonb not null, -- [{ exercise_id, exercise_name, day_number, day_name, sets, reps, rest_seconds, notes }]
  approval_status text not null default 'pending' check (approval_status in ('pending', 'approved', 'rejected')),
  approved_workout_id uuid references workouts(id) on delete set null,
  professional_notes text,
  approved_at timestamptz,
  created_at timestamptz not null default now()
);

create index if not exists idx_gym_recommendations_submission on gym_workout_recommendations(submission_id);
create index if not exists idx_gym_recommendations_status on gym_workout_recommendations(approval_status);
