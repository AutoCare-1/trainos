-- Desafios (estilo Gym Rats): o profissional cria um desafio com prazo e convida alunos;
-- pontuação vem de treinos concluídos no período (não de carga/peso), pra nivelar entre alunos.
create table if not exists challenges (
  id uuid primary key default uuid_generate_v4(),
  professional_id uuid not null references professionals(id) on delete cascade,
  name text not null,
  description text,
  start_date date not null,
  end_date date not null,
  created_at timestamptz not null default now()
);

create table if not exists challenge_participants (
  id uuid primary key default uuid_generate_v4(),
  challenge_id uuid not null references challenges(id) on delete cascade,
  student_id uuid not null references students(id) on delete cascade,
  unique (challenge_id, student_id)
);

create index if not exists idx_challenge_participants_challenge on challenge_participants(challenge_id);
create index if not exists idx_challenge_participants_student on challenge_participants(student_id);
