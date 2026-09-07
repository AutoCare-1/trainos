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
| (demais 7 grupos) | | — | |

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
