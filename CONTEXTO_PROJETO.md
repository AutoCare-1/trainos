# TrainOS — contexto do projeto

Este documento existe para quem está entrando no projeto agora (ou trocando de
conta/ferramenta de IA) entender rápido o que é, por que certas decisões foram
tomadas, e o que já existe vs. o que falta.

## O que é

App brasileiro para profissionais de Educação Física (personal trainers,
academias, estúdios) gerenciarem alunos, treinos e a jornada completa do
aluno. Referência inicial de mercado: Nexur — mas o objetivo é um produto
mais completo e moderno, não uma cópia.

O briefing completo original (visão de produto, todos os módulos, MVP,
fases, arquitetura de domínio sugerida) está em
`BRIEFING_APP_EDUCACAO_FISICA.md` (fora deste repo — pedir para a Carol).
Este repositório implementa o **núcleo mínimo** desse briefing, não tudo.

## Decisões importantes (o "porquê")

### 1. Escopo do MVP foi cortado bem mais que o briefing original propõe
O briefing descreve, na prática, um produto SaaS maduro (gestão de alunos,
financeiro, chat, avaliações, periodização, gamificação, IA, multi-tenant,
marca branca — tudo). Isso é trabalho de anos. A v0 aqui prova só o **loop
essencial**: profissional cadastra aluno → monta treino → envia → aluno
executa e registra carga → dá feedback → profissional acompanha. Tudo mais
(pagamento, avaliação física completa, periodização, gamificação, mobile
nativo) fica para depois, de propósito.

### 2. Banco de dados é provisório, e não é compartilhado com outros sistemas
A Carol tem outros produtos na área de saúde (AnamneseIA, TriagemAI) que
rodam ao lado de um sistema de prontuário médico chamado "Consultório na
Nuvem". Foi decidido explicitamente **não compartilhar banco de dados**
entre o TrainOS e esses sistemas de saúde — dado de saúde sensível e dado de
aluno de academia são domínios/finalidades diferentes, e misturar aumenta o
raio de impacto de qualquer incidente (mesmo princípio já aplicado antes
entre AnamneseIA e TriagemAI, que também não compartilham banco entre si).

Por isso: hoje o TrainOS roda em **PostgreSQL local** (via Homebrew, não
Docker — Docker não estava disponível no ambiente de desenvolvimento). Isso é
propositalmente **provisório**: quando a equipe de TI da Carol assumir a
infra definitiva, `backend/src/db/schema.sql` serve de referência para a
migração. Se no futuro for necessário integrar com outro sistema da operação
(financeiro, CRM, prontuário), isso deve ser via **API**, nunca acesso direto
a tabela de outro sistema.

### 3. Portal do aluno não tem login/senha — só um link com token
Mesmo padrão já validado no AnamneseIA: o profissional cadastra o aluno, o
sistema gera um token único, e o link `/aluno/<token>` dá acesso direto ao
treino daquele aluno, sem criar conta. Reduz fricção para o aluno (que só
recebe um link) e foi um padrão que já funcionou bem nesse outro produto.

### 4. Stack: Postgres puro (`pg`), não Supabase
Os outros projetos da Carol (AnamneseIA, TriagemAI) usam Supabase. Aqui
optei por Postgres "cru" via driver `pg`, sem depender de nenhuma
plataforma específica — já que o banco final ainda não está decidido e vai
ser trocado quando a TI assumir, não faz sentido criar dependência de
vendor agora.

## Estado atual (o que já funciona, testado no navegador)

- Cadastro/login do profissional (JWT)
- Cadastro de aluno, com geração de link de convite
- Biblioteca de exercícios (15 exercícios seedados)
- Criação e envio de treino (série/reps/carga por exercício)
- Portal do aluno: visualizar treino, iniciar sessão, registrar séries
  (reps/carga reais), dar feedback pós-treino (RPE, satisfação, desconforto)
- Dashboard do profissional reflete sessões concluídas dos alunos

Fluxo completo validado ponta a ponta no navegador (ver `README.md`).

## Também pronto (segunda leva)

- **Chat aluno ↔ profissional com Coach IA**: aluno conversa pelo portal; se o
  "piloto automático" do aluno estiver ligado (`students.ai_autopilot`, toggle
  na tela do aluno no painel do profissional), a IA responde na hora simulando
  o assistente do personal — tom curto e motivador, com limites de segurança
  (não diagnostica; dor forte/sintoma preocupante → orienta procurar o
  profissional/médico; mudança de treino → encaminha ao professor). O
  profissional vê tudo e pode responder manualmente a qualquer momento.
  Backend: `backend/src/services/chat.ts` (Claude Haiku via `@anthropic-ai/sdk`,
  precisa de `ANTHROPIC_API_KEY` no `.env`), rotas em `alunos.ts` (lado
  profissional) e `portal.ts` (lado aluno). Se a IA falhar, a mensagem do aluno
  é registrada mesmo assim — o chat nunca perde mensagem por causa da IA.
- **Redesign visual**: tema escuro premium (glassmorphism, gradiente
  esmeralda→ciano, avatares com iniciais, pills de status, barra de progresso
  do treino). Base em `frontend/app/globals.css` (classes `glass`,
  `btn-primary`, `input-dark`, `bg-glow`); portal do aluno com abas
  Treino/Chat, mobile-first.

## Como rodar

Ver `README.md` na raiz — resumo:

```bash
brew services start postgresql@16   # banco local

cd backend && cp .env.example .env  # preencher ANTHROPIC_API_KEY (pedir à Carol, não está no repo)
npm install && npm run seed && npm run dev   # localhost:3002

cd frontend && cp .env.local.example .env.local
npm install && npm run dev          # localhost:3101
```

## Deploy (quando for a hora)

Padrão já validado em outro projeto da Carol (AnamneseIA): backend
Node/Express → Railway, frontend Next.js → Vercel, via CLI. Pontos que
já causaram problema antes e vale saber de antemão:

- Railway precisa de `railway add --service nome` antes de `railway
  variables --set`, senão dá erro "Project has no services"
- Não fixar `PORT` nas env vars do Railway — ele injeta a dele
- Depois de publicar o frontend na Vercel, **voltar no backend** e atualizar
  `FRONTEND_URL` com a URL real, senão CORS bloqueia tudo
- Testar de verdade abrindo o link no navegador (não só `curl`), incluindo
  uma ação de POST, para garantir que CORS libera todos os métodos

## Estrutura

```
backend/src/
  db/          schema.sql, pool de conexão, seed de exercícios
  routes/      auth, alunos, exercicios, treinos, portal (público)
  services/    hash de senha, JWT, IA do chat
  middleware/  requireAuth

frontend/app/
  login/                     login e cadastro do profissional
  dashboard/                 lista de alunos
  alunos/novo, alunos/[id]   cadastro e perfil do aluno
  treinos/novo, treinos/[id] criação e visualização de treino
  aluno/[token]/             portal público de execução do treino
```
