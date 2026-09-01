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

# Lote 3 — 11 alongamentos + regeração do figura 4, 48 créditos (574 restantes)

| # | Exercício | Veredito |
|---|---|---|
| 41 | Alongamento de isquiotibiais em pé | **OK** |
| 42 | Alongamento de isquiotibiais deitado com elástico | **OK** — elástico íntegro |
| 43 | Alongamento de isquiotibiais em passada com apoio no banco | **GRAVE** — ele apoia as MÃOS no banco; é a perna que sobe |
| 44 | Alongamento de quadríceps deitado de lado | **OK** |
| 45 | Alongamento de quadríceps na parede ajoelhado | **MÉDIO** — ajoelhado nos dois joelhos, sem o pé na parede |
| 46 | Alongamento de glúteo sentado na cadeira | **GRAVE** — sentado sem cruzar o tornozelo no joelho |
| 47 | Alongamento de piriforme sentado | **OK** |
| 48 | Alongamento de adutores sentado (borboleta) | **OK** |
| 49 | Alongamento de adutores em afastamento lateral | **OK** |
| 50 | Alongamento de virilha em agachamento profundo | **OK** |
| 51 | Alongamento de sóleo com joelho flexionado | **MÉDIO** — joelho de trás sai ESTENDIDO mesmo com a cena mandando dobrar |
| 52 | Alongamento de glúteo deitado (figura 4) — regeração | **OK** — uma cabeça só |

**8 OK · 2 MÉDIO · 2 GRAVE — 67%**

## Achados

