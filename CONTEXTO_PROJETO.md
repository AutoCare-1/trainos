# Clube Mais Personal — contexto do projeto

Este documento existe para quem está entrando no projeto agora (ou trocando de
conta/ferramenta de IA) entender rápido o que é, por que certas decisões foram
tomadas, e o que já existe vs. o que falta.

> Nome do repositório no GitHub ainda é `trainos` (codinome do início do
> projeto) — a marca do produto em si é **Clube Mais Personal**, confirmado
> pela Carol. Referências a "TrainOS" que ainda aparecerem soltas por aí são
> resquício do nome provisório, não um produto diferente.

## O que é

App brasileiro para profissionais de Educação Física (personal trainers,
academias, estúdios) gerenciarem alunos, treinos e a jornada completa do
aluno. Referência inicial de mercado: Nexur — mas o objetivo é um produto
mais completo e moderno, não uma cópia.

O briefing completo original (visão de produto, todos os módulos, MVP,
fases, arquitetura de domínio sugerida) está em
`BRIEFING_APP_EDUCACAO_FISICA.md` (fora deste repo — pedir para a Carol).
Este repositório implementa o **núcleo** desse briefing, priorizado por fases —
não tudo de uma vez.

## Decisões importantes (o "porquê")

### 1. Escopo do MVP foi cortado bem mais que o briefing original propõe
O briefing descreve, na prática, um produto SaaS maduro (gestão de alunos,
financeiro, periodização, gamificação, multi-tenant, marca branca — tudo).
Isso é trabalho de anos. A v0 provou só o **loop essencial**: profissional
cadastra aluno → monta treino → envia → aluno executa e registra carga → dá
feedback → profissional acompanha. Depois disso vieram, em ondas seguintes:
avaliação física, evolução de medidas, modelos de treino, vídeos customizados
e chat com Coach IA (ver "Estado atual" abaixo). Pagamento, periodização,
gamificação e app mobile nativo continuam fora de escopo por enquanto.

### 2. Banco de dados é provisório, e não é compartilhado com outros sistemas
A Carol tem outros produtos na área de saúde (AnamneseIA, TriagemAI) que
rodam ao lado de um sistema de prontuário médico chamado "Consultório na
Nuvem". Foi decidido explicitamente **não compartilhar banco de dados**
entre este app e esses sistemas de saúde — dado de saúde sensível e dado de
aluno de academia são domínios/finalidades diferentes, e misturar aumenta o
raio de impacto de qualquer incidente (mesmo princípio já aplicado antes
entre AnamneseIA e TriagemAI, que também não compartilham banco entre si).

Por isso: hoje o app roda em **PostgreSQL local** (via Homebrew, não Docker —
Docker não estava disponível no ambiente de desenvolvimento). Isso é
propositalmente **provisório**: quando a equipe de TI da Carol assumir a
infra definitiva, `backend/src/db/schema.sql` + `backend/migrations/` servem
de referência para a migração. Se no futuro for necessário integrar com outro
sistema da operação (financeiro, CRM, prontuário), isso deve ser via **API**,
nunca acesso direto a tabela de outro sistema.

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

## Estado atual (testado e funcionando)

**Núcleo (v0):**
- Cadastro/login do profissional (JWT)
- Cadastro de aluno, com geração de link de convite
- Biblioteca de exercícios com imagens reais de demonstração (fonte wger.de,
  CC-BY-SA, com crédito) e fallback de animação boneco-palito quando não há
  imagem/vídeo
- Criação e envio de treino (série/reps/carga por exercício), com **modelos
  de treino** reaproveitáveis entre alunos
- Portal do aluno: visualizar treino, iniciar sessão, registrar séries
  (reps/carga reais, retomando de onde parou se fechar e reabrir o app — ver
  nota de bugfix abaixo), dar feedback pós-treino (RPE, satisfação,
  desconforto)
- Dashboard do profissional reflete sessões concluídas e alerta alunos
  inativos (sem treinar há mais de 7 dias)

**Avaliação física e evolução:**
- PAR-Q resumido (4 perguntas de segurança) + observações de saúde por aluno
- Registro de medidas (peso, cintura, quadril, % gordura) ao longo do tempo
- Gráfico de evolução de peso, visível para profissional e para o próprio
  aluno (aba "Evolução" no portal)

**Vídeos customizados:**
- Cada profissional pode enviar seu próprio vídeo de demonstração para
  qualquer exercício da biblioteca — substitui o padrão só para os alunos
  dele, sem afetar outros profissionais

**Chat aluno ↔ profissional com Coach IA:**
- Aluno conversa pelo portal; se o "piloto automático" do aluno estiver
  ligado (`students.ai_autopilot`, toggle na tela do aluno no painel do
  profissional), a IA responde na hora simulando o assistente do personal —
  tom curto e motivador, com limites de segurança (não diagnostica; dor
  forte/sintoma preocupante → orienta procurar o profissional/médico;
  mudança de treino → encaminha ao professor). O profissional vê tudo e pode
  responder manualmente a qualquer momento.
- Backend: `backend/src/services/chat.ts` (Claude Haiku via
  `@anthropic-ai/sdk`, precisa de `ANTHROPIC_API_KEY` no `.env`). Se a IA
  falhar, a mensagem do aluno é registrada mesmo assim.

**Visual:** rebranding para **Clube Mais Personal** — tema claro com cores da
marca (azul `#2648b3`, lavanda `#8b7fd6`), logo e ícone em
`frontend/public/clubemais-*.png`. Base de estilo em
`frontend/app/globals.css` (classes `glass`, `btn-primary`, `input-dark`,
`bg-glow`).

## Bugfix relevante (histórico)

Depois de puxar as mudanças de avaliação física/modelos/vídeos, uma revisão
encontrou e corrigiu: `GET /portal/:token` não devolvia quantas séries já
haviam sido registradas na sessão em andamento. Se o aluno fechasse o app no
meio do treino e reabrisse o link, a tela reiniciava a contagem do zero, e
reenviar uma série já feita criava um registro duplicado no banco. Corrigido
adicionando `registeredCounts` na resposta do endpoint.

## Como rodar

Ver `README.md` na raiz para o passo a passo completo.

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
