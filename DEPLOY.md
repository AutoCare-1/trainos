# Deploy — checklist pra colocar no ar

Escrito em 01/09/2026. Objetivo imediato: um personal conseguir acessar de
casa dele pra revisar nome/vídeo de exercício e pedir o que falta. Não existe
nada em produção hoje — este é o primeiro deploy, não uma troca.

Backend Laravel → **Railway** (Dockerfile e `railway.toml` já prontos em
`backend-laravel/`). Frontend Next.js → **Vercel**. Vídeos de exercício
(546 MB) → **Cloudflare R2** (código já pronto em `config/demonstracoes.php`
e no comando `exercicios:publicar-demonstracoes`).

## O que só você pode fazer (criar conta, colocar cartão)

### 1. Railway — backend + banco
1. Crie conta em railway.app, novo projeto a partir do repo GitHub
   `AutoCare-1/trainos`.
2. No serviço criado, em Settings → **Root Directory**, coloque
   `backend-laravel` (é um monorepo — sem isso ele tenta buildar a raiz).
3. Add → Database → **MySQL** (plugin do próprio Railway, mesmo projeto).
4. No serviço do backend, aba **Variables**, adicione (usando a referência ao
   plugin MySQL, que o próprio Railway sugere no autocomplete):
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=${{RAILWAY_PUBLIC_DOMAIN}}
   DB_CONNECTION=mysql
   DB_HOST=${{MySQL.MYSQLHOST}}
   DB_PORT=${{MySQL.MYSQLPORT}}
   DB_DATABASE=${{MySQL.MYSQLDATABASE}}
   DB_USERNAME=${{MySQL.MYSQLUSER}}
   DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
   ```
   (o resto das variáveis — `APP_KEY`, `ANTHROPIC_API_KEY`, JWT, Mercado
   Pago, R2 — eu preencho com você numa chamada de tela ou você cola aqui no
   chat os nomes das chaves sem o valor, que eu digo onde cada uma vai.)
5. Deploy. Railway dá uma URL pública tipo `algo.up.railway.app` de graça —
   não precisa de domínio próprio pra esta etapa.

### 2. Cloudflare R2 — os vídeos
1. Crie conta em cloudflare.com (ou use uma existente), ative **R2** no
   painel.
2. Crie um bucket, ex. `trainos-exercise-demos`.
3. R2 → **Manage API Tokens** → criar token com permissão *Object Read &
   Write*, escopo só nesse bucket. Anote: Account ID, Access Key ID, Secret
   Access Key, e o **endpoint S3** (formato
   `https://<account-id>.r2.cloudflarestorage.com`).
4. Ative acesso público ao bucket (ou um domínio customizado R2) pra pegar a
   `base_url` de onde os vídeos vão ser servidos.

### 3. Vercel — frontend
1. Crie conta em vercel.com, importe o mesmo repo.
2. Em Project Settings → **Root Directory**, coloque `frontend`.
3. Variável de ambiente `NEXT_PUBLIC_API_URL` = a URL do Railway do passo 1
   (só dá pra preencher depois que o backend estiver no ar).
4. Deploy. Vercel também dá uma URL gratuita tipo `algo.vercel.app`.

## O que eu faço depois que essas 3 contas existirem

- Preencho o resto das variáveis de ambiente do Railway (`APP_KEY` gerada,
  chaves de IA, JWT, R2) — sem nunca ver os valores que forem segredo puro:
  você cola no painel do Railway/Cloudflare/Vercel diretamente, eu só digo
  qual variável recebe o quê.
- Rodo `php artisan exercicios:publicar-demonstracoes` apontando pro bucket
  R2 — sobe os 546 MB e grava a URL de cada vídeo no banco de produção.
- Confirmo o deploy de ponta a ponta no navegador de verdade (login, listar
  exercício, tocar vídeo) — não só `curl`.
- Crio (ou você me passa) a conta do personal nesse banco de produção, e te
  devolvo o link + a senha dele.

## Como o personal reporta o que está errado

Não vale a pena construir uma tela nova só pra isso agora — mais rápido: ele
vê o exercício errado (nome ou vídeo) e manda pra você uma lista simples
("Rosca X: nome errado, deveria ser Y" / "Supino Z: vídeo mostra outro
exercício" / "faltou tal exercício"). Você me repassa e eu processo em lote
na próxima sessão. Se a lista crescer muito (50+ itens), aviso antes de
revisar tudo numa conversa só — revisão de vídeo em lote grande estoura o
limite de uma sessão (ver memória `feedback-revisao-video-em-lotes`).
