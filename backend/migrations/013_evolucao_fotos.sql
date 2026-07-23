-- Evolução física por fotos: o aluno registra fotos do próprio corpo ao longo do
-- tempo e a Coach IA comenta comparando com a foto anterior (dado sensível — os
-- arquivos ficam fora do /uploads público, servidos só por rota autenticada).
create table if not exists body_photos (
  id uuid primary key default uuid_generate_v4(),
  student_id uuid not null references students(id) on delete cascade,
  file_path text not null,
  taken_at timestamptz not null default now(),
  ai_feedback text,
  compared_to_photo_id uuid references body_photos(id) on delete set null,
  created_at timestamptz not null default now()
);

create index if not exists idx_body_photos_student on body_photos(student_id, taken_at desc);
