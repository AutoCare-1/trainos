# Segunda rodada de revisão: notificações push do Clube Mais Personal

Isto é uma continuação de uma revisão anterior (documento irmão:
`REVISAO_NOTIFICACOES_PUSH.md`, já respondido item a item). Este documento
resume **o que mudou desde então** e pede uma nova avaliação crítica — quero
saber se as correções resolveram de verdade os problemas, se alguma abriu um
problema novo, e se ainda falta algo importante antes do PR.

## Contexto do produto (recap)

**Clube Mais Personal**: PWA de personal trainer, público de alunos de ~16 a
70+ anos. Backend Laravel 12 + MySQL, frontend Next.js. Projeto ainda não
implantado em produção. Sistema de notificações push: 22 tipos, cada um numa
"Rule class" que decide sozinha sua janela de tempo, avaliadas a cada 15min
via Laravel Scheduler, envio real via fila (nunca síncrono).

## O que a primeira revisão encontrou, e o que mudou

**Correções aplicadas** (arquitetura nova ou schema novo):

1. **Gate de instalação era global demais.** O botão de ativar notificações
   só aparecia com o app rodando "instalado" (`display-mode: standalone`) —
   correto pro iOS, mas também escondia a feature em desktop, onde o
   personal realmente usa o painel. Corrigido: o gate de "precisa estar
   instalado" agora só vale detectando iPhone/iPad/iPod por user agent;
   qualquer outra plataforma libera o botão numa aba comum.
2. **Sem coordenação entre as 22 regras — risco de empilhar notificação no
   mesmo dia.** Criada uma camada nova, `App\Support\CoordenadorNotificacoes`,
   que roda depois de coletar os candidatos de todas as regras e antes de
   despachar qualquer job:
   - **Supressão por pares**: regras que competem pelo "mesmo motivo" — hoje
     só dois pares mapeados manualmente (`onboarding_boas_vindas` suprime
     `sem_treinar_dias`/`sem_treinar_hoje`; `streak_em_risco` suprime
     `sem_treinar_hoje`).
   - **Limite de 2 notificações por dia por destinatário**, escolhendo por
     uma tabela de prioridade fixa (5 tiers, do "acionável" ao
     "administrativo"), contando o que já foi enviado em execuções
     anteriores do Scheduler no mesmo dia (não só no ciclo atual — senão o
     limite "vaza" entre execuções de 15 em 15 min).
3. **`sem_treinar_hoje` podia cobrar no mesmo dia em que o treino foi
   prescrito.** Corrigido: só considera candidatos com treino enviado antes
   de hoje.
4. **`avaliacao_pendente` podia reenviar com irregularidade no
   fim/início de mês** (dedup era por "ano-mês", não por dias corridos).
   Trocado por uma janela rolante de 15 dias a partir de quando o aluno
   cruza o limiar.
5. **Risco de "veredito de estagnação" divergente entre o push e o alerta
   in-app já existente.** Consolidados numa fonte única,
   `App\Support\Estagnacao::compararUltimasDuasSessoes()`, usada tanto pelo
   endpoint `GET /alunos/:id` (perfil visto pelo personal) quanto pela regra
   `estagnacao_detectada`. **Decisão consciente que ficou em aberto**: a
   revisão também sugeriu ampliar a janela de comparação de 2 para 3-4
   sessões (reduzir falso positivo de "um dia de cansaço"); não fiz isso,
   porque mudar só o lado do push criaria exatamente a divergência que a
   consolidação resolveu — mudar os dois juntos é decisão de produto (muda
   um alerta in-app já em uso), não bug. **Pergunta em aberto pra essa
   revisão**: vale a pena ampliar a janela dos dois lados juntos?
6. **Payload de push podia expor dado sensível na tela de bloqueio.** 8 dos
   22 tipos (os que mencionam frequência de treino, saúde/medidas ou nome de
   aluno) agora mandam um título/corpo genérico no push
   ("Você tem uma novidade no app. Toque para ver.") — o detalhe completo só
   aparece depois de abrir o app pelo link da notificação. Os outros 14
   tipos (celebração, operacional sem dado sensível) continuam com texto
   rico.
7. **Sem rate limit nos endpoints de subscribe.** `throttle:10,1` adicionado
   em `POST /push/subscribe` e `POST /portal/{token}/push/subscribe`.
