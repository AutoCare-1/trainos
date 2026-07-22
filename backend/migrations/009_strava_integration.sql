-- Integração com Strava (e, futuramente, outros provedores de wearable/app de corrida).
-- Guarda a conexão OAuth do aluno e as atividades sincronizadas, separado dos
-- treinos prescritos pelo profissional.

create table if not exists device_connections (
  id uuid primary key default uuid_generate_v4(),
  student_id uuid not null references students(id) on delete cascade,
  provider text not null check (provider in ('strava')),
  provider_athlete_id text not null,
  access_token text not null,
  refresh_token text not null,
  expires_at timestamptz not null,
  scope text,
  connected_at timestamptz not null default now(),
  unique (student_id, provider)
);

create table if not exists external_activities (
  id uuid primary key default uuid_generate_v4(),
  student_id uuid not null references students(id) on delete cascade,
  provider text not null check (provider in ('strava')),
  external_id text not null,
  activity_type text not null,
  name text,
  started_at timestamptz not null,
  duration_seconds integer,
  distance_meters numeric,
  calories numeric,
  avg_heart_rate numeric,
  raw_payload jsonb,
  created_at timestamptz not null default now(),
  unique (provider, external_id)
);

create index if not exists idx_external_activities_student on external_activities(student_id, started_at desc);
