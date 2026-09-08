# Deploy — checklist pra colocar no ar

Atualizado em 07/09/2026. Objetivo imediato: um personal conseguir acessar de
casa dele, usar o app de verdade e dizer o que falta. Não existe nada em
produção hoje — este é o primeiro deploy, não uma troca.

- Backend Laravel → **Railway** (`Dockerfile` e `railway.toml` prontos em
  `backend-laravel/`).
- Frontend Next.js → **Vercel**.
- Vídeos de exercício (340 MB) → **Cloudflare R2** (código pronto em
  `config/demonstracoes.php` e no comando `exercicios:publicar-demonstracoes`).

As contas do Railway e da Vercel **já existem** — são as mesmas usadas no
Pre-consulta. Falta a do Cloudflare R2 (ou usar uma Cloudflare existente).

## Por que agora é a hora mais barata

O banco de produção vai nascer vazio. Migration que apaga dado (a troca do
catálogo de alimentos, por exemplo) custa zero num banco vazio e custa caro
depois que um personal real começou a registrar treino e refeição. Toda mudança
de esquema que a gente já sabia que precisava fazer, já está feita.

## Ordem importa (senão você volta atrás duas vezes)

Backend e frontend apontam um pro outro, e cada URL só existe depois do deploy
correspondente:

1. **Railway** sobe primeiro e te dá a URL do backend.
2. **Vercel** usa essa URL em `NEXT_PUBLIC_API_URL` e te dá a URL do frontend.
3. **Volta no Railway** e põe a URL da Vercel em `FRONTEND_URL` — é ela que
   libera o CORS. Sem esse passo o app abre e **nenhuma chamada funciona**, com
   erro só no console do navegador.

## 1. Railway — backend + banco

1. Novo projeto a partir do repo GitHub `AutoCare-1/trainos`.
2. No serviço criado, Settings → **Root Directory** = `backend-laravel`
   (é um monorepo — sem isso ele tenta buildar a raiz e falha).
3. Add → Database → **MySQL** (plugin do próprio Railway, no mesmo projeto).
4. No serviço do backend, aba **Variables** (usando a referência ao plugin
   MySQL, que o autocomplete do Railway sugere):
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
5. **`APP_KEY` e `JWT_SECRET`** — o app não sobe sem os dois (criptografia de
   sessão e assinatura de login). Não são segredo de conta nenhuma, são só um
   valor aleatório. Gere na hora, em qualquer computador com PHP:
   ```
   php -r 'echo "base64:".base64_encode(random_bytes(32));'
   php -r 'echo bin2hex(random_bytes(32));'
   ```
   O primeiro é o `APP_KEY` (já vem com o prefixo `base64:`), o segundo é o
   `JWT_SECRET`. **Nunca reaproveite um valor que apareceu numa conversa de
   chat** — inclusive esta. Gere na hora, cole direto no painel.
6. O resto (`ANTHROPIC_API_KEY`, R2, Mercado Pago, Strava) pode ficar com o
   placeholder do `.env.example` por enquanto — ver "O que funciona sem chave
   nenhuma" no fim.
7. Deploy. O Railway dá uma URL pública tipo `algo.up.railway.app` de graça —
   não precisa de domínio próprio pra esta etapa.

### O que o deploy faz sozinho, a cada subida

`docker/entrypoint.sh` roda antes do Apache:

- `migrate --force` (migration já aplicada não roda de novo);
- os 3 seeders da biblioteca de exercícios + `exercicios:aplicar-demonstracoes`;
- `NotificationTypesSeeder` — sem ele a tela de notificações sobe vazia e
  nenhum aviso é disparado;
- `AlimentoPofSeeder` — os 1.971 alimentos e as 7.771 medidas caseiras;
- ajusta o Apache pra escutar na `$PORT` que o Railway injeta. Sem isso o
  container sobe, responde na porta errada, o healthcheck de `/up` falha e o
  Railway derruba tudo com um log que não explica nada.

Nenhum deles sobrescreve dado de gente: são todos idempotentes, com chave
própria. É isso que faz "corrigir um vídeo", "arrumar um nome de alimento" ou
"adicionar exercício" virar realidade em produção **só com `git push`**.

## 2. Vercel — frontend

