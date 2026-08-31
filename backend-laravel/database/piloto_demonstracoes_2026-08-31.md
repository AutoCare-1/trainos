# Piloto da leva complementar — 12 vídeos, 48 créditos

Rodado em 31/08/2026 ANTES de comprometer os outros 262, justamente porque
não ter feito isso nos 402 custou três rodadas de regeração.

Escolha dos 12: um por dimensão que a auditoria de 25/08 nunca mediu — os
5 props que a biblioteca nunca teve (rolo, bosu, disco, argolas, bastão) e a
isometria (posição sustentada em vez de repetida).

Receita: `seedance_2_0_mini`, 480p, 4s, 9:16, sem áudio, 6 fotos de
referência. Conferido: 496x864 e 4 créditos por vídeo (718,18 -> 670,18).
A primeira submissão foi recusada com preset "IN THE DARK", sem cobrar —
passou reenviando com `declined_preset_id`, como o RETOMAR já avisava.

## Veredito

| # | Exercício | Prop | Veredito |
|---|---|---|---|
| 01 | Alongamento de isquiotibiais sentado | — | **OK** |
| 02 | Alongamento de panturrilha na parede | — | **GRAVE** — perna de trás dobrada com o pé no ar; calcanhar no chão é a definição do exercício |
| 03 | Alongamento de glúteo deitado (figura 4) | — | **MÉDIO** — o cruzamento do tornozelo não lê, e ele levanta os ombros (vira abdominal) |
| 04 | Liberação miofascial de quadríceps no rolo | Rolo | **OK** |
| 05 | Liberação miofascial de banda iliotibial no rolo | Rolo | **OK** |
| 06 | Apoio unipodal em superfície instável | Bosu | **OK** |
| 07 | Agachamento no bosu com apoio bipodal | Bosu | **OK** |
| 08 | Serra abdominal no disco | Disco | **MÉDIO** — o disco virou um pad redondo, e só um em vez de dois |
| 09 | Deslizamento de isquiotibiais no disco | Disco | **GRAVE** — virou prancha reversa sentada; a ponte exige ombros no chão e quadril alto |
| 10 | Remada nas argolas com pés elevados | Argolas | **GRAVE** — ele está EM PÉ puxando; a remada invertida exige o corpo na horizontal |
| 11 | Mergulho nas argolas | Argolas | **GRAVE** — virou barra fixa; no mergulho o corpo fica apoiado EM CIMA das argolas |
| 12 | Passagem de bastão pela cabeça | Bastão | **MÉDIO** — bastão perfeito, movimento aleatório em vez do arco frente-trás |

**5 OK · 3 MÉDIO · 4 GRAVE** (42% OK, 33% grave)

## O que o piloto provou

**A negação nominal do prop funcionou: 7 de 8.** Rolo 2/2, bosu 2/2, argolas
2/2, bastão 1/1 — todos renderizaram como o objeto certo, de primeira, num
prop que o gerador nunca tinha visto. Confirma a lição da rodada 2: o que
corrige não é descrever mais, é negar por nome a peça em que aquilo costuma
virar.

**O disco deslizante é o único prop que não pegou.** Vira um pad redondo
pequeno, e aparece um só. É objeto pequeno e sem silhueta forte — provável
limite do gerador, do mesmo tipo da corda que nunca virou corda nos 402.
Antes de gastar nos 11 exercícios de disco, tentar UM com negação mais dura
("two separate discs, one under each foot, completely flat like a plate on
the floor, no dome and no curvature").

**A isometria funcionou.** Os quatro `estatico` sustentaram a posição sem
inventar repetição. A marcação valeu.

**Todos os 4 graves são MONTAGEM DE CENA, não prop nem movimento.** É
exatamente o que `cena` existe pra resolver, e o padrão é o mesmo dos 7 de 7
erros de puxada de 23/08: a instrução da biblioteca descreve o MOVIMENTO e
nunca a MONTAGEM (orientação do corpo, o que apoia onde), e o gerador chuta.

Nenhum dos 4 é limite do modelo — é dica faltando. O que cada um precisa:

- `Alongamento de panturrilha na parede` — perna de trás reta, calcanhar
  colado no chão, nunca com o pé levantado.
- `Deslizamento de isquiotibiais no disco` — ombros no chão, quadril alto,
  nunca sentado com as mãos atrás.
- `Remada nas argolas com pés elevados` — corpo na horizontal por baixo das
  argolas, calcanhares no banco, nunca em pé.
- `Mergulho nas argolas` — corpo apoiado EM CIMA das argolas com os braços ao
  lado das costelas, nunca pendurado com os braços acima da cabeça.

## Próximo passo

Escrever essas quatro `cena` (mais as duas dos médios), regerar os 6 e
conferir a tira ANTES de soltar os 262 restantes. Custo: 24 créditos.
Se pegar, a projeção da leva inteira melhora muito — os 5 OK e os 2 médios
de prop já mostram que a faixa boa se confirma aqui.

Vídeos e tiras: `/private/tmp/claude-501/-Users-filipelima/6cab34fd-b357-4d6a-a4ef-51a353148aa0/scratchpad/piloto/`
(scratchpad de sessão não é apagado — não regerar pra rever).