8. **Preferência de notificação sem garantia real no banco.** A combinação
   "preferência global" (`student_id` null) dependia só de `updateOrCreate()`
   na aplicação pra não duplicar — MySQL trata NULL como distinto em índice
   único. Adicionada uma coluna gerada (`VIRTUAL`, com um sentinela fixo no
   lugar de NULL) + constraint UNIQUE de verdade no banco.
9. **Tabela `challenge_rank_snapshots` criada só pra guardar "último rank
   notificado".** Removida — essa informação virou uma coluna a mais em
   `challenge_participants` (a tabela de participação aluno-desafio que já
   existia).
10. **2 tipos de notificação novos**, reaproveitando a mesma infraestrutura:
    `comentario_foto_evolucao` (Coach IA comenta foto de evolução do aluno —
    feature que já existia sem gerar push) e `aluno_cadastrado` (aviso pro
    personal quando cadastra um aluno novo).

**Achados que investiguei e decidi NÃO mexer** (documentados, não
ignorados):

- `novo_recorde_pessoal` já lia direto de uma única fonte
  (`session_entries.is_pr`, calculada uma vez em
  `PortalController::registrarSerie`) — nunca existiu duplicação aqui.
- `novo_treino_enviado` só dispara com `workouts.sent_at` preenchido, que só
  é escrito em `TreinoController::enviar()` — nunca em rascunho. Já era o
  gatilho inequívoco que a revisão pedia.
- Limpeza de subscriptions expiradas (HTTP 404/410) já é feita
  automaticamente pelo pacote `laravel-notification-channels/webpush`
  (`ReportHandler::handleReport` chama `$subscription->delete()` quando a
  resposta indica subscription expirada) — confirmado lendo o código do
  pacote, não precisei escrever nada.
- O app **não tem conceito de "dia de descanso programado"** — não existe
  agenda semanal nem campo de dias de treino no schema, só um treino ativo
  que vale até o personal trocar. Não dá pra saber "hoje era dia de treino
  ou descanso" com o schema atual. O que corrigi (item 3 acima) foi o caso
  relacionado que realmente existia: não cobrar no dia em que o treino foi
  prescrito.

**Descoberta fora de escopo, não mexida**: `App\Support\ConteudoAgregados`
(feature de "ideias de conteúdo" pro personal, não relacionada a push) tem
sua própria detecção de PR independente, via SQL com window function —
diferente da fonte usada pelo push/in-app. Não é o par que a revisão pediu
pra investigar (essa pedia especificamente push vs. in-app do próprio
aluno), então não toquei, só registrei.

## Estado técnico atual

- 40 testes de feature (17 novos nesta rodada), todos passando, rodando
  contra SQLite (suíte de testes) — as regras evitam funções de data
  exclusivas do MySQL (`datediff`, `date_add`) de propósito, calculando
  limites de data em PHP/Carbon, pra funcionar nos dois motores.
- `CoordenadorNotificacoes` tem uma tabela de prioridade fixa (constante no
  código, 5 tiers) e 2 pares de supressão hard-coded — não é configurável
  via banco/admin hoje.
- Limite diário fixo em 2 por destinatário, também hard-coded
  (`CoordenadorNotificacoes::LIMITE_DIARIO_POR_DESTINATARIO`).
- Ainda **não testado em dispositivo real** (iPhone) — só verificado via
  testes automatizados e inspeção de código/navegador simulado.

## O que eu quero saber desta rodada

- As correções resolvem os problemas apontados de verdade, ou alguma é só
  aparência de correção?
- A supressão por pares hard-coded (`onboarding_boas_vindas` vs
  `sem_treinar_*`; `streak_em_risco` vs `sem_treinar_hoje`) cobre os casos
  reais de colisão, ou tem mais pares óbvios entre os 22 tipos que eu não
  vi?
- O limite fixo de 2/dia por destinatário é razoável, ou deveria ser
  diferente pro personal (que pode legitimamente precisar de mais avisos
  de gestão) vs. pro aluno?
- A tabela de prioridade de 5 tiers faz sentido, ou tem tipo em tier errado
  (ex: algo tratado como "baixa prioridade" que na real deveria nunca ser
  suprimido)?
- Alguma das minhas decisões de "não mexer" (estagnação continuar em 2
  sessões; ConteudoAgregados fora de escopo) foi a escolha errada?
- Algum problema novo, não relacionado à primeira revisão, que apareceu
  olhando o sistema como um todo agora?
