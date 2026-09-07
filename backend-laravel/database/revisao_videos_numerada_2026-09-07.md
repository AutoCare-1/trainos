# Revisão dos vídeos de demonstração — rodada da lista numerada (07/09/2026)

Motivo: o personal reclamou de vídeos com **nome errado** (mídia boa, rótulo
trocado). Revisão feita por mim, grupo a grupo, pelo método das tiras de 5
frames (`ffmpeg select` + montagem PIL, 6 exercícios por imagem). Os números
são os de `biblioteca_numerada.md` / da tela `/videos`.

Escala: **GRAVE** = mostra outro exercício · **MÉDIO** = exercício certo, mas a
variação do nome não aparece (ângulo de banco, pegada) · resto = OK.

Regerar qualquer um depende de aprovação de gasto Higgsfield. Método de
regeração em `RETOMAR_demonstracoes.md` / memória `feedback_trainos_video_higgsfield`.

## Grupos revisados

| Grupo | Faixa | Revisado | Achados |
|---|---|:--:|---|
| Ombros | #113–153 | ✅ | 1 grave, 3 médios |
| Peito | #1–52 | ✅ | 0 grave, 4 médios (todos "declinado" saindo plano/inclinado) |
| Costas | #53–104 | ✅ | 1 grave (#70), resto OK |
| Bíceps | #146–172 | ✅ | 0 — grupo limpo |
| Tríceps | #173–205 | ✅ | 0 — grupo limpo |
| Antebraço | #206–212 | ✅ | 0 — grupo limpo |
| Trapézio | #213–222 | ✅ | 0 — grupo limpo |
| Posterior | #275–300 | ✅ | 0 — grupo limpo |
| Glúteos | #301–333 | ✅ | 0 — grupo limpo |
| Panturrilha | #334–348 | ✅ | 0 — grupo limpo |
| Pernas | #223–274 | ✅ | 0 grave; 2 médios (#249, #250) — não regerados |
| Core | #349–395 | ✅ | 0 — grupo limpo |
| Funcional | #396–437 | ✅ | #416 regerado e instalado; #406 mantido |
| Esportivo | #438–511 | ✅ | 0 — grupo limpo (74 vídeos) |
| Ativação | #512–533 | ✅ | 0 — grupo limpo |
| Mobilidade | #534–576 | ✅ | 0 — grupo limpo |
| Equilíbrio | #577–600 | ✅ | 0 — grupo limpo |
| Prevenção | #601–630 | ✅ | 0 — grupo limpo |
| Alongamento | #631–676 | ✅ | 0 — grupo limpo |


## Ombros

**GRAVE**
- **#127 Elevação frontal com barra** — o vídeo é uma **remada alta** (barra
  puxada até o queixo, cotovelos altos). Elevação frontal é braço reto à frente
  até a linha do ombro.

**MÉDIO**
- **#119 Desenvolvimento por trás da nuca** — não dá pra ver a barra passar
  atrás da nuca; lê como desenvolvimento à frente.
- **#125 Elevação frontal alternada** — cotovelo bem dobrado, lembra rosca.
- **#135 Elevação lateral unilateral na polia** — a mão sobe perto do rosto,
  parece puxada alta em vez de elevação lateral.

Todos os 13 "Desenvolvimento" e os press militares: **OK** (press acima da
cabeça de verdade, equipamento certo).

## Peito

Sem nenhum "exercício trocado". O único padrão de erro é **banco de supino
declinado saindo plano ou inclinado**:

**MÉDIO**
- **#38 Supino declinado com halteres** — banco claramente **inclinado**
  (encosto levantado). O mais evidente do grupo.
- **#37 Supino declinado** — banco não parece declinado (lê como plano).
- **#39 Supino declinado na máquina** — é um chest press sentado horizontal,
  sem nada de declínio.
- **#7 Crucifixo declinado com halteres** — banco inclinado, não declinado.

OK de referência no mesmo tema: **#40 Supino declinado no smith** saiu com o
banco declinado certo (joelhos por cima, cabeça baixa) — serve de alvo pro
prompt dos outros.

