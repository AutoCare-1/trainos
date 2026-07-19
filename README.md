# TrainOS (nome provisório)

Protótipo do núcleo do app para profissionais de Educação Física, baseado no
`BRIEFING_APP_EDUCACAO_FISICA.md`. Cobre o fluxo crítico: profissional cadastra
aluno → monta e envia treino → aluno abre o treino por um link (sem login) →
executa e registra cargas → dá feedback → profissional acompanha.

Fora de escopo nesta fase (ver seção 7/8/9 do briefing): pagamentos, chat,
avaliações físicas completas, periodização, gamificação, app mobile nativo,
IA, permissões/equipes, marca branca.

## Banco de dados

Provisório — Postgres local (via Homebrew), só até a equipe de TI assumir a
infra definitiva com a integração ao sistema já existente. O schema em
`backend/src/db/schema.sql` serve de referência para a migração.

```
brew services start postgresql@16
```

## Rodando localmente

### Backend

```bash
cd backend
cp .env.example .env   # já preenchido para uso local
npm install
npm run seed            # popula a biblioteca de exercícios
npm run dev              # http://localhost:3002
```

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
| Frontend | Next.js 16 (App Router) + Tailwind CSS |

## Estrutura

```
backend/src/
  db/          schema.sql, pool de conexão, seed de exercícios
  routes/      auth, alunos, exercicios, treinos, portal (público)
  services/    hash de senha, JWT
  middleware/  requireAuth

frontend/app/
  login/                  login e cadastro do profissional
  dashboard/              lista de alunos
  alunos/novo, alunos/[id]  cadastro e perfil do aluno
  treinos/novo, treinos/[id] criação e visualização de treino
  aluno/[token]/          portal público de execução do treino
```
