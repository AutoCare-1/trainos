-- Consultor IA: chat onde o personal pergunta sobre os próprios alunos em
-- linguagem natural. A IA nunca acessa o banco direto — só via tool use,
-- sempre escopado ao personal_id do JWT (implementado no backend, não aqui).
create table if not exists consultor_ia_messages (
  id uuid primary key default uuid_generate_v4(),
  professional_id uuid not null references professionals(id) on delete cascade,
  role text not null check (role in ('personal', 'ai')),
  content text not null,
  created_at timestamptz not null default now()
);

create index if not exists idx_consultor_ia_messages_professional on consultor_ia_messages(professional_id, created_at);