Sem vídeo (não são erro, são limite do gerador já documentado): #11 Crucifixo
na polia deitado no banco, #28 Paralelas nas argolas.

## Costas

**GRAVE**
- **#70 Pull-up negativa** — o vídeo é um **desenvolvimento / push press com barra
  em pé** (barra do ombro pra cima da cabeça). Pull-up negativa é a descida lenta
  da barra fixa. Exatamente o tipo de erro que o personal descreveu ("virou
  desenvolvimento e não era pra ser").

Resto do grupo **OK**: todas as barras fixas (#53–58), puxadas (#71–82),
remadas (#83–103), terras (#66–69), face pulls (#62–64), Superman (#104). A
`#99 Remada nas argolas com pés elevados` — que foi GRAVE no piloto de 31/08
("em pé puxando") — aqui está com o corpo na horizontal, corrigida.

## Bíceps e Tríceps

Os dois grupos **limpos** — 27 + 32 vídeos, nenhum exercício trocado, nenhuma
variação errada digna de nota. Rosca (direta/martelo/scott/spider/concentrada/
bayesiana/inversa), extensão, mergulho, testa, francês, coice, pushdown: todos
batem com o nome.

## Regeração (07/09, ~36 créditos Higgsfield, seedance_2_0_mini 480p)

9 vídeos gerados: os 5 sem dica + os 4 que já tinham dica boa (re-roll). Método
em [[feedback_trainos_video_higgsfield]]. Resultado conferido por tira de frames:

| # | Exercício | Antes | Depois | Ação |
|---|---|---|---|---|
| #70 | Pull-up negativa | GRAVE (desenv. c/ barra em pé) | **corrigido** — pendurado na barra | instalado |
| #127 | Elevação frontal com barra | GRAVE (remada alta) | **corrigido** — braço reto à frente | instalado |
| #38 | Supino declinado com halteres | MÉDIO (banco inclinado) | **corrigido** — banco declinado | instalado |
| #125 | Elevação frontal alternada | MÉDIO (parecia rosca) | melhorou — braço mais reto | instalado |
| #135 | Elevação lateral unilateral na polia | MÉDIO (puxada alta) | melhorou — braço abre pro lado | instalado |
| #119 | Desenvolvimento por trás da nuca | MÉDIO | leve melhora, ainda limítrofe | instalado |
| #7 | Crucifixo declinado com halteres | MÉDIO (inclinado) | arco ok, declínio fraco | instalado (plano > inclinado) |
| #37 | Supino declinado | MÉDIO (plano) | sem melhora | **original mantido** |
| #39 | Supino declinado na máquina | MÉDIO (press horizontal) | marginal | **original mantido** |

7 instalados em `public/uploads/exercise-demos/`, originais em
`_backup-2026-09-07/`. Os `.mp4` seguem fora do git — Carol/produção só recebem
via R2 (pendência antiga). `#37` e `#39` ficam pra re-roll futuro; o alvo é
`#40 Supino declinado no smith`, que saiu com o declínio certo.

## Antebraço, Trapézio, Posterior, Glúteos, Panturrilha

**91 vídeos, todos OK.** Nenhum exercício trocado, nenhuma variação errada
digna de nota. Inclui a família de risco histórico — `#277 Cadeira flexora` e
`#278 cadeira flexora unilateral` (que saíam como extensora nas auditorias de
agosto) aqui estão como leg curl de verdade; e as remadas/terras/pontes/
abduções todas batem com o nome. Nada a regerar nesta rodada.

## Pernas

**52 vídeos, praticamente limpo.** A família de risco histórico — leg press
(#260–268, 42% de erro grave na auditoria de agosto) — aqui está toda **certa**:
são leg press de verdade, sem virar cadeira extensora. Afundos, agachamentos
(livre/frontal/hack/búlgaro/sumô/pistol/cossaco), passadas e step-ups todos
batem com o nome.

Dois médios, nenhum é exercício trocado, **não regerados** (baixa chance):
- **#249 Agachamento sissy** — sai como agachamento raso comum, sem a inclinação
  do tronco pra trás. Já era limite confirmado do gerador nas 3 tentativas de
  agosto (`RETOMAR_demonstracoes.md`).
- **#250 Agachamento sumô com halter** — o peso parece barra e não halter; a
  base ampla e o movimento estão certos.

## Core e Funcional

**Core (47): limpo.** Pranchas (todas as variações), abdominais, L-sit, dragon
flag, hollow, ab wheel, pallof, woodchop, russian twist — todos batem.

**Funcional (42, 40 com vídeo): 2 médios, regerando.**
- **#406 Caminhada inclinada na esteira** — saía correndo, e a esteira não
  parecia inclinada. Ganhou `cena` em inglês negando a corrida.
- **#416 Deslocamento lateral (skater)** — saía correndo pra frente em vez de
  saltar de lado. Ganhou `cena` descrevendo o salto lateral de patinador.
Sem vídeo (não são erro): #399 Back lever progressivo, #423 Muscle-up nas argolas.

### Regeração Funcional (07/09, 8 créditos)
- **#416 Deslocamento lateral (skater)** — regerado: agora salta de lado em base
  atlética, não corre pra frente. **Instalado.**
- **#406 Caminhada inclinada na esteira** — regeração ainda saiu correndo em
  esteira plana. **Original mantido.** É o mesmo tipo de limite do gerador que
  "andar pra trás de frente pra câmera" — a distinção andar×correr não pega.

## Esportivo

**74 vídeos (69 com vídeo), todos OK.** Era o grupo de maior risco — gesto
esportivo específico que o gerador nunca tinha feito antes da leva complementar
(tênis, corrida educativa, futebol, natação, luta, vôlei, ciclismo, golfe,
escalada, surf). Nenhum exercício trocado. O método de escrever a cena da
família ANTES de gerar (piloto de 31/08) rendeu aqui. Nada a regerar.

Sem vídeo (não são erro): #454, #464, #484, #488, #501.

## Ativação, Mobilidade, Equilíbrio, Prevenção, Alongamento

**165 vídeos, todos OK.** Nenhum exercício trocado. Rolo/liberação miofascial,
apoio unipodal, bosu, prancha de Copenhague, mobilizações neurais,
alongamentos passivos — todos batem com o nome.

---

# FIM DA REVISÃO — 19 grupos, 663 vídeos, 100% conferidos

## Resultado

| | quantos | detalhe |
|---|--:|---|
| **GRAVE** (mostrava outro exercício) | 3 | #70 Pull-up negativa, #127 Elevação frontal com barra, #416 Deslocamento lateral (skater) — **todos regerados e corrigidos** |
| **MÉDIO** regerado e melhorado | 5 | #38, #125, #135, #119, #7 |
| **MÉDIO** sem melhora na regeração, original mantido | 3 | #37, #39 (supino declinado / na máquina), #406 (caminhada inclinada — sai correndo) |
| **MÉDIO menor** não regerado (não é rótulo trocado) | ~5 | #249 sissy, #250 sumô, #530, #604 … |
| **OK** | ~647 | — |

**Taxa de erro real: ~1,6%** (11 vídeos precisando de ação em 663).

## Custo

12 vídeos regerados em 2 rodadas pelo MCP Higgsfield (`seedance_2_0_mini`
480p), **~48 créditos** (saldo ~1.262). 9 instalados, 3 originais mantidos.

## O que fica pra depois

- **#37 e #39 (supino declinado)** — re-roll futuro com prompt inspirado no
  `#40 Supino declinado no smith`, que saiu com o declínio certo.
- **#406 Caminhada inclinada na esteira** — limite do gerador (andar × correr).
- **`.mp4` fora do git** — os 9 regerados só existem no disco local + em
  `_backup-2026-09-07/`. Carol / produção só recebem via R2 (pendência antiga).
- **13/14 exercícios sem vídeo** continuam sem — limite confirmado do gerador
  (ver piloto_demonstracoes_2026-08-31.md).
