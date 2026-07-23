-- Check-in de frequência: o aluno registra 1 foto por dia como prova de que foi
-- treinar (independe da foto de evolução física). A unicidade por dia garante que
-- postar de novo no mesmo dia não duplica a contagem — só atualiza a foto do dia.
create table if not exists checkins (
  id uuid primary key default uuid_generate_v4(),
  student_id uuid not null references students(id) on delete cascade,
  checkin_date date not null default current_date,
  file_path text not null,
  created_at timestamptz not null default now(),
  unique (student_id, checkin_date)
);

create index if not exists idx_checkins_student on checkins(student_id, checkin_date desc);
