# Pedido de revisão: sistema de notificações push do Clube Mais Personal

Este documento resume uma feature que acabou de ser implementada num app real, pra você (Claude, sem acesso ao código) avaliar e apontar problemas, riscos ou pontos de melhoria — antes de um PR ser aberto. Não preciso que você reescreva nada, só uma avaliação honesta: o que está bom, o que está arriscado, o que você mudaria.

## Contexto do produto

**Clube Mais Personal** é um app brasileiro (PWA, instalável na tela inicial) pra personal trainers gerenciarem alunos e treinos. Público de alunos vai de **~16 a 70+ anos** — um detalhe importante pra avaliar tom de comunicação. Existe um "piloto automático" de IA (Coach IA) que responde o aluno no chat quando ligado pelo personal, com tom curto e motivador, e regra explícita de nunca usar linguagem de culpa/pressão.

Stack: backend Laravel 12 + MySQL, frontend Next.js (App Router) + React. Fila via `QUEUE_CONNECTION=database`, sem Redis. Projeto ainda não foi implantado em produção — só roda localmente.

## O pedido original (resumo)

Implementar notificações push (Web Push via PWA) com:
- Vários **tipos** de notificação, cada um com sua própria regra de disparo.
- Personal poder **ligar/desligar cada tipo**, hoje globalmente, com a estrutura já pronta pra ser por-aluno no futuro.
- Pedido de permissão de notificação **só depois que o app detectar que está instalado** na tela inicial (não numa aba comum do navegador — no iOS, push só funciona assim mesmo).
- Nada síncrono numa request HTTP: tudo via job em fila.
- Idempotência: rodar a regra duas vezes não pode duplicar o envio.
- Aviso in-app (banner, não-bloqueante) pra quem ainda não instalou o app.

## Arquitetura implementada

- **Scheduler único**: um comando artisan (`notifications:process`) roda a cada 15 minutos. Não há uma entrada de scheduler por tipo — cada tipo é uma "Rule class" pequena que decide sozinha sua própria janela de tempo (ex: a regra de "toda sexta" só retorna candidatos se `hoje->isFriday()`).
- **20 Rule classes**, uma por tipo, cada uma implementando `avaliar(): array<Candidato>` — consulta o banco e devolve quem deveria receber o quê.
- **Dedup**: cada candidato carrega uma `dedupKey` (string) que a própria regra monta (ex: `sem_treinar_dias:{aluno}:{limiar}:{data}` ou `medalha_conquistada:{aluno}:{badge_id}`, sem data quando é um marco único-na-vida). O comando tenta criar uma linha de log com essa chave como UNIQUE; se colidir, já foi enviado, pula. Não há verificação de duplicidade separada — a garantia é 100% a constraint UNIQUE do banco.
- **Fila**: cada envio aprovado despacha uma `Notification` do Laravel que implementa `ShouldQueue` — o envio HTTP de verdade pro serviço de push roda no worker da fila, nunca dentro do comando do scheduler.
- **Preferência (toggle)**: tabela com `professional_id`, `tipo_chave`, `student_id` (nullable — null = preferência global). Hoje só se escreve com `student_id` null. Sem constraint UNIQUE no banco pra essa combinação (documentei que MySQL trata NULL como distinto em índice único, então a unicidade é garantida só pela aplicação usando `updateOrCreate`, não pelo schema).

## Catálogo completo dos 20 tipos

**Hábito e engajamento (aluno):**
1. `sem_treinar_hoje` — não treinou até um horário configurável (padrão 18h)
2. `sem_treinar_dias` — cruza exatamente 3, 7 ou 14 dias sem treinar (uma regra só, tom cresce com o limiar)
3. `streak_em_risco` — sequência ativa de dias treinando, ainda não treinou hoje, sequência ≥3 dias
4. `alerta_sexta` — toda sexta, mensagem de moderação pro fim de semana
5. `parabens_fim_semana` — treinou ≥3x nos últimos 7 dias, aviso toda segunda
6. `onboarding_boas_vindas` — sequência dia 1/3/7 pra aluno novo; dia 1 é pra todo mundo, dia 3 e 7 só se ainda tiver menos de 3 treinos concluídos

**Celebração e gamificação (aluno):**
7. `novo_recorde_pessoal` — bateu PR de carga num exercício
8. `medalha_conquistada` — cobre 5 badges (primeiro treino, 10/30 treinos, sequência 7/30 dias) numa regra só
9. `mudanca_ranking_desafio` — sobe/desce de posição no leaderboard de um desafio ativo
10. `desafio_terminando` — 48h antes do fim de um desafio que participa
11. `marco_tempo_treinando` — 1, 3, 6 ou 12 meses desde o cadastro

