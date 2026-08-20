# Clube Mais Personal — dossiê completo do produto

> **Para que serve este documento:** dar a alguém (pessoa ou IA) que nunca viu o
> projeto o contexto necessário para **avaliar o produto** — o que ele é, o que
> já funciona, como está construído, quais decisões foram tomadas e por quê, e
> onde estão os riscos e as lacunas.
>
> **Data:** 20/08/2026 · **Commit:** `def8ece` (branch `main`, sincronizada com
> `github.com/AutoCare-1/trainos`)
>
> Escrito para ser lido de cima a baixo, mas as seções são independentes. A
> seção **"Riscos e lacunas conhecidas"** no fim é provavelmente a mais útil
> para quem vai avaliar criticamente.

---

## 1. O que é o produto

App brasileiro para **personal trainers** gerenciarem alunos, treinos e a
jornada completa do aluno — com IA embutida em 8 pontos do fluxo.

- **Modelo de negócio:** SaaS por assinatura mensal do personal, com faixas de
  preço por número de alunos ativos. O personal cobra o aluno dele à parte
  (o app só ajuda a controlar essa receita, não processa esse pagamento).
- **Público:** personal trainers autônomos, estúdios e academias no Brasil.
- **Referência inicial de mercado:** Nexur — mas o objetivo declarado é um
  produto mais completo e moderno, não uma cópia.
- **Repositório:** `AutoCare-1/trainos` (o nome `trainos` é codinome do início
  do projeto; a marca é **Clube Mais Personal**). Referências soltas a
  "TrainOS" pelo código são resquício, não outro produto.

### Quem toca o projeto

- **Filipe** (dono do produto, conduz o desenvolvimento com IA)
- **Carol** (`leoarraes.o@gmail.com`) — commita direto na `main` também. Por
  isso o fluxo é sempre `git fetch` antes de começar.
- **Sócios definidos em 20/08/2026:** Mateus, Leonardo e Gerusa, 25% cada.
  Os outros 25% são reservados para **recarga de crédito das APIs de IA**,
  não para um quarto sócio.

### Preços (curados à mão em `config/planos_assinatura.php`)

| Plano | Limite de alunos | Mensal |
|---|---|---|
| Custom | 50 | R$ 79,90 |
| Exclusive | 100 | R$ 149,90 |
| Plus | 150 | R$ 199,90 |
| Master | 250 | R$ 249,90 |
| Top 500 | 500 | R$ 399,90 |
| Top 1000 | 1.000 | R$ 499,00 |
| Top 2000 | 2.000 | R$ 689,00 |
| Top 2500 | 2.500 | R$ 725,00 |

Teste grátis de 7 dias, carência de 3 dias após vencimento. Cobrança recorrente
via **Mercado Pago** (preapproval).

---

## 2. Stack e arquitetura

| Camada | Tecnologia |
|---|---|
| Backend | Laravel 12 (LTS) + PHP 8.3 |
| Banco | MySQL |
| Frontend | Next.js 16.2 + React 19 + Tailwind 4 |
| IA | Anthropic Claude Haiku 4.5 (`claude-haiku-4-5-20251001`) via SDK oficial PHP |
| Auth do personal | JWT (`php-open-source-saver/jwt-auth`) |
| Push | Web Push (PWA) via `laravel-notification-channels/webpush` |
| Pagamento | Mercado Pago (assinatura recorrente) |
| Erros | Sentry (backend + frontend) |
| CI | GitHub Actions — PHPUnit contra **MySQL real**, não SQLite |

**Números atuais:** 55 migrations · 38 models · 33 controllers · 26 classes de
apoio (`app/Support/`) · **109 rotas** · 43 arquivos de teste · **196 testes
PHPUnit passando** · 23 páginas no frontend.

### Por que Laravel/MySQL (a decisão não foi técnica)

O app começou como protótipo em **Node/Express + PostgreSQL**. A equipe que vai
administrar a infra de produção (backup diário, hosting) definiu a stack
**Laravel 12 + PHP 8.3 + MySQL** para bater com o que eles operam. A migração
foi concluída: 13 route groups / 58 rotas do Node portados com paridade
funcional, incluindo os pipelines de IA. **O backend Node foi removido do
repositório** — hoje só existe `backend-laravel/`.

