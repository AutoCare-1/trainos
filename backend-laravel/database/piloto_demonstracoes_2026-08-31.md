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

# Lote 2 — 7 correções + 5 novos, 48 créditos (622,18 restantes)

As `cena` foram escritas pra FAMÍLIA, não pros 4 que falharam: o piloto viu
2 dos 8 exercícios de argolas, e a montagem que ele errou é a mesma dos
outros 6. Entraram 8 de argolas, 5 de solo barriga-pra-cima, 2 de parede,
5 de bastão e o figura 4.

| # | Exercício | Antes | Agora |
|---|---|---|---|
| 21 | Alongamento de panturrilha na parede | GRAVE | **OK** |
| 22 | Alongamento de glúteo deitado (figura 4) | MÉDIO | **GRAVE** — duas cabeças |
| 23 | Remada nas argolas com pés elevados | GRAVE | **OK** |
| 24 | Mergulho nas argolas | GRAVE | **OK** |
| 25 | Deslizamento de isquiotibiais no disco | GRAVE | **MÉDIO** — ponte certa, disco não |
| 26 | Serra abdominal no disco | MÉDIO | **MÉDIO** — disco não |
| 27 | Passagem de bastão pela cabeça | MÉDIO | **OK** |
| 28 | Alongamento de quadríceps em pé | novo | **OK** |
| 29 | Alongamento de flexores do quadril ajoelhado | novo | **OK** |
| 30 | Alongamento de peitoral no batente | novo | **OK** |
| 31 | Alongamento de tríceps acima da cabeça | novo | **OK** |
| 32 | Postura da criança | novo | **OK** |

**9 OK · 2 MÉDIO · 1 GRAVE — 75%**, contra 42% do piloto.

## O que fechou

**A cena de montagem resolve montagem: 5 de 6.** Panturrilha, remada nas
argolas, mergulho nas argolas e bastão saíram certos com o texto escrito
depois de ver o erro. Confirma de novo que erro de montagem é dica faltando,
não limite do gerador — e que a dica de família vale pra família inteira.

**Os 5 alongamentos novos saíram OK de primeira, 5 de 5.** É o maior grupo
da leva (46) e o mais barato de acertar: peso corporal, isometria e nenhum
prop. A faixa boa se confirmou.

**O disco deslizante é limite do gerador — decidido.** A negação dura
("completely FLAT round plastic disc, no dome, no curve, no cushion, no
thickness, never a BOSU, never a balance pad") não mudou nada: continua um
pad redondo e continua um só. É o mesmo caso da corda nos 402, que também
não virou corda com nenhuma quantidade de texto.

NÃO gastar os 44 créditos dos 11 exercícios de disco. Duas saídas melhores:
deixar no boneco animado, ou trocar o equipamento pra `Toalha` na biblioteca
— o deslizamento funciona com toalha em piso liso, é o que academia de
verdade usa, e toalha é objeto que o gerador desenha bem.

**O figura 4 saiu com duas cabeças.** É o modo de falha de duplicação que a
guarda de anatomia já tenta cobrir e que às vezes escapa mesmo assim. Não é
prompt: é sorteio, do mesmo tipo do `supino-declinado` que acertou com o
texto que o `supino-declinado-com-halteres` errou. Vale UMA regeração.

## Onde estão

Vídeos e tiras: `/private/tmp/claude-501/-Users-filipelima/6cab34fd-b357-4d6a-a4ef-51a353148aa0/scratchpad/piloto/`
(scratchpad de sessão não é apagado — não regerar pra rever.)

## Placar de créditos

24 vídeos gerados, 96 créditos. Restam **622**, que dão 155 vídeos.
Faltam 250 da leva — ou 239 tirando os de disco. Ainda falta decidir entre
comprar crédito e priorizar dentro do que cabe.

Vídeos e tiras: `/private/tmp/claude-501/-Users-filipelima/6cab34fd-b357-4d6a-a4ef-51a353148aa0/scratchpad/piloto/`
(scratchpad de sessão não é apagado — não regerar pra rever).
