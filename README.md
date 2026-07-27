# Clube Mais Personal

App para profissionais de Educação Física gerenciarem alunos e treinos, baseado
no `BRIEFING_APP_EDUCACAO_FISICA.md`. Cobre o fluxo crítico: profissional
cadastra aluno → monta e envia treino → aluno abre o treino por um link (sem
login) → executa e registra cargas → dá feedback → profissional acompanha —
além de avaliação física, evolução de medidas, modelos de treino
reaproveitáveis, financeiro do personal, notificações push e chat com Coach
IA.

Fora de escopo nesta fase: cobrança/plano do próprio personal, periodização,
app mobile nativo, permissões/equipes, marca branca multi-cliente.

## Banco de dados

**MySQL**, confirmado como definitivo pela equipe que vai administrar a
infra (backup diário). Cada pessoa roda sua própria instância local (backend
+ banco + frontend na própria máquina) — não há banco compartilhado entre
quem está desenvolvendo.

## Configuração inicial (primeira vez, por máquina)

```bash
# 1. Instalar e subir o MySQL
brew install mysql
brew services start mysql

# 2. Criar o usuário e o banco (senha combinando com o DB_PASSWORD do .env.example)
mysql -u root -e "CREATE USER 'trainos'@'localhost' IDENTIFIED BY 'trainos';"
mysql -u root -e "CREATE DATABASE trainos_laravel;"
mysql -u root -e "GRANT ALL PRIVILEGES ON trainos_laravel.* TO 'trainos'@'localhost';"
```

## Rodando localmente

### Backend

```bash
cd backend-laravel
cp .env.example .env   # preencha ANTHROPIC_API_KEY (pergunte à Carol — não está no repo)
composer install
php artisan key:generate
php artisan jwt:secret
php artisan migrate
php artisan db:seed      # popula a biblioteca de exercícios (com imagens)
php artisan serve --port=3003
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
| Backend | Laravel 12 + PHP 8.3 |
| Banco | MySQL |
| Auth do profissional | JWT (`php-open-source-saver/jwt-auth`) |
| Acesso do aluno | link com token (sem senha, como no AnamneseIA) |
| IA (Coach, Consultor IA, análises) | Claude Haiku via `anthropic-ai/sdk` (PHP) |
| Notificações push | Web Push via PWA (`laravel-notification-channels/webpush`), fila `database` |
| Frontend | Next.js 16 (App Router) + Tailwind CSS |

## Estrutura

```
backend-laravel/
  app/Http/Controllers/   auth, alunos, exercicios, treinos, modelos, desafios,
                           strava, academia, conteudo, consultor-ia, negocio,
                           gastos, notificacoes, portal (público)
  app/Support/            helpers de domínio (Money, Checkins, ConteudoAgregados,
                           FinanceiroPersonal, ConsultorFerramentas)
  app/Notifications/      regras de notificação push (uma classe por tipo)
  database/migrations/    schema, numeradas por data
  routes/api.php          todas as rotas

frontend/app/
  login/                     login e cadastro do profissional
  dashboard/                 lista de alunos
  alunos/novo, alunos/[id]   cadastro, edição, perfil, avaliação física e chat
  treinos/novo, treinos/[id] criação (com modelos) e visualização de treino
  modelos/                   modelos de treino reaproveitáveis
  videos/                    upload de vídeo customizado por exercício
  academia/                  revisão de análises de academia
  consultor-ia/              chat sobre a base de alunos
  conteudo/                  ideias de conteúdo
  negocio/, gastos/          financeiro do personal
  desafios/                  gamificação
  notificacoes/              preferências de notificação
  aluno/[token]/             portal público: treino, evolução e chat
```