### Três decisões de arquitetura que valem entender

1. **Portal do aluno não tem login nem senha.** O personal cadastra o aluno, o
   sistema gera um token único e o link `/aluno/<token>` dá acesso direto.
   Padrão já validado em outro produto da Carol (AnamneseIA). Reduz fricção,
   mas veja "Riscos" adiante.

2. **Banco não é compartilhado com os sistemas de saúde da Carol**
   (AnamneseIA, TriagemAI, "Consultório na Nuvem"). Decisão explícita: dado de
   saúde e dado de aluno de academia são finalidades diferentes, e misturar
   aumenta o raio de impacto de um incidente. Integração futura, se houver,
   deve ser via API — nunca acesso direto à tabela de outro sistema.

3. **Dinheiro sempre em centavos inteiros** (`App\Support\Money`), nunca float.
   E todo histórico financeiro é imutável: mudar o valor de cobrança de um
   aluno **fecha** o registro vigente e **cria** um novo, para que o relatório
   de um mês passado continue refletindo o acordo daquele mês. Mesmo padrão em
   `student_billing_plans`, `professional_expenses`, `platform_costs` e
   `profit_shares`.

---

## 3. O que já funciona hoje

### Núcleo
- Cadastro/login do profissional (JWT), com rate limit
- Cadastro de aluno + link de convite; desvincular aluno
- **Biblioteca de 646 exercícios** (ver seção 4)
- Montagem e envio de treino (séries/reps/carga por exercício)
- **Modelos de treino** reaproveitáveis entre alunos
- Validade opcional do treino (em semanas) + arquivamento manual; o aluno
  escolhe entre treinos vigentes
- Portal do aluno: ver treino, iniciar sessão, registrar séries reais,
  retomar de onde parou, feedback pós-treino (RPE, satisfação, desconforto)
- Dashboard do profissional com sessões concluídas e alerta de aluno inativo

### Avaliação física e evolução
- **Anamnese inicial completa** (histórico de atividade, objetivos, condições
  de saúde, estilo de vida, motivação, disponibilidade, histórico familiar)
- Anamnese de revisão, que bloqueia o portal quando o treino vence
- Medidas corporais ao longo do tempo + gráfico de evolução de peso
- Fotos de evolução corporal

### Os 8 pipelines de IA
Todos com **humano no circuito** — a IA sugere, o personal aprova/edita antes
de virar treino de verdade.

| Pipeline | O que faz |
|---|---|
| Chat com Coach IA | Responde o aluno no lugar do personal quando o "piloto automático" daquele aluno está ligado |
| Consultor IA | Chat do personal sobre os próprios dados, via **5 ferramentas pré-definidas — nunca SQL livre** |
| Ideias de conteúdo | Sugestões de post usando `web_search` nativa |
| Evolução física | Compara fotos e comenta a evolução |
| Análise de academia | Lê fotos/vídeo da academia e identifica equipamentos |
| Recomendação de treino | Monta sugestão a partir dos equipamentos detectados |
| Análise de forma | Avalia execução a partir de vídeo (frames extraídos via ffmpeg) |
| Avaliação postural | 3 fotos (frente/lado/costas), comparação ângulo a ângulo — marcada como **opcional** por envolver exposição corporal maior |

Há um **kill-switch por pipeline** (`config/ia_pipelines.php`) para desligar
qualquer um sem deploy. Falha de IA nunca derruba a operação: a mensagem do
aluno é registrada mesmo se a IA falhar.

### Progressão de carga (deliberadamente SEM IA)
`App\Support\Progressao` sugere a carga da próxima sessão por **cálculo
determinístico**: bateu o topo da faixa em todas as séries → sobe o menor
incremento praticável daquele equipamento; ficou abaixo → mantém ou reduz.

O motivo de não usar IA aqui está documentado no código: progressão de carga é
matemática estabelecida, e o personal precisa **confiar no número**. Um LLM
seria mais caro, mais lento e menos previsível. O sistema só sugere — nunca
aplica sozinho.

