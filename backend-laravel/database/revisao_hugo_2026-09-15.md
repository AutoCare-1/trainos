# Revisão do Hugo — 400 exercícios (15/09/2026)

Lista mandada pelo Hugo (o personal que testa o app) sobre os **400 primeiros**
exercícios da biblioteca. Este arquivo é a leitura dela: o número do Hugo já
resolvido no nome real, agrupado por tipo de ação.

## Antes de tudo: qual numeração ele usou

O `#N` do card **não é fixo** — o frontend calcula na hora
(`numerarBiblioteca`, em `frontend/lib/bibliotecaExercicios.ts`), então a poda
de 08/09 (676 → 663) deslocou os números. Havia duas numerações possíveis e
elas divergem.

Conferido contra produção em 14 âncoras — os itens em que o próprio Hugo
escreveu o nome (#300 "abdução de quadril na máquina abdutora", #379 "renegade
row", #395 "airbike", #400 "burpee box jump", #32 "supino com halteres"…).
**Todos batem com a numeração ATUAL de 663.** Ele revisou o app pós-poda.

## Resumo

| | |
|---|--:|
| Revisados | 400 de 663 |
| Aprovados (✅) | 124 |
| Retirar | 147 |
| Corrigir | 129 |

**A biblioteca cairia de 663 para 516.** 37% do que ele viu, ele quer fora.

## O impacto por grupo — e o alerta

| Grupo | tem | sai | fica |
|---|--:|--:|--:|
| Peito | 50 | 24 | 26 |
| Costas | 52 | 25 | 27 |
| Ombros | 41 | 18 | 23 |
| Bíceps | 27 | 8 | 19 |
| Tríceps | 32 | 8 | 24 |
| Antebraço | 7 | 5 | 2 |
| Trapézio | 10 | 6 | 4 |
| Pernas | 52 | 16 | 36 |
| Posterior | 26 | 9 | 17 |
| Glúteos | 33 | 12 | 21 |
| Panturrilha | 15 | 10 | 5 |
| Core | 47 | 6 | 41 |
| Funcional | 40 | 0 | 40 |

**Antebraço fica com 2, Trapézio com 4, Panturrilha com 5.** Peito e Costas
perdem quase metade. Antes de executar, vale o Hugo confirmar que aceita a
biblioteca desse tamanho nesses três grupos — pode ser que ele tenha reprovado
o vídeo e não o exercício, e o certo ali seja regerar, não remover.

## 1. Retirar — 147 exercícios (não precisa do Higgsfield)


**Peito**

- `#4` Crossover de baixo para cima (Polia)
- `#5` Crossover na polia média (Polia)
- `#6` Crossover unilateral (Polia)
- `#9` Crucifixo na polia alta (Polia)
- `#10` Crucifixo na polia baixa (Polia)
- `#12` Crucifixo unilateral na polia (Polia)
- `#15` Flexão de braço com abertura na toalha (Toalha)
- `#16` Flexão de braço com deslocamento lateral (Peso corporal)
- `#19` Flexão de braço declinada (Peso corporal)
- `#20` Flexão de braço diamante (Peso corporal)
- `#22` Flexão de braço nas argolas (Argolas)
- `#24` Floor press com halteres (Halteres)
- `#27` Peck deck (voador) (Máquina)
- `#28` Peck deck inclinado (Máquina)
- `#30` Pullover com halter (Halter)
- `#33` Supino com pegada fechada (Barra)
- `#34` Supino com pegada neutra (Halteres)
- `#37` Supino declinado na máquina (Máquina)
- `#38` Supino declinado no smith (Smith)
- `#41` Supino inclinado com pegada neutra (Halteres)
- `#44` Supino inclinado unilateral com halter (Halter)
- `#46` Supino máquina convergente (Máquina)
- `#47` Supino no chão (floor press) (Barra)
- `#50` Voador na máquina unilateral (Máquina)

**Costas**

- `#53` Barra fixa com peso (Peso corporal)
- `#54` Barra fixa nas argolas (Argolas)
- `#58` Encolhimento na polia (Polia)
- `#61` Face pull com elástico (Elástico)
- `#62` Face pull na polia alta (Polia)
- `#63` Hiperextensão lombar (Peso corporal)
- `#66` Levantamento terra romeno com halteres (Halteres)
- `#67` Levantamento terra sumô (Barra)
- `#68` Pull-up negativa (Peso corporal)
- `#69` Pulldown com braços estendidos (Polia)
- `#71` Puxada frontal (Polia)
- `#73` Puxada frontal com pegada em V (Polia)
- `#74` Puxada frontal pegada aberta (Polia)
- `#77` Puxada frontal unilateral (Polia)
- `#78` Puxada na máquina sentado (Máquina)
- `#79` Puxada por trás (Polia)
- `#84` Remada baixa pegada aberta (Polia)
- `#86` Remada cavalinho (Barra T)
- `#87` Remada cavalinho com pegada neutra (Barra T)
- `#91` Remada curvada no smith (Smith)
- `#95` Remada máquina pegada neutra (Máquina)
- `#96` Remada máquina unilateral (Máquina)
- `#97` Remada nas argolas com pés elevados (Argolas)
- `#99` Remada Pendlay (Barra)
- `#102` Superman no solo (Peso corporal)

**Ombros**

- `#103` Crucifixo invertido (Máquina)
- `#104` Crucifixo invertido na máquina (Máquina)
- `#105` Crucifixo invertido na polia (Polia)
- `#111` Desenvolvimento com pegada neutra (Halteres)
- `#112` Desenvolvimento em parada de mão na parede (Peso corporal)
- `#114` Desenvolvimento militar (Barra)
- `#117` Desenvolvimento por trás da nuca (Barra)
- `#118` Desenvolvimento sentado com barra (Barra)
- `#119` Desenvolvimento sentado com halteres (Halteres)
- `#120` Desenvolvimento unilateral com halter (Halter)
- `#121` Elevação em Y no banco inclinado (Halteres)
- `#125` Elevação frontal com barra (Barra)
- `#126` Elevação frontal na polia (Polia)
- `#129` Elevação lateral com halteres em pé (Halteres)
- `#130` Elevação lateral na máquina (Máquina)
- `#131` Elevação lateral na polia (Polia)
- `#135` Flexão de braço em pique (pike push-up) (Peso corporal)
- `#136` Parada de mão na parede (Peso corporal)

**Bíceps**

- `#147` Rosca alternada com rotação (Halteres)
- `#148` Rosca bayesiana com o braço atrás do corpo (Polia)
- `#151` Rosca concentrada na polia (Polia)
- `#152` Rosca de bíceps na máquina (Máquina)
- `#154` Rosca direta (Barra)
- `#157` Rosca direta com halteres (Halteres)
- `#164` Rosca martelo na corda (Polia)
- `#165` Rosca nas argolas (Argolas)

**Tríceps**

- `#171` Extensão de tríceps com elástico (Elástico)
- `#172` Extensão de tríceps deitado com halteres neutro (Halteres)
- `#174` Extensão de tríceps nas argolas (Argolas)
- `#187` Tríceps corda acima da cabeça (Polia)
- `#192` Tríceps na máquina unilateral (Máquina)
- `#196` Tríceps na polia unilateral (Polia)
- `#200` Tríceps testa com elástico (Elástico)
- `#202` Tríceps testa na polia baixa (Polia)

**Antebraço**

- `#204` Rosca de punho (Barra)
- `#205` Rosca de punho com halteres (Halteres)
- `#206` Rosca de punho invertida (Barra)
- `#207` Rosca de punho invertida com halteres (Halteres)
- `#208` Rosca inversa em pé (Barra)

**Trapézio**

- `#210` Elevação em Y na polia (Polia)
- `#214` Encolhimento inclinado no banco (Halteres)
- `#215` Encolhimento na máquina (Máquina)
- `#217` Encolhimento unilateral com halter (Halter)
- `#218` Puxada alta no cavalete (Barra)
- `#219` Remada alta explosiva (Barra)

**Pernas**

- `#223` Afundo lateral (Halteres)
- `#224` Afundo reverso (Halteres)
- `#226` Agachamento box squat (Barra)
- `#230` Agachamento com barra baixa (Barra)
- `#245` Agachamento pausado (Barra)
- `#250` Avanço em diagonal (Halteres)
- `#252` Belt squat na máquina (Máquina)
- `#257` Leg press 45° (Máquina)
- `#259` Leg press horizontal (Máquina)
- `#260` Leg press pés afastados (Máquina)
- `#261` Leg press pés altos (Máquina)
- `#262` Leg press pés baixos (Máquina)
- `#263` Leg press pés juntos (Máquina)
- `#264` Leg press unilateral (Máquina)
- `#265` Leg press vertical (Máquina)
- `#268` Passada com halteres (Halteres)

**Posterior**

- `#273` Bom dia sentado (Barra)
- `#275` Cadeira flexora unilateral (Máquina)
- `#278` Flexão de joelho com caneleira (Caneleira)
- `#279` Flexão nórdica (Peso corporal)
- `#281` Glute ham raise (Máquina)
- `#289` Mesa flexora com pausa (Máquina)
- `#290` Mesa flexora unilateral (Máquina)
- `#291` Ponte de glúteo com uma perna (Peso corporal)
- `#295` Stiff unilateral (Halter)

**Glúteos**

- `#307` Cadeira abdutora (Máquina)
- `#310` Chute de glúteo na polia (kickback) (Polia)
- `#312` Coice de glúteo com elástico (Elástico)
- `#313` Coice de glúteo na máquina (Máquina)
- `#316` Elevação de quadril com halter (Halter)
- `#317` Elevação de quadril com pés no banco (Peso corporal)
- `#321` Extensão de quadril em quatro apoios (Peso corporal)
- `#322` Fire hydrant (Peso corporal)
- `#323` Frog pump (Peso corporal)
- `#325` Glúteo quatro apoios (Peso corporal)
- `#327` Ponte de glúteo com barra (Barra)
- `#328` Ponte de glúteo com elevação alternada (Peso corporal)

**Panturrilha**

- `#331` Panturrilha burro (donkey calf) (Máquina)
- `#332` Panturrilha com pés para dentro (Máquina)
- `#333` Panturrilha com pés para fora (Máquina)
- `#334` Panturrilha em pé (Máquina)
- `#336` Panturrilha em pé na máquina (Máquina)
- `#340` Panturrilha sentado (Máquina)
- `#341` Panturrilha sentado com anilha (Anilha)
- `#342` Panturrilha sentado unilateral (Máquina)
- `#343` Panturrilha unilateral com halter (Halter)
- `#345` Tibial anterior com elástico (Elástico)

**Core**

- `#351` Abdominal na polia alta (ajoelhado) (Polia)
- `#356` Abdominal supra com pés apoiados (Peso corporal)
- `#375` Prancha com apoio dos antebraços (Peso corporal)
- `#384` Roda abdominal (ab wheel) (Equipamento)
- `#387` Rotação de tronco na polia (woodchop) (Polia)
- `#390` Serra abdominal na toalha (Toalha)

Mesmo caminho da poda de 08/09: entram em `database/biblioteca_podada.php`,
saem dos seeders, e `exercicios:podar-biblioteca --force` roda uma vez em
produção. O comando preserva o que estiver em treino ou com mídia de personal.

## 2. Renomear — cuidado, 6 colidem e 11 trocariam de grupo

Nem todo "mudar o nome" é vocabulário. Boa parte é o Hugo dizendo *"o vídeo
mostra outro exercício"* — e aí renomear resolve o rótulo e cria dois
problemas novos.

**Colidem com exercício que já existe** (viraria nome duplicado na biblioteca):

| # | hoje | vira | já existe |
|---|---|---|---|
| 145 | Rosca 21 com halteres | rosca direta com halteres | #157 |
| 181 | Supino fechado com halteres | supino inclinado com halteres | #40 |
| 182 | Supino fechado no smith | supino inclinado no smith | #43 |
| 258 | Leg press 45° unilateral | leg press horizontal | #259 |
| 274 | Cadeira flexora | cadeira extensora | #253 |
| 304 | Agachamento búlgaro com foco em glúteo | agachamento búlgaro | #227 |

**Ficariam arquivados no grupo muscular errado** (o rename não move de grupo):

| # | hoje | grupo hoje | vira | pede |
|---|---|---|---|---|
| 65 | Levantamento terra com trap bar | Costas | levantamento terra com barra hexagonal | Posterior |
| 139 | Remada alta | Ombros | remada alta com barra | Costas |
| 181 | Supino fechado com halteres | Tríceps | supino inclinado com halteres | Peito |
| 182 | Supino fechado no smith | Tríceps | supino inclinado no smith | Peito |
| 183 | Tríceps coice bilateral | Tríceps | crucifixo inverso com halter | Ombros |
| 274 | Cadeira flexora | Posterior | cadeira extensora | Pernas |
| 276 | Elevação pélvica com perna estendida | Posterior | elevação pélvica solo unilateral | Glúteos |
| 304 | Agachamento búlgaro com foco em glúteo | Glúteos | agachamento búlgaro | Pernas |
| 306 | Agachamento sumô profundo | Glúteos | agachamento sumô com barra | Pernas |
| 326 | Passada profunda para glúteo | Glúteos | avanço com halteres | Pernas |
| 330 | Subida no step alta para glúteo | Glúteos | subida no step com halter | Pernas |

`#183 Tríceps coice bilateral → crucifixo inverso com halter` é o caso mais
claro: não é renomear, é **outro exercício, de outro grupo**. O vídeo do coice
saiu como crucifixo inverso. Ou se aceita o vídeo e o exercício muda de grupo,
ou se mantém o nome e regera o vídeo. Decisão do Hugo, não minha.

**Renome limpo** (vocabulário de academia, sem colisão nem troca de grupo):

- `#1` Chest press sentado → mudar nome para crucifixo
- `#32` Supino com halteres → mudar nome: supino reto com halter
- `#70` Puxada articulada na máquina → puxada frente
- `#88` Remada com kettlebell → remada unilateral com kettblell
- `#178` Mergulho na máquina assistida → paralela no gráviton
- `#179` Mergulho nas paralelas → paralela livre
- `#220` Afundo → mudar o nome: avanço alternado
- `#222` Afundo com halteres → mudar o nome: recuo com halteres
- `#251` Avanço estático → nome: afundo com halter
- `#396` Battle rope com agachamento → corda naval

## 3. Regerar vídeo — 111, e o prognóstico é ruim em 42 deles

- **Peso livre / corpo / cardio: 69** — vale regerar. Na auditoria de
  25/08 essa faixa deu 6% de erro grave, e a regeração corrigiu 13 de 19.
- **Máquina / polia / Smith: 42** — 42% de erro grave na mesma
  auditoria, e curadoria de prompt não mudou o número (os 8 leg press com
  parágrafo curado saíram como cadeira extensora). Regerar aqui é apostar.

A conta de crédito, a 4 créditos por vídeo em `seedance_2_0_mini` 480p:
111 × 4 = **444 créditos** numa rodada só, sem contar re-rolagem.

### Máquina / polia / Smith — decidir antes de gastar

- `#2` Chest press unilateral (Máquina) — mudar nome para crucifixo unilateral/ movimento está errado.
- `#3` Crossover (Polia) — exercício está errado
- `#31` Pullover na polia alta (Polia) — melhor movimento
- `#45` Supino máquina (Máquina) — movimento errado
- `#59` Extensão lombar na máquina (Máquina) — movimento e máquina errada
- `#60` Face pull (Polia) — melhorar movimento
- `#75` Puxada frontal pegada neutra (Polia) — banco e pegada errada
- `#76` Puxada frontal pegada supinada (Polia) — pegada errada
- `#80` Puxador triângulo (Polia) — puxada triângulo/ pegada errada
- `#82` Remada baixa (Polia) — movimento errado
- `#83` Remada baixa com corda (Polia) — remada com argola/ melhor movimento
- `#94` Remada máquina (Máquina) — remada aberta máquina
- `#115` Desenvolvimento na máquina (Máquina) — máquina errada
- `#140` Remada alta na polia baixa (Polia) — tirar o cabo da polia de cima e melhorar o movimento
- `#142` Rotação externa na polia (Polia) — movimento errado
- `#143` Rotação interna na polia (Polia) — movimento errado
- `#149` Rosca com corda na polia baixa (Polia) — trocar a corda naval pela corda na polia
- `#158` Rosca direta na polia (Polia) — vídeo errado (ajustar a barra, polia…)
- `#161` Rosca invertida na polia (Polia) — vídeo errado (ajustar a barra, pegada e posição da pessoa)
- `#173` Extensão de tríceps na máquina (Máquina) — máquina errada e trocar o nome por tríceps testa na máquina
- `#175` Extensão de tríceps unilateral (Polia) — tríceps unilateral na polia alta
- `#185` Tríceps coice na polia (Polia) — exercício errado
- `#186` Tríceps corda (Polia) — melhorar o movimento
- `#189` Tríceps francês na polia (Polia) — mudar a barra e deixar ele em pé
- `#193` Tríceps na polia com barra V (Polia) — ajustar a barra
- `#194` Tríceps na polia com pegada cruzada (Polia) — ajustar o vídeo
- `#195` Tríceps na polia pegada supinada (Polia) — movimento errado
- `#197` Tríceps pulley barra reta (Polia) — ajustar a pegada e posição do aluno
- `#216` Encolhimento no smith (Smith) — ajustar o movimento
- `#254` Cadeira extensora unilateral (Máquina) — ajustar a máquina
- `#277` Extensão de quadril na polia (Polia) — glúteo coice na polia - ajustar o movimento
- `#280` Flexora em pé na máquina (Máquina) — ajeitar a máquina
- `#288` Mesa flexora (Máquina) — ajusta a maquina
- `#301` Abdução de quadril na polia (Polia) — movimento errado
- `#303` Adução de quadril na polia (Polia) — melhorar o movimento
- `#318` Elevação de quadril na máquina (Máquina) — colocar a máquina certa
- `#319` Elevação de quadril no smith (Smith) — ajustar o banco
- `#324` Glúteo no cabo (coice) (Polia) — melhorar o movimento - inclinar o corpo e aumentar a flexão e extensão do quadril
- `#338` Panturrilha no leg press (Máquina) — corrigir o movimento
- `#344` Panturrilha unilateral no leg press (Máquina) — corrigir o movimento
- `#350` Abdominal na máquina com carga (Máquina) — ajustar o erro da máquina - abdominal supra máquina
- `#371` Pallof press (Polia) — exercício errado, segura em isometria mantendo o cabo na lateral

### Peso livre / corpo / cardio — regerar com negação nominal

- `#7` Crucifixo declinado com halteres (Halteres) — movimento errado
- `#8` Crucifixo inclinado com halteres (Halteres) — melhorar movimento
- `#11` Crucifixo reto (Halteres) — melhorar: banco na horizontal e melhorar movimento
- `#14` Flexão de braço (Peso corporal) — melhorar movimento
- `#23` Flexão de braço no TRX (TRX) — movimento errado
- `#35` Supino declinado (Barra) — movimento errado
- `#36` Supino declinado com halteres (Halteres) — posição do banco errado
- `#39` Supino inclinado (Barra) — supino inclinado banco
- `#56` Barra fixa pegada supinada (chin-up) (Peso corporal) — pegada está errada
- `#57` Bom dia com barra (Barra) — retirar o peso da barra
- `#64` Levantamento terra com halteres (Halteres) — melhor movimento
- `#65` Levantamento terra com trap bar (Barra) — levantamento terra c/barra hexagonal
- `#90` Remada curvada com halteres (Halteres) — melhorar movimento
- `#92` Remada curvada pegada supinada (Barra) — pegada está errada
- `#137` Press militar estrito (Barra) — desenvolvimento lateral com barra em pé
- `#139` Remada alta (Barra) — remada alta com barra
- `#141` Rotação externa com elástico (Elástico) — movimento errado
- `#144` Rosca 21 (Barra) — rosca direta com barra reta
- `#153` Rosca de bíceps sentado com halteres (Halteres) — colocar sentado com o encosto do banco na vertical
- `#155` Rosca direta com barra W (Barra W) — ajustar a barra
- `#160` Rosca inversa (Barra) — mudar a pegada
- `#162` Rosca martelo (Halteres) — rosca martelo com halteres - mudar a pegada para neutra
- `#163` Rosca martelo alternada (Halteres) — mudar a pegada para neutra
- `#166` Rosca Scott (Barra W) — nome: rosca scott com barra reta (ajustar a barra)
- `#167` Rosca Scott com barra W (Barra W) — ajustar a barra
- `#168` Rosca Scott com halter unilateral (Halter) — ajustar o banco
- `#176` Flexão diamante para tríceps (Peso corporal) — melhorar o movimento
- `#177` Mergulho entre bancos (Peso corporal) — tríceps banco livre com pés suspensos
- `#180` Mergulho no banco (Peso corporal) — tríceps banco livre (melhorar o movimento, manter os pés no chão)
- `#184` Tríceps coice com halter (Halter) — melhorar o movimento
- `#190` Tríceps francês sentado com halter (Halter) — ajustar o halter
- `#198` Tríceps testa (Barra W) — movimento errado
- `#199` Tríceps testa com barra W (Barra W) — movimento errado
- `#201` Tríceps testa com halteres (Halteres) — melhorar o movimento
- `#211` Encolhimento com halteres (Halteres) — melhorar movimento
- `#221` Afundo com barra (Barra) — mudar o nome: avanço alternado com barra
- `#225` Afundo reverso com barra (Barra) — mudar o nome: recuo com barra
- `#229` Agachamento com barra alta (Barra) — mudar o nome: agachamento com barra
- `#235` Agachamento com peso corporal (Peso corporal) — melhorar o movimento
- `#238` Agachamento frontal (Barra) — passar a barra para frente
- `#246` Agachamento sissy (Peso corporal) — colocar a máquina
- `#247` Agachamento sumô com halter (Halter) — nome: agachamento sumô com barra
- `#255` Extensão de joelho com caneleira (Caneleira) — ajustar o banco
- `#267` Passada com barra (Barra) — avanço com barra
- `#271` Terra sumô com halteres (Halteres) — agachamento sumô com halteres - melhorar a pegada
- `#276` Elevação pélvica com perna estendida (Peso corporal) — elevação pélvica solo unilateral
- `#302` Abdução em pé com elástico (Elástico) — melhorar o movimento
- `#306` Agachamento sumô profundo (Halter) — agachamento sumô com barra
- `#309` Caminhada lateral com elástico (Elástico) — deslocamento lateral com miniband - melhorar movimento
- `#311` Coice de glúteo com caneleira (Caneleira) — glúteo coice com caneleira - colocar caneleira, colchonete e deixar os cotovelos flexionados
- `#320` Elevação de quadril unilateral (Peso corporal) — deixar as escapulas no banco
- `#329` Ponte de glúteo no solo (Peso corporal) — elevação pélvica solo
- `#330` Subida no step alta para glúteo (Halteres) — subida no step com halter (colocar o pé completo no step)
- `#337` Panturrilha em pé no step (Peso corporal) — apoiar no espaldar
- `#346` Abdominal bicicleta (Peso corporal) — melhorar o movimento - esticar as pernas
- `#348` Abdominal declinado (Peso corporal) — colocar o banco declinado de abdômen
- `#354` Abdominal remador (Peso corporal) — melhorar o movimento - descer mais
- `#359` Dead bug (Peso corporal) — melhorar o movimento
- `#360` Dragon flag (Peso corporal) — colocar encosto no banco para segurar
- `#365` Hollow hold (Peso corporal) — melhorar postura - pernas mais baixas e braços atrás da cabeça
- `#366` Hollow rock (Peso corporal) — corrigir o movimento - pernas e braços mais próximos do chão
- `#368` L-sit no solo (Peso corporal) — ajustar a postura - mais no chão mantendo o corpo acima do solo em isometria
- `#369` Limpador de para-brisa suspenso na barra (Peso corporal) — abdominal pára-brisa suspenso na barra - melhorar movimento - eleva as pernas durante o movimento
- `#370` Mountain climber lento (Peso corporal) — corrigir execução (pernas trocam de lugar)
- `#381` Prancha dinâmica (up-down) (Peso corporal) — corrigir o movimento
- `#385` Roda abdominal ajoelhado (Equipamento) — corrigir o movimento
- `#386` Rollout na barra (Barra) — corrigir o movimento
- `#397` Bear crawl (Peso corporal) — melhorar o movimento
- `#398` Bicicleta ergométrica (Bicicleta) — trocar a bicicleta

## 4. O que ele ainda não viu

263 exercícios: o resto de Funcional (32) e os grupos da leva complementar —
Esportivo, Ativação, Mobilidade, Equilíbrio, Prevenção, Alongamento. Ele parou
no `#400` (Burpee com salto na caixa). Se a taxa de reprovação se repetir, há
mais ~95 remoções vindo.

## Ordem sugerida

1. **Confirmar com o Hugo** os três grupos que ficam quase vazios (Antebraço 2,
   Trapézio 4, Panturrilha 5) e os 6 renames que colidem.
2. **Executar as remoções e os renames limpos** — não dependem do Higgsfield.
3. **Regerar só peso livre/corpo** (69), com negação nominal e re-rolagem antes
   de reescrever.
4. **Máquina/polia (42): decidir** entre filmar com o próprio Hugo, licenciar
   acervo, ou aceitar como está. Regerar por IA não vem resolvendo.

