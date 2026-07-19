-- TrainOS — schema inicial (núcleo: profissional, aluno, exercício, treino, execução)
-- Banco provisório para prototipagem. Quando a equipe de TI assumir a infra
-- definitiva, este schema serve de referência para a migração.

create extension if not exists "uuid-ossp";

-- ─────────────────────────────────────────────
-- Profissional
-- ─────────────────────────────────────────────
create table if not exists professionals (
  id uuid primary key default uuid_generate_v4(),
  name text not null,
  email text not null unique,
  password_hash text not null,
  created_at timestamptz not null default now()
);

-- ─────────────────────────────────────────────
-- Aluno — cadastrado pelo profissional, acessa via link de convite (token)
-- sem necessidade de senha nesta fase do protótipo.
-- ─────────────────────────────────────────────
create table if not exists students (
  id uuid primary key default uuid_generate_v4(),
  professional_id uuid not null references professionals(id) on delete cascade,
  name text not null,
  email text,
  phone text,
  objective text,
  invite_token text not null unique,
  status text not null default 'active' check (status in ('active', 'inactive')),
  -- Piloto automático do chat: quando ligado, mensagens do aluno recebem
  -- resposta automática da IA simulando o personal trainer.
  ai_autopilot boolean not null default true,
  created_at timestamptz not null default now()
);

create index if not exists idx_students_professional on students(professional_id);
create index if not exists idx_students_token on students(invite_token);

-- ─────────────────────────────────────────────
-- Chat aluno ↔ profissional (com respostas opcionais da IA)
-- ─────────────────────────────────────────────
create table if not exists messages (
  id uuid primary key default uuid_generate_v4(),
  student_id uuid not null references students(id) on delete cascade,
  professional_id uuid not null references professionals(id) on delete cascade,
  sender text not null check (sender in ('student', 'professional', 'ai')),
  content text not null,
  created_at timestamptz not null default now()
);

create index if not exists idx_messages_student on messages(student_id, created_at);

-- ─────────────────────────────────────────────
-- Biblioteca de exercícios — global e curada nesta fase (seed),
-- sem exercícios próprios por profissional ainda.
-- ─────────────────────────────────────────────
create table if not exists exercises (
  id uuid primary key default uuid_generate_v4(),
  name text not null,
  muscle_group text not null,
  equipment text,
  instructions text,
  video_url text,
  created_at timestamptz not null default now()
);

-- ─────────────────────────────────────────────
-- Treino — prescrito pelo profissional para um aluno
-- ─────────────────────────────────────────────
create table if not exists workouts (
  id uuid primary key default uuid_generate_v4(),
  professional_id uuid not null references professionals(id) on delete cascade,
  student_id uuid not null references students(id) on delete cascade,
  name text not null,
  status text not null default 'draft' check (status in ('draft', 'sent')),
  sent_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create index if not exists idx_workouts_student on workouts(student_id);

create table if not exists workout_exercises (
  id uuid primary key default uuid_generate_v4(),
  workout_id uuid not null references workouts(id) on delete cascade,
  exercise_id uuid not null references exercises(id),
  order_index integer not null default 0,
  sets integer not null,
  reps text not null, -- texto pra caber "10-12", "AMRAP", "30s" etc.
  load_kg numeric,
  rest_seconds integer,
  notes text
);

create index if not exists idx_workout_exercises_workout on workout_exercises(workout_id);

-- ─────────────────────────────────────────────
-- Execução do treino pelo aluno
-- ─────────────────────────────────────────────
create table if not exists training_sessions (
  id uuid primary key default uuid_generate_v4(),
  workout_id uuid not null references workouts(id) on delete cascade,
  student_id uuid not null references students(id) on delete cascade,
  status text not null default 'in_progress' check (status in ('in_progress', 'completed')),
  started_at timestamptz not null default now(),
  finished_at timestamptz
);

create index if not exists idx_training_sessions_workout on training_sessions(workout_id);

create table if not exists session_entries (
  id uuid primary key default uuid_generate_v4(),
  training_session_id uuid not null references training_sessions(id) on delete cascade,
  workout_exercise_id uuid not null references workout_exercises(id),
  set_number integer not null,
  reps_done integer,
  load_kg_done numeric,
  notes text,
  created_at timestamptz not null default now()
);

create index if not exists idx_session_entries_session on session_entries(training_session_id);

create table if not exists feedbacks (
  id uuid primary key default uuid_generate_v4(),
  training_session_id uuid not null references training_sessions(id) on delete cascade unique,
  effort_rpe integer check (effort_rpe between 0 and 10),
  satisfaction integer check (satisfaction between 1 and 5),
  discomfort text,
  comment text,
  created_at timestamptz not null default now()
);