### Modo offline (PWA)
- Service worker registrado ao abrir o portal
- Série registrada sem rede vai para fila em IndexedDB com UUID do cliente
- Idempotência garantida por coluna `session_entries.client_entry_id`
  (nullable + unique)
- Concluir o treino também entra na fila (senão a sessão ficaria presa em
  `in_progress`, quebrando streak/gamificação/progressão)

### Gamificação e engajamento
- Desafios entre alunos, ranking, medalhas, recordes pessoais (PR), streaks
- Check-ins
- **22 tipos de notificação push** em 4 categorias (hábito, celebração,
  informativo, gestão), com scheduler + fila (`database`, sem Redis) e dedupe
  por constraint UNIQUE. O personal liga/desliga cada tipo.

### Financeiro do personal ("Meu Negócio")
- Receita por aluno com histórico imutável
- Despesas recorrentes e avulsas
- Resultado líquido

### Assinatura do personal
- Planos, checkout e cobrança recorrente via Mercado Pago
- **Cancelamento** em `/plano`: cancela primeiro no Mercado Pago e só marca
  local como cancelada se isso der certo

### CRM interno do produto (`/admin`) — visão dos donos
- Faturamento, custo real de IA, custo de plataforma, lucro, margem, MRR
- Custo de IA **por pipeline** e **por personal** (quem está pesando na conta)
- Distribuição de assinantes por plano e por status, incluindo o balde
  "testou e nunca assinou" (público de follow-up comercial)
- Rateio de lucro entre sócios
- Acesso por `professionals.is_admin`; quem não é admin recebe **404, não 403**
  (não confirma nem que a rota existe)
- Gráficos em SVG puro, sem biblioteca nova, com paleta validada por script
  para daltonismo (protanopia/deuteranopia) e alternativa em tabela para todo
  gráfico

**Custo de IA:** cada uma das 11 chamadas Anthropic registra tokens e custo em
`ia_usage_logs`, com o custo **congelado em USD no momento da chamada** — mudar
a tabela de preços não reescreve o histórico.

---

## 4. A biblioteca de exercícios (trabalho de 20/08/2026)

Passou de **75 para 646 exercícios**, a pedido de personal trainers que
apontaram que 75 é pouco repertório para prescrever.

**Distribuição:** Pernas 79 · Costas 72 · Peito 66 · Core 64 · Funcional 59 ·
Ombros 58 · Glúteos 51 · Tríceps 48 · Bíceps 47 · Posterior 41 ·
Panturrilha 23 · Antebraço 22 · Trapézio 16.

### O ponto que mais importa para avaliar: imagens

- **75 exercícios têm foto real** (fonte wger.de, CC-BY-SA, com crédito)
- **571 não têm foto** — usam um fallback de animação em boneco-palito que já
  existia no produto para esse caso

**Por que não têm foto:** foi tentado puxar do wger.de, a mesma fonte dos 75
originais. O acervo inteiro deles tem apenas **66 traduções em português** e
**365 imagens no total** (para 860 exercícios). Não há como chegar a centenas de
exercícios em pt-BR por lá. Os 571 foram então escritos com a nomenclatura de
academia usada no Brasil, sem imagem.

**Mitigação existente:** o personal pode subir o próprio vídeo em qualquer
exercício (`exercise_media_overrides`), que substitui o padrão só para os alunos
dele.

**Isto é uma decisão de produto que merece avaliação externa:** a biblioteca
ganhou 8,6× mais repertório, mas a cobertura visual caiu de 100% para 12%.

### O fallback de animação
`frontend/lib/exercisePatterns.ts` mapeia cada exercício para um de 23 padrões
de movimento (agachamento, rosca, supino, prancha…) a partir do nome e do grupo
muscular. Os 646 foram verificados: **nenhum cai no padrão genérico**.

---

## 5. Histórico de decisões e bugs relevantes

Esta seção existe porque vários bugs encontrados foram **de comportamento
silencioso** — passavam em teste, não davam erro, e só apareceriam como número
errado na tela do usuário.