1. Importe o mesmo repo.
2. Project Settings → **Root Directory** = `frontend`.
3. Variável `NEXT_PUBLIC_API_URL` = a URL do Railway do passo 1.
4. Deploy. A Vercel também dá uma URL gratuita tipo `algo.vercel.app`.
5. **Volte no Railway** e adicione `FRONTEND_URL=https://<a URL da Vercel>`.
   Isso é o CORS (`config/cors.php` lê essa variável). O Railway reinicia o
   serviço sozinho quando você salva.

## 3. Cloudflare R2 — os vídeos

Sem este passo o app **funciona**, mas nenhum exercício tem vídeo: o
componente cai no desenho animado do movimento. Isso é feio-aceitável, não
quebrado — dá pra mandar pro personal antes de fazer o R2, se quiser.

1. Ative **R2** no painel da Cloudflare e crie um bucket, ex.
   `trainos-exercise-demos`.
2. R2 → **Manage API Tokens** → token com *Object Read & Write*, escopo só
   nesse bucket. Anote: Account ID, Access Key ID, Secret Access Key e o
   **endpoint S3** (`https://<account-id>.r2.cloudflarestorage.com`).
3. Ative acesso público ao bucket (ou um domínio custom R2) pra ter a
   `base_url` de onde os vídeos serão servidos.
4. Variáveis no Railway: `DEMONSTRACOES_DISCO`, `DEMONSTRACOES_BASE_URL`,
   `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`,
   `AWS_ENDPOINT`, `AWS_DEFAULT_REGION=auto`.
5. Com os `.mp4` na máquina (eles **não vêm pelo git** — 340 MB, ignorados):
   ```
   php artisan exercicios:publicar-demonstracoes --dry-run
   php artisan exercicios:publicar-demonstracoes
   php artisan exercicios:aplicar-demonstracoes --exportar
   ```
   O terceiro grava as URLs novas em `database/demonstracoes_geradas.php`.
   Commita esse arquivo: a partir daí quem clonar recebe as URLs pelo git, e o
   deploy aplica sozinho.

## O que funciona sem chave nenhuma

Confirmado no código, não só na intenção:

| Sem a chave | O que acontece |
| --- | --- |
| `ANTHROPIC_API_KEY` | O chat **registra a mensagem do aluno** e o profissional responde na mão; a aba de nutrição devolve "não consegui responder agora". O app não cai. |
| R2 | Exercício sem vídeo cai no desenho animado do movimento. |
| Mercado Pago | Só a tela de assinatura falha. |
| Strava | Só a tela de conexão com o Strava falha. |

Ou seja: dá pra lançar **hoje** e ligar cada uma dessas depois, sem mexer em
código — é variável de ambiente, o Railway reinicia sozinho quando você salva.

## Pendências pra próxima subida (Carol — 08/09)

Contexto: o Filipe testou no iPhone. Vídeo de exercício não aparecia — corrigido
em `0c95b75` + `22f2d3d` (o teu). Os 663 posters `.jpg` já estão no R2
(`exercicios:publicar-demonstracoes --posters`, rodado local).

1. **Redeploy do frontend** (Railway `frontend`) — o commit `22f2d3d` não
   disparou deploy sozinho; o bundle no ar ainda é o antigo.
2. **`php artisan exercicios:podar-biblioteca --force`** uma vez em produção.
   `1af0218` tirou 13 exercícios sem vídeo do seeder e botou na
   `biblioteca_podada.php`, mas a poda nunca roda no entrypoint — sem esse
   comando os 13 (e os 244 cortes antigos) seguem no banco de produção.

## Depois que estiver no ar

- Conferir o app de ponta a ponta no navegador de verdade: criar conta, criar
  aluno, montar treino, abrir o link do aluno, registrar refeição, tocar vídeo.
  Não só `curl`.
- A conta do personal que vai testar: ele mesmo cria em `/cadastro`. Não
  precisa de ninguém mexer no banco.

## Como o personal reporta o que está errado

Não vale construir uma tela só pra isso agora. Mais rápido: ele vê o exercício
errado (nome ou vídeo) e manda uma lista simples ("Rosca X: nome errado,
deveria ser Y" / "Supino Z: vídeo mostra outro exercício" / "faltou tal
exercício"). Você repassa e a gente processa em lote na próxima sessão. Se a
lista passar de uns 50 itens, a revisão precisa ser quebrada em conversas
separadas — revisão de vídeo em lote grande estoura o limite de uma sessão.
