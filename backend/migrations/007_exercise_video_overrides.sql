-- Permite que cada profissional substitua o vídeo padrão de um exercício pelo próprio,
-- sem afetar outros profissionais que usem a mesma biblioteca de exercícios.
create table if not exists exercise_media_overrides (
  id uuid primary key default uuid_generate_v4(),
  professional_id uuid not null references professionals(id) on delete cascade,
  exercise_id uuid not null references exercises(id) on delete cascade,
  video_url text not null,
  created_at timestamptz not null default now(),
  unique (professional_id, exercise_id)
);
