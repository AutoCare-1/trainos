# Clube Mais Personal

Protótipo do núcleo do app para profissionais de Educação Física, baseado no
`BRIEFING_APP_EDUCACAO_FISICA.md`. Cobre o fluxo crítico: profissional cadastra
aluno → monta e envia treino → aluno abre o treino por um link (sem login) →
executa e registra cargas → dá feedback → profissional acompanha — além de
avaliação física, evolução de medidas, modelos de treino reaproveitáveis e
chat com Coach IA.

Fora de escopo nesta fase (ver seção 7/8/9 do briefing): pagamentos,
periodização, gamificação, app mobile nativo, permissões/equipes, marca branca
multi-cliente.

## Banco de dados

Provisório — Postgres local (via Homebrew), só até a equipe de TI assumir a
infra definitiva com a integração ao sistema já existente. O schema em
`backend/src/db/schema.sql` + `backend/migrations/` serve de referência para
a migração.

```
brew services start postgresql@16
```

## Rodando localmente

### Backend

```bash
cd backend
cp .env.example .env   # preencha ANTHROPIC_API_KEY (pergunte à Carol — não está no repo)
npm install
npm run seed             # popula a biblioteca de exercícios (com imagens)
npm run dev               # http://localhost:3002
```

Aplique as migrations em `backend/migrations/` (em ordem numérica) no banco
local antes do primeiro `npm run dev`, se ainda não aplicou.

### Frontend

```bash
cd frontend
cp .env.local.example .env.local
npm install
npm run dev               # http://localhost:3101 (via .claude/launch.json)
```

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | Node.js + Express + TypeScript |
| Banco | PostgreSQL (local, provisório) |
| Auth do profissional | JWT + bcrypt |
| Acesso do aluno | link com token (sem senha, como no AnamneseIA) |
| IA (Coach do chat) | Claude Haiku via `@anthropic-ai/sdk` |
| Frontend | Next.js 16 (App Router) + Tailwind CSS |

## Estrutura

```
backend/src/
  db/          schema.sql, pool de conexão, seed de exercícios
  routes/      auth, alunos, exercicios, treinos, modelos, portal (público)
  services/    hash de senha, JWT, IA do chat
  middleware/  requireAuth, asyncHandler

frontend/app/
  login/                     login e cadastro do profissional
  dashboard/                 lista de alunos
  alunos/novo, alunos/[id]   cadastro, perfil, avaliação física e chat do aluno
  treinos/novo, treinos/[id] criação (com modelos) e visualização de treino
  modelos/                   modelos de treino reaproveitáveis
  videos/                    upload de vídeo customizado por exercício
  aluno/[token]/             portal público: treino, evolução e chat
```
