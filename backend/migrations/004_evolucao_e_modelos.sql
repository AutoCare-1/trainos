-- Evolução física: registros de peso ao longo do tempo, feitos pelo profissional.
create table if not exists body_measurements (
  id uuid primary key default uuid_generate_v4(),
  student_id uuid not null references students(id) on delete cascade,
  recorded_at date not null default current_date,
  weight_kg numeric,
  notes text,
  created_at timestamptz not null default now()
);

create index if not exists idx_body_measurements_student on body_measurements(student_id, recorded_at);

-- Modelos de treino: reaproveitáveis entre alunos, não presos a um aluno específico.
create table if not exists workout_templates (
  id uuid primary key default uuid_generate_v4(),
  professional_id uuid not null references professionals(id) on delete cascade,
  name text not null,
  created_at timestamptz not null default now()
);

create table if not exists workout_template_exercises (
  id uuid primary key default uuid_generate_v4(),
  template_id uuid not null references workout_templates(id) on delete cascade,
  exercise_id uuid not null references exercises(id),
  order_index integer not null default 0,
  sets integer not null,
  reps text not null,
  load_kg numeric,
  rest_seconds integer,
  notes text
);

create index if not exists idx_workout_template_exercises_template on workout_template_exercises(template_id);