**Informativo/operacional (aluno):**
12. `novo_treino_enviado` — personal enviou/reenviou o treino
13. `treino_academia_aprovado` — personal aprovou a recomendação da análise de academia
14. `mensagem_nao_lida` — mensagem do personal/Coach IA sem abrir há N horas (padrão 4h)
15. `avaliacao_pendente` — 30+ dias sem atualizar medidas corporais
16. `estagnacao_detectada` — carga de um exercício não melhorou entre as duas últimas sessões

**Gestão (personal):**
17. `resumo_semanal_risco` — toda segunda, quantos alunos estão em risco de abandono
18. `avaliacao_recebida` — aluno completou o PAR-Q pela primeira vez
19. `revisao_pendente` — análise de academia esperando aprovação (nudge 1x/dia enquanto pendente)
20. `mensagem_sem_resposta` — última mensagem da conversa é do aluno e está sem resposta há N horas (padrão 12h)

## Decisões/tradeoffs que valem seu olhar crítico

1. **`mensagem_nao_lida` vs `mensagem_sem_resposta`**: são definidos de formas diferentes de propósito. O primeiro (lado do aluno) usa uma coluna `read_at` nova, marcada quando o aluno abre o chat. O segundo (lado do personal) **não** usa leitura — usa "a mensagem mais recente da conversa é do aluno, sem nenhuma resposta depois". Motivo: não existe hoje (no backend Laravel) um endpoint do personal lendo mensagens do aluno pra marcar como lida — só existe do lado do Node antigo, gap de paridade pré-existente que ficou fora de escopo. É uma solução funcional, mas assimétrica — vale question se isso é aceitável ou se deveria ter resolvido o gap de paridade primeiro.
2. **`revisao_pendente` só cobre análise de academia**, não análise de forma — porque só a de academia tem fluxo de aprovação no código hoje (a de forma manda feedback direto pro aluno). Fiz essa escolha porque adicionar aprovação pra análise de forma seria mudança de escopo maior (mudaria o fluxo do produto, não só notificação).
3. **`mudanca_ranking_desafio`** depende de uma tabela nova (`challenge_rank_snapshots`) só pra guardar "qual era a posição da última vez que eu chequei", porque o ranking do desafio é sempre calculado na hora, nunca persistido. Isso significa mais uma tabela no banco só pra suportar uma notificação — vale avaliar se o ganho compensa a complexidade.
4. **Cadência única de 15 minutos pra tudo**: em vez de configurar horários de scheduler diferentes por tipo (ex: `resumo_semanal_risco` só precisa rodar 1x por semana), tudo roda no mesmo tick de 15 min e cada regra decide internamente se "é a hora". Mais simples de manter, mas o comando sempre avalia as 20 regras mesmo quando 19 delas vão retornar vazio na maioria das execuções — pode não escalar bem se a base de alunos crescer muito (hoje o app tem só alguns alunos de teste).
5. **Gate de instalação**: o botão de ativar notificações e o request de permissão só aparecem se `matchMedia('(display-mode: standalone)')` for true. Isso significa que em desktop (Chrome/Edge), que suporta push sem precisar "instalar" formalmente, a feature fica escondida também — pode ser mais restritivo do que o necessário fora do contexto do iPhone.
6. **Sem teste em dispositivo real ainda**: tudo foi validado via testes automatizados (PHPUnit) e simulação de navegador; a entrega real de push num iPhone de verdade (o requisito mais crítico, já que é o público majoritário) ainda não foi confirmada.
7. **Sem rate-limit/verificação de abuso** nos endpoints `POST /push/subscribe` e `POST /portal/{token}/push/subscribe` — qualquer requisição com um `invite_token` válido pode registrar uma subscription. Não é diferente do resto do padrão de auth por token já usado no portal do aluno, mas vale um olhar de segurança.

## O que eu quero saber de você

- Alguma **falha de lógica real** em algum dos 20 tipos (ex: uma condição que nunca vai disparar, ou que vai disparar demais)?
- O **tom de voz** das notificações (principalmente as de "sem treinar") está adequado pro público de 16 a 70+ anos, ou parece pressão/culpa disfarçada?
- A decisão de **não notificar em desktop** faz sentido, ou é restritivo demais?
- Tem algum **tipo de notificação faltando** que seria óbvio pra esse tipo de app (personal trainer + alunos)?
- Algum **risco de segurança ou de privacidade** que passou batido?
- A arquitetura (Scheduler único + Rule classes + dedup por UNIQUE key) é razoável pra esse porte de projeto, ou é over-engineering / under-engineering?