**A duplicação era sorteio mesmo.** O figura 4 voltou certo na segunda tentativa,
com uma cabeça só. Somei ao prompt uma negação explícita de segunda cabeça
("exactly ONE head and ONE face in the whole frame, no mirrored body, no
reflection") — mas como a primeira tentativa já tinha a guarda genérica de
anatomia, não dá pra saber se foi a negação nova ou a re-rolagem. De qualquer
jeito custou 4 créditos e resolveu.

**Os 2 graves são de novo montagem ambígua, e o padrão agora tem nome:
substantivo que não diz o que apoia onde.** "Apoio no banco" não diz apoio de
QUÊ — o gerador apoiou as mãos, e o exercício é a perna em cima. Escritas as
três cena que faltavam (banco, cadeira, parede ajoelhado).

**O sóleo é o primeiro caso em que a cena NÃO pegou.** O prompt dizia "the rear
leg is set back with the KNEE CLEARLY BENT" em maiúsculas e o joelho saiu
estendido, idêntico ao alongamento de panturrilha que veio antes dele. É
distinção de variante — o mesmo critério (a) que a poda de 22/08 usou pra
cortar 244 exercícios, e o mesmo tipo de erro dos "pegada neutra sai pronada"
que sobraram nos 402. Não insistir: 4 créditos por tentativa pra uma diferença
que nem o personal vê no vídeo.

## Placar

36 vídeos gerados, 144 créditos. **574 restantes = 143 vídeos.**
Faltam 238 da leva, ou 227 tirando os 11 de disco.

# Lote 4 — 6 correções + 6 novos, 48 créditos (526 restantes)

Primeiro lote depois da varredura preventiva das 58 cena.

| # | Exercício | Antes | Agora |
|---|---|---|---|
| 61 | Alongamento de isquiotibiais em passada com apoio no banco | GRAVE | **OK** |
| 62 | Alongamento de glúteo sentado na cadeira | GRAVE | **OK** |
| 63 | Alongamento de quadríceps na parede ajoelhado | MÉDIO | **OK** |
| 64 | Alongamento de sóleo com joelho flexionado | MÉDIO | **OK** |
| 65 | Deslizamento de isquiotibiais na toalha | MÉDIO (disco) | **OK** |
| 66 | Serra abdominal na toalha | MÉDIO (disco) | **MÉDIO** — toalha certa, mas sob as mãos |
| 67 | Alongamento de dorsal na barra | novo | **MÉDIO** — barra alta, tronco em pé |
| 68 | Alongamento de lombar em posição fetal | novo | **OK** |
| 69 | Alongamento de abdome (esfinge) | novo | **OK** |
| 70 | Alongamento de cadeia posterior com toalha | novo | **OK** |
| 71 | Alongamento de rotadores externos (sleeper stretch) | novo | **MÉDIO** — cotovelo estendido |
| 72 | Alongamento de manguito rotador na porta | novo | **OK** |

**9 OK · 3 MÉDIO · ZERO GRAVE — 75%.** As 6 correções pegaram, 6 de 6.

## Duas correções do que eu tinha afirmado antes

**Eu estava errado sobre o sóleo.** Escrevi no lote 3 que era limite do
gerador e que não valia insistir. Não era: bastou dizer o contraste POR NOME
— "the rear leg is NEVER straight, this is not the straight-leg calf stretch"
— e saiu certo. A lição não é "distinção de variante é limite", é que o
gerador precisa saber de QUAL exercício parecido ele tem que se afastar.
Antes de dar variante como perdida, tentar a negação nominal do vizinho.

**E estava errado sobre o disco.** O erro não era do gerador, era meu, de ter
agrupado disco de EQUILÍBRIO e disco DESLIZANTE como um prop só. O de
equilíbrio sempre esteve certo; o deslizador virou toalha e renderizou de
primeira.

## O padrão dos 3 médios que sobraram

Todos são a cena estar certa mas INCOMPLETA — ela diz o apoio e não diz qual
extremidade vai nele. "Under each working foot or hand" deixou o gerador
escolher a mão quando o exercício é do pé. Cena corrigida nos três.

## Placar

48 vídeos, 192 créditos. **526 restantes = 131 vídeos.**
Faltam 226 da leva. Acumulado: 31 OK, 10 médios, 7 graves — e dos 7 graves,
6 já foram corrigidos e reinstalados.

# Lote 5 — 3 correções + 9 alongamentos, 48 créditos (478 restantes)

| # | Exercício | Antes | Agora |
|---|---|---|---|
| 81 | Serra abdominal na toalha | MÉDIO | **MÉDIO** — toalha sob os antebraços, 2ª tentativa |
| 82 | Alongamento de dorsal na barra | MÉDIO | **OK** |
| 83 | Alongamento de rotadores externos (sleeper) | MÉDIO | **MÉDIO** — cotovelo ainda abre, 2ª tentativa |
| 84 | Alongamento de tibial anterior ajoelhado | novo | **OK** |
| 85 | Alongamento de peitoral com as mãos atrás da cabeça | novo | **OK** |
| 86 | Alongamento de dorsal ajoelhado no banco | novo | **OK** |
| 87 | Alongamento de bíceps na parede | novo | **OK** |
| 88 | Alongamento de deltoide posterior cruzando o braço | novo | **OK** |
| 89 | Alongamento de antebraço em extensão | novo | **MÉDIO** — palma ambígua |
| 90 | Alongamento de antebraço em flexão | novo | **OK** |
| 91 | Alongamento de trapézio superior sentado | novo | **OK** |
| 92 | Alongamento cervical lateral | novo | **OK** |

**9 OK · 3 MÉDIO · ZERO GRAVE — 75%.** Segundo lote seguido sem grave.

## Os dois que não pegaram na segunda tentativa: PARAR

`Serra abdominal na toalha` e o `sleeper stretch` já levaram duas rodadas de
cena cada e continuam médios. A regra do RETOMAR vale aqui: não gastar
crédito regerando esperando resultado diferente.

Nos dois o defeito é o mesmo tipo, e é sutil: **a toalha vai para onde o peso
visual está** (ele apoia nos antebraços, então a toalha aparece embaixo
deles), e **o cotovelo do sleeper abre porque deitar de lado com o cotovelo
a 90 graus é uma pose rara**. Nenhum dos dois engana o personal: a prancha é
prancha, o deitado de lado é deitado de lado. Ficam como médios aceitos.

## Placar

60 vídeos, 240 créditos. **478 restantes = 119 vídeos.**
Alongamento: 34 dos 46 feitos. Faltam 214 da leva.
Acumulado: 40 OK, 16 médios, 7 graves — os 7 graves todos refeitos e certos.
Taxa dos dois últimos lotes, já com a varredura preventiva: **75% OK, 0% grave**,
contra 42% e 33% do piloto.

# Lote 6 — 12 alongamentos, 48 créditos (430 restantes). GRUPO ALONGAMENTO FECHADO

| # | Exercício | Veredito |
|---|---|---|
| 101 | Alongamento de punho em extensão na mesa | **MÉDIO** — palma na mesa, devia ser o dorso |
| 102 | Alongamento de escaleno e cervical anterior | **OK** |
| 103 | Alongamento de lombar com joelhos cruzados | **OK** |
| 104 | Alongamento de coluna em torção sentado | **OK** |
| 105 | Alongamento de cadeia anterior em pé na parede | **MÉDIO** — de lado, devia ser de frente |
| 106 | Alongamento de banda iliotibial em pé | **OK** |
| 107 | Alongamento de psoas em decúbito dorsal na maca | **OK** — a maca renderizou |
| 108 | Alongamento de quadril em passada profunda com rotação | **OK** |
| 109 | Alongamento de coluna suspenso na barra com pés no chão | **MÉDIO** — pés não apoiados |
| 110 | Postura do pombo | **MÉDIO** — perna de trás não estende |
| 111 | Alongamento de ombro em rotação interna com toalha | **OK** |
| 112 | Alongamento de cadeia lateral em pé com braço acima da cabeça | **OK** |

**8 OK · 4 MÉDIO · ZERO GRAVE.** Terceiro lote seguido sem grave.

## Alongamento: 46/46 gerados

O maior grupo da leva está fechado. Nenhum grave sobrou no grupo inteiro, e
os médios são todos de detalhe que não engana o personal.

Os 4 médios deste lote são do mesmo tipo dos anteriores — par espelhado
(dorso x palma, de frente x de lado). Já foram duas rodadas de cena nesse
padrão e a taxa não muda: o par espelhado responde à negação nominal
**às vezes** (sóleo pegou, punho não). Não vale terceira rodada.

## Placar

72 vídeos, 288 créditos. **430 restantes = 107 vídeos.**
Faltam 202 da leva. Acumulado: **48 OK, 20 médios, 7 graves** — os 7 graves
todos refeitos e certos, e três lotes seguidos sem produzir grave novo.

# Lote 7 — 12 de mobilidade, 48 créditos (382 restantes)

| # | Exercício | Veredito |
|---|---|---|
| 121 | Mobilidade torácica em quatro apoios (open book) | **OK** |
| 122 | Rotação torácica deitado de lado | **OK** |
| 123 | Gato e camelo | **OK** |
| 124 | Mobilidade de quadril 90/90 | **GRAVE** — sai EM PÉ tocando o pé |
| 125 | Transição 90/90 com rotação | **MÉDIO** — sentado, mas não em 90/90 |
| 126 | Sustentação em agachamento profundo com apoio | **OK** |
| 127 | Círculo de quadril em quatro apoios | **OK** |
| 128 | Mobilidade de tornozelo na parede | **OK** |
| 129 | Mobilidade de tornozelo com elástico | **GRAVE** — o elástico não aparece em nenhum frame |
| 130 | Deslizamento de escápula na parede (wall slide) | **OK** |
| 131 | Círculo de ombro com bastão | **OK** |
| 132 | Rotação de ombro no batente da porta | **MÉDIO** — cotovelo não fica a 90° |

**8 OK · 2 MÉDIO · 2 GRAVE.** Quebra a sequência de três lotes sem grave.

## Os dois graves são de um tipo NOVO, e não é montagem

O 90/90 tinha cena longa e específica — "both shins flat on the ground at
right angles, never cross-legged, never kneeling" — e ele fez um exercício
completamente diferente, em pé. Isso não é montagem ambígua: é **posição que
o gerador não tem no repertório**. Mesma família do disco deslizante. Sentar
no chão com as duas canelas em L é uma pose rara fora do universo de
mobilidade, e nenhuma quantidade de texto inventa o que ele não conhece.

O elástico no tornozelo sumiu inteiro. A negação de elástico que funciona nos
28 outros descreve a faixa saindo das MÃOS; ancorada no tornozelo, baixa e
fina contra piso escuro, ela não renderiza. Vale UMA tentativa com faixa
clara e grossa descrita por cor antes de desistir.

## Achado operacional: `declined_preset_id` cobre um preset só

O item 126 foi recusado com um preset DIFERENTE ("DROWN IN MUSIC",
f1821f84-945b-4cd1-9085-1f479db0028e) mesmo mandando o `declined_preset_id`
do preset antigo. Ou seja: o campo suprime aquele preset específico, não a
recomendação em geral. Numa submissão em lote, tratar cada recusa com o id
que vier no erro dela. Não cobra nada, mas custa uma viagem.

## Placar

84 vídeos, 336 créditos. **382 restantes = 95 vídeos.**
Mobilidade: 15 dos 43. Faltam 190 da leva.
Acumulado: **56 OK, 22 médios, 9 graves** — 7 dos 9 graves já refeitos e certos.

# Lote 8 — 2 correções + 10 de mobilidade, 48 créditos (334 restantes)

**12 OK · 0 MÉDIO · 0 GRAVE. Primeiro lote 100%.**

| # | Exercício | Veredito |
|---|---|---|
| 141 | Mobilidade de quadril 90/90 (regeração) | **OK** — sentado, canelas no chão |
| 142 | Mobilidade de tornozelo com elástico (regeração) | **OK** — faixa vermelha visível nos 5 frames |
| 143 | Mobilidade cervical em flexão e extensão | **OK** |
| 144 | Rotação cervical controlada | **OK** |
| 145 | Alongamento dinâmico do flexor de quadril | **OK** |
| 146 | Mobilidade de punho em quatro apoios | **OK** |
| 147 | Rotação de quadril em pé (hip airplane) | **OK** |
| 148 | Balanço de perna frontal | **OK** |
| 149 | Balanço de perna lateral | **OK** |
| 150 | Rotação de tronco sentado com bastão | **OK** |
| 151 | Mobilidade de coluna em posição de criança | **OK** |
| 152 | Cachorro olhando para baixo com pedalada | **OK** |

## CORREÇÃO da conclusão do lote 7

No lote 7 eu escrevi que o 90/90 era "posição que o gerador não tem no
repertório, mesma família do disco deslizante" e que nenhuma quantidade de
texto resolveria. **Estava errado, e os dois voltaram certos.**

O que mudou não foi mais descrição da posição certa — a cena antiga já
descrevia as duas canelas em L com detalhe. O que mudou foi **negar o erro
observado**: "He is SEATED ON THE FLOOR the whole time: he never stands up
and is never on his feet at any point in the clip." O erro era ele ficar em
pé, e a frase que resolve é a que proíbe ficar em pé.

Mesma coisa no elástico: descrever melhor a faixa não adiantava; nomear
**cor e espessura** ("thick BRIGHT RED band, as wide as his hand, clearly
visible against the dark floor in every single frame") fez ela renderizar.

Isso é exatamente a regra que o arquivo de memória já tinha pra máquina e
polia — *negação nominal da peça errada* — e que eu não estava aplicando a
posição de corpo nem a prop. A regra geral, agora com três famílias de
evidência:

> **Descrever o certo com mais palavras não move. Negar por nome o errado que
> apareceu move.** Vale pra aparelho (máquina/polia), pra montagem do corpo
> (argolas, ponte, parede) e pra posição de chão (90/90).

Consequência prática: quando um vídeo sai errado, a regeração NÃO deve ser
"escrever a cena melhor" — deve ser "acrescentar uma frase que proíbe o que
eu acabei de ver". É mais barato e tem taxa muito maior.

Também revê o veredito do disco: ele foi condenado com negação genérica
("never a BOSU, never a balance pad"). Pode ser que uma negação do erro
específico observado — um pad único e redondo sob os dois pés — pegasse. Não
vale desfazer a troca pra toalha, que já está certa e é mais realista, mas a
conclusão "limite do gerador" era prematura ali também.

## Placar

96 vídeos, 384 créditos. **334 restantes = 83 vídeos.**
Mobilidade: 27 dos 43. Faltam 178 da leva.
Acumulado: **68 OK, 22 médios, 9 graves** — todos os 9 graves refeitos e certos.

# Lote 9 — 9 correções de médio + 3 de mobilidade, 48 créditos (286 restantes)

**8 OK · 4 MÉDIO · ZERO GRAVE.** Das 9 correções, 5 pegaram.

| # | Exercício | Tentativa | Veredito |
|---|---|---|---|
| 161 | Serra abdominal na toalha | 3ª | **MÉDIO** — toalha aparece também sob os antebraços |
| 162 | Sleeper stretch | 3ª | **MÉDIO** — cotovelo de baixo ainda estende |
| 163 | Alongamento de antebraço em extensão | 2ª | **OK** |
| 164 | Alongamento de punho em extensão na mesa | 2ª | **MÉDIO** — apoia a palma, não o dorso |
| 165 | Alongamento de cadeia anterior na parede | 2ª | **OK** |
| 166 | Coluna suspenso na barra com pés no chão | 2ª | **MÉDIO** — pés alternam chão e ar |
| 167 | Postura do pombo | 2ª | **OK** — perna de trás agora estende |
| 168 | Transição 90/90 com rotação | 2ª | **OK** |
| 169 | Rotação de ombro no batente | 2ª | **OK** — cotovelo a 90° |
| 170 | Escorpião deitado | novo | **OK** |
| 171 | Ponte torácica lateral | novo | **OK** |
| 172 | Mobilidade de quadril com joelho alto e rotação | novo | **OK** |

## Onde a negação do erro funciona e onde não funciona

A regra do lote 8 se confirmou nas cinco que pegaram: negar o erro observado
("that back knee is never bent", "the arm is never straight", "he never
stands up") resolve montagem e posição de membro inteiro.

**Os quatro que resistem têm todos a mesma natureza, e é uma natureza nova:
distinção de FACE ou de CONTATO de um segmento pequeno.**

- toalha sob os pés x sob os antebraços
- dorso da mão x palma da mão
- cotovelo dobrado a 90° x estendido
- pé tocando o chão x no ar

Não é o corpo inteiro no lugar errado — é qual LADO de uma parte pequena
encosta em quê. O gerador desenha a cena certa e erra o detalhe, e a negação
explícita ("his palms never touch the bench at any moment") não muda nada.
Três tentativas na serra abdominal e no sleeper, duas no punho e na barra.

**Encerro esses quatro como estão.** Somam 12 créditos por tentativa sem
convergir, e o erro que sobra é de grau: o exercício lê, a montagem está
certa, e o detalhe errado é justamente o que o personal corrige ao vivo com
o aluno. Gastar mais aqui é o mesmo erro que a poda de 22/08 documentou —
perseguir uma variação que ninguém vê no vídeo de 4 segundos.

## Placar

108 vídeos, 432 créditos. **286 restantes = 71 vídeos.**
Mobilidade: 30 dos 43. Faltam 166 da leva.
Acumulado: **81 OK, 18 médios, 9 graves.** Todos os graves refeitos e certos;
dos 18 médios, 14 refeitos e certos e 4 encerrados como estão.
