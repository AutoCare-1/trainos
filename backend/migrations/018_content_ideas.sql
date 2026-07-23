-- Assistente de ideias de conteúdo pro Instagram do personal — funde tendência de
-- formato (cara, cacheada) com dado agregado/anônimo da base de alunos (barato,
-- sempre fresco). Visível só pro personal (JWT), nunca pro aluno.

-- Cache global (não por personal) da última pesquisa de tendências de formato —
-- tendência de reels/áudio/gancho é a mesma pra todo mundo, então uma busca na
-- web serve todos os personals dentro da janela de cache.
create table if not exists trend_cache (
  id uuid primary key default uuid_generate_v4(),
  content_snapshot text not null,
  cached_at timestamptz not null default now()
);

create table if not exists content_ideas (
  id uuid primary key default uuid_generate_v4(),
  professional_id uuid not null references professionals(id) on delete cascade,
  batch_id uuid not null,
  format text not null check (format in ('post', 'story', 'reels')),
  title text not null,
  description text not null,
  caption_suggestion text not null,
  saved boolean not null default false,
  created_at timestamptz not null default now()
);

create index if not exists idx_content_ideas_professional on content_ideas(professional_id, created_at desc);