### Bugs silenciosos já corrigidos (amostra representativa)

- **`MedalhaConquistadaRule` usava `havingRaw()` sem `groupBy()`** — passava no
  MySQL, zerava no SQLite. A notificação de medalha **nunca disparava de
  verdade em produção**.
- **`professional_id` sempre nulo em 6 dos 8 pipelines de IA** — todos rodam a
  partir do portal do aluno (autenticado por token, nunca por JWT), então o
  fallback que lia `request()->user()` não resolvia nada. O custo total estava
  certo, mas a atribuição por profissional ficava vazia (75% do volume).
- **Resumo de assinantes não fechava com o total de contas** — quem passou do
  teste grátis e nunca assinou ficava invisível. Era justamente o público mais
  relevante para follow-up comercial.
- **Campo "Reps" era preenchido com a prescrição crua** ("10-12"). Como o input
  é `type=number`, renderizava vazio e gravava `reps_done = NULL`. Nota no
  código: **nunca** preencher com o topo da faixa — isso registraria sozinho
  que o aluno bateu a meta em toda série, que é exatamente o gatilho da
  sugestão de aumento de carga.
- **Fuso horário:** coluna `date` serializada como ISO completo fazia
  `new Date(iso).toLocaleDateString()` mostrar o dia anterior em UTC-3.
- **Sintaxe exclusiva de PostgreSQL** que sobreviveu à migração
  (`ON CONFLICT`, `NULLS FIRST`, `generate_series`, `FILTER (WHERE…)`) — uma
  delas quebrava **o caminho feliz inteiro** da análise de forma.

### Bugs encontrados no trabalho de 20/08/2026

- **`GraficoBarras` usava o rótulo como `key` do React.** Inofensivo enquanto os
  rótulos vinham de enums; passou a poder colidir quando o gráfico novo usa
  **nome de personal** como rótulo (dois personais homônimos).
- **Progressão sugeriria "+2,5 kg" num elástico.** São 41 exercícios com
  elástico e 25 com kettlebell entre os novos; todos cairiam no incremento
  padrão. Agora elástico/TRX/bola/corda/cardio têm incremento zero (a sugestão
  vira "mais uma repetição") e kettlebell sobe de 4 em 4 kg. **Há um teste que
  falha se alguém adicionar equipamento novo sem mapear.**
- **Bug pré-existente no fallback de animação:** a regra `quadril` disparava
  antes de `abducao`, então *toda* abdução de quadril virava animação de hip
  thrust. Os 75 originais escapavam apenas por estarem numa lista de exceções.
  Mesmo problema em "Tríceps coice" e "Panturrilha no hack machine".

### Decisões de produto documentadas no código

- **Consultor IA nunca recebe SQL livre** — só 5 ferramentas pré-definidas.
- **`temperature: 0.0` no Consultor IA** + regras explícitas contra inventar
  dado quando a ferramenta retorna vazio (houve uma alucinação real observada:
  o modelo inventou um ranking de alunos com a ferramenta devolvendo lista
  vazia; não foi reproduzível, mas motivou o guard).
- **Notificação nunca leva dado financeiro em texto claro** na tela de bloqueio.
- **Cotação USD→BRL é fixa em `.env`**, não buscada em API externa: uma
  dependência de rede no meio do dashboard financeiro quebraria a tela quando a
  API caísse.

---

## 6. Riscos e lacunas conhecidas

> Esta é a seção mais relevante para uma avaliação crítica. Nada aqui está
> escondido no código — está tudo documentado, mas continua em aberto.

### Produto

1. **88% da biblioteca de exercícios não tem imagem** (seção 4). O fallback
   animado funciona, mas é um boneco-palito — não é uma foto de execução. Para
   um produto vendido a personal trainers, isso pode ser percebido como
   inacabado. **Não há solução barata identificada:** nenhuma fonte livre em
   português com esse volume foi encontrada.
2. **Strava foi tirado da interface** (commit `c3f162b`, "stand by") — a
   integração existe no backend mas não tem credenciais reais.
3. **Periodização de treino, marca branca e app mobile nativo** continuam fora
   de escopo.

