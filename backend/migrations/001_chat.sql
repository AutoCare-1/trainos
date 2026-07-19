-- Chat aluno ↔ profissional, com respostas opcionais da IA (personal trainer virtual).

-- Piloto automático: quando ligado, cada mensagem do aluno recebe uma resposta
-- automática da IA simulando o personal. O profissional pode intervir manualmente
-- a qualquer momento.
alter table students add column if not exists ai_autopilot boolean not null default true;

create table if not exists messages (
  id uuid primary key default uuid_generate_v4(),
  student_id uuid not null references students(id) on delete cascade,
  professional_id uuid not null references professionals(id) on delete cascade,
  sender text not null check (sender in ('student', 'professional', 'ai')),
  content text not null,
  created_at timestamptz not null default now()
);

create index if not exists idx_messages_student on messages(student_id, created_at);