### Técnico

4. **Nada foi verificado em produção de verdade.** Todo o teste visual foi em
   ambiente local. Não há deploy definido para o backend PHP (o padrão usado
   em outro projeto da Carol era Railway, mas específico de Node).
5. **A otimização de animação da biblioteca não pôde ser verificada ao vivo.**
   O painel de navegador usado roda oculto (`visibilityState: hidden`), o que
   deixa `IntersectionObserver`, `requestAnimationFrame` e a timeline SVG
   inertes. A implementação foi feita para **falhar animando** (comportamento
   anterior) em vez de congelar — e *isso* foi verificado. Ainda assim, merece
   um olhar humano na tela de montar treino.
6. **A tela de montar treino renderiza os 646 exercícios de uma vez** (~8.900
   nós de DOM). Há busca por nome, mas não há virtualização nem paginação.
   O payload da biblioteca é de 226 KB.
7. **`IA_COTACAO_USD_BRL` é ajustada à mão** — se o câmbio andar, o CRM mostra
   custo de IA defasado.
8. **Preços de IA cadastrados só para o Haiku 4.5.** Trocar de modelo exige
   entrada nova em `config/ia_precos.php` (o CRM avisa na tela quando encontra
   modelo sem preço, em vez de estimar errado).
9. **Sócios cadastrados apenas no banco local** — precisam ser recadastrados em
   produção pela tela `/admin` → "Divisão".
10. **3 arquivos de teste E2E (Playwright) estão sem commit desde 27/07** — são
    anteriores ao redesign visual e os seletores provavelmente quebraram.
11. **Endpoint `/admin/uso-ia`** (lista crua de chamadas de IA, para investigar
    um pico de custo) existe no backend mas **não tem tela**.

### Segurança e conformidade

12. **O portal do aluno é autenticado só por token na URL.** É uma decisão
    consciente (fricção zero para o aluno) já validada em outro produto, mas:
    quem tiver o link tem acesso aos dados de treino, medidas corporais, fotos
    de evolução e avaliação postural daquele aluno. **Não houve avaliação
    formal de LGPD**, e o app trata dado sensível de saúde (anamnese,
    condições de saúde, fotos corporais).
13. **Não houve pentest nem revisão de segurança externa.** Houve varreduras de
    bugs feitas pela própria IA (segurança de upload, race conditions, rate
    limiting, vazamento de exceção crua), mas isso não substitui revisão
    independente.

---

## 7. Como rodar

```bash
# backend (porta 3003)
cd backend-laravel && composer install && php artisan migrate --seed && php artisan serve --port=3003
```

```bash
# frontend (porta 3101)
cd frontend && npm install && npm run dev
```

Variáveis essenciais no `.env` do backend: `DB_*` (MySQL), `ANTHROPIC_API_KEY`,
`JWT_SECRET`, `FRONTEND_URL`, `IA_COTACAO_USD_BRL`, credenciais do Mercado Pago
e chaves VAPID do Web Push.

**Armadilha recorrente:** depois de trocar de branch ou puxar a `main`, rodar
`php artisan migrate` antes de testar — migrations não rodam sozinhas, e isso
já quase quebrou uma apresentação ao vivo.

---

## 8. O que eu pediria a quem for avaliar

Sugestões de foco, considerando onde o projeto está:

1. **A troca "8,6× mais repertório × 88% sem foto" foi a decisão certa?**
   Existe alternativa que eu não considerei? (as descartadas foram: wger.de —
   insuficiente em pt-BR; e ficar em 75 exercícios)
2. **O portal do aluno sem autenticação real é aceitável** dado que o app
   guarda anamnese de saúde, fotos corporais e avaliação postural?
3. **O produto está pronto para cobrar R$ 79,90–725,00/mês?** O que falta para
   ser vendável — e o que é excesso de escopo para o estágio atual?
4. **A precificação faz sentido** frente ao custo real (o CRM mostra custo de
   IA por personal, então esse número é mensurável)?
5. **Há algo obviamente ausente** no fluxo de um personal trainer real que 8
   pipelines de IA e 646 exercícios não compensam?
