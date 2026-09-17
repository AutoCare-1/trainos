# Livro-razão da revisão do Hugo — os 400, item a item

Gerado em 17/09/2026 a partir da lista bruta do Hugo (400 linhas, numeração de
**663** pós-poda de 08/09, conferida nas âncoras que ele nomeou: #300 abdução de
quadril na máquina, #379 renegade, #395 airbike, #400 burpee box jump).

**Fonte da verdade item a item: `revisao_hugo_livro_razao.tsv`** (400 linhas com
número, nome atual, grupo, equipamento, queixa verbatim, classe, nome novo e
status). Este .md é o resumo. Companheiros: `revisao_hugo_2026-09-15.md` (a
leitura original), `fila_revisao_hugo.tsv` (só o que falta gerar) e
`aprovados_revisao_hugo.php` (o que já foi instalado, com a receita de desfazer).

## ⚠️ A numeração está CONGELADA de propósito

O `#N` desta tabela é o que o Hugo usou (biblioteca de 663, 15/09) e continua
valendo — é por ele que ele vai reconferir se as correções ficaram boas. Duas
coisas reordenariam a lista e por isso estão seguradas até ele validar:

1. **Os renames** (a lista é alfabética dentro do grupo: os 48 deslocam 216 das
   663 posições);
2. **A poda dos 147** (tudo depois do primeiro removido anda).

Quando ele liberar, as duas entram juntas e aí ele recebe uma lista numerada
nova (`php artisan exercicios:numerar-biblioteca`). Daí em diante, a chave
confiável passa a ser o NOME.

## A distinção que faltava

Boa parte dos "mudar o nome" do Hugo é **só rótulo, sem vídeo novo**. Separando
por classe, a conta fecha exatamente com a dele (124 ✅ / 147 retirar / 129 corrigir):

| classe | o que é | quantos |
|---|---|--:|
| `OK` | aprovado, nada a fazer | 105 |
| `OK+NOME` | aprovou o vídeo e pediu outro nome | 19 |
| `NOME` | **só renomear — não precisa de vídeo** | 32 |
| `NOME+VIDEO` | renomear E regerar | 15 |
| `VIDEO` | só regerar | 82 |
| `RETIRAR` | sai da biblioteca | 147 |

**Consequência prática:** o que precisa de vídeo são 97 itens, não 114. Destes,
51 já estão aprovados, 2 encerrados e **44 faltam gerar**.

## Placar em 17/09/2026

| | |
|---|--:|
| Vídeos aprovados e **INSTALADOS em 17/09** | 57 |
| Aprovados no lote 6 (polia), ainda **não instalados** | 13 |
| Faltam gerar | **44** |
| Encerrados (causa raiz é o nome) | 2 (#338, #344) |
| Parcial, esperando o Hugo | 1 (#359 dead bug) |
| **Renames prontos, NÃO aplicados** (esperando o Hugo) | **48** |
| Renames que dependem de decisão dele | 18 |
| Podados no código, não deployados | 147 |

### Seis vídeos gerados sem o Hugo pedir

Descoberto ao montar este livro-razão. Não são vídeos ruins — passaram na tira
de frames e melhoraram o que estava no ar — mas custaram crédito sem estar na
lista dele:

- **#94, #276, #329:** a queixa era **só o nome**. (#94 ainda foi no 2.5, 12 créditos.)
- **#361, #363, #392:** ele já tinha dado **✅**; pediu nome novo e um ajuste de
  mão na pegada da barra.

**Regra que sai disso:** antes de gerar, ler a classe no livro-razão. `NOME` e
`OK+NOME` não vão pro Higgsfield.

## Renames: prontos e DESARMADOS de propósito

Os 48 limpos foram aplicados e **desfeitos** em 17/09, por decisão do Filipe: o
Hugo vai reconferir pelos MESMOS números que reportou, e rename reordena a
lista (216 das 663 posições andariam). Ficam prontos: mapa em
`renames_revisao_hugo.php`, comando `exercicios:renomear` com 6 testes, e a
linha no `docker/entrypoint.sh` comentada com a receita. O commit `28d5ded`
mostra tudo que precisa mudar junto; o `284ca3f` é o recuo.

**Ordem combinada:** instalar os vídeos (feito) → Hugo reconfere pelos números
antigos → aí sim renames + poda dos 147 + lista numerada nova pra ele.

Uma grafia foi corrigida de propósito: ele escreveu "kettblell", e o nome
aparece na tela do personal — ficou "Remada unilateral com kettlebell".

## Renames ainda pendentes (18): 4 colisões, 1 duplicado, 7 de grupo, 3 ambíguos, 2 esperando a poda

Duas das seis colisões da leitura de 15/09 **se resolvem sozinhas**, porque o
exercício com quem elas colidiam sai na poda:

- `#145 → rosca direta com halteres` colidia com #157, que **sai**.
- `#258 → leg press horizontal` colidia com #259, que **sai**.

As que continuam de pé:

| # | hoje | vira | colide com | e ainda |
|---|---|---|---|---|
| 181 | Supino fechado com halteres | supino inclinado com halteres | #40 (existe) | mudaria de Tríceps pra Peito |
| 182 | Supino fechado no smith | supino inclinado no smith | #43 (existe) | mudaria de Tríceps pra Peito |
| 274 | Cadeira flexora | cadeira extensora | #253 (existe) | ele mesmo escreveu "não é posterior" |
| 304 | Agachamento búlgaro com foco em glúteo | agachamento búlgaro | #227 (existe) | mudaria de Glúteos pra Pernas |

E **#247 e #306 viram os dois "agachamento sumô com barra"** — nome duplicado
entre dois exercícios da lista, que a leitura de 15/09 não tinha pego.

## O que falta gerar (44)

Por equipamento: Polia 16, Barra 7, Barra W 5, Halteres 4, Elástico 3, Smith 2,
Caneleira 2, e 1 cada de Máquina, TRX, Peso corporal (#359, parcial), Equipamento
e Bicicleta. **Dois terços é polia/máquina/smith**, a faixa de 42% de erro grave.

| # | exercício | equip. | queixa do Hugo | classe |
|---|---|---|---|---|
| 2 | Chest press unilateral | Máquina | mudar nome para crucifixo unilateral/ movimento está errado. | NOME+VIDEO |
| 3 | Crossover | Polia | exercício está errado | VIDEO |
| 23 | Flexão de braço no TRX | TRX | movimento errado | VIDEO |
| 31 | Pullover na polia alta | Polia | melhor movimento | VIDEO |
| 35 | Supino declinado | Barra | movimento errado | VIDEO |
| 39 | Supino inclinado | Barra | supino inclinado banco | VIDEO |
| 57 | Bom dia com barra | Barra | retirar o peso da barra | VIDEO |
| 60 | Face pull | Polia | melhorar movimento | VIDEO |
| 75 | Puxada frontal pegada neutra | Polia | banco e pegada errada | VIDEO |
| 76 | Puxada frontal pegada supinada | Polia | pegada errada | VIDEO |
| 80 | Puxador triângulo | Polia | puxada triângulo/ pegada errada | NOME+VIDEO |
| 82 | Remada baixa | Polia | movimento errado | VIDEO |
| 83 | Remada baixa com corda | Polia | remada com argola/ melhor movimento | NOME+VIDEO |
| 92 | Remada curvada pegada supinada | Barra | pegada está errada | VIDEO |
| 140 | Remada alta na polia baixa | Polia | tirar o cabo da polia de cima e melhorar o movimento | VIDEO |
| 141 | Rotação externa com elástico | Elástico | movimento errado | VIDEO |
| 142 | Rotação externa na polia | Polia | movimento errado | VIDEO |
| 143 | Rotação interna na polia | Polia | movimento errado | VIDEO |
| 155 | Rosca direta com barra W | Barra W | ajustar a barra | VIDEO |
| 160 | Rosca inversa | Barra | mudar a pegada | VIDEO |
| 166 | Rosca Scott | Barra W | nome: rosca scott com barra reta (ajustar a barra) | NOME+VIDEO |
| 167 | Rosca Scott com barra W | Barra W | ajustar a barra | VIDEO |
| 198 | Tríceps testa | Barra W | movimento errado | VIDEO |
| 199 | Tríceps testa com barra W | Barra W | movimento errado | VIDEO |
| 201 | Tríceps testa com halteres | Halteres | melhorar o movimento | VIDEO |
| 211 | Encolhimento com halteres | Halteres | melhorar movimento | VIDEO |
| 216 | Encolhimento no smith | Smith | ajustar o movimento | VIDEO |
| 238 | Agachamento frontal | Barra | passar a barra para frente | VIDEO |
| 255 | Extensão de joelho com caneleira | Caneleira | Ajustar o banco | VIDEO |
| 271 | Terra sumô com halteres | Halteres | agachamento sumô com halteres - melhorar a pegada | NOME+VIDEO |
| 277 | Extensão de quadril na polia | Polia | glúteo coice na polia - ajustar o movimento | NOME+VIDEO |
| 301 | Abdução de quadril na polia | Polia | movimento errado | VIDEO |
| 302 | Abdução em pé com elástico | Elástico | melhorar o movimento | VIDEO |
| 303 | Adução de quadril na polia | Polia | melhorar o movimento | VIDEO |
| 309 | Caminhada lateral com elástico | Elástico | deslocamento lateral com miniband - melhorar movimento | NOME+VIDEO |
| 311 | Coice de glúteo com caneleira | Caneleira | glúteo coice com caneleira - colocar caneleira, colchonete e deixar os cotovelos flexionados | NOME+VIDEO |
| 319 | Elevação de quadril no smith | Smith | ajustar o banco | VIDEO |
| 324 | Glúteo no cabo (coice) | Polia | melhorar o movimento - inclinar o corpo e aumentar a flexão e extensão do quadril | VIDEO |
| 330 | Subida no step alta para glúteo | Halteres | subida no step com halter (colocar o pé completo no step) | NOME+VIDEO |
| 371 | Pallof press | Polia | exercício errado, segura em isometria mantendo o cabo na lateral | VIDEO |
| 385 | Roda abdominal ajoelhado | Equipamento | corrigir o movimento | VIDEO |
| 386 | Rollout na barra | Barra | corrigir o movimento | VIDEO |
| 398 | Bicicleta ergométrica | Bicicleta | trocar a bicicleta | VIDEO |

## Renames pendentes (66)

Nenhum foi aplicado. Enquanto não forem, a poda dos 147 tira "Rosca direta",
"Puxada frontal", "Leg press 45°" e "Desenvolvimento militar" sem entrar os
substitutos.

| # | hoje | vira | classe |
|---|---|---|---|
| 1 | Chest press sentado | crucifixo | NOME |
| 2 | Chest press unilateral | crucifixo unilateral | NOME+VIDEO |
| 32 | Supino com halteres | supino reto com halter | NOME |
| 65 | Levantamento terra com trap bar | levantamento terra c | NOME |
| 70 | Puxada articulada na máquina | puxada frente | NOME |
| 80 | Puxador triângulo | puxada triângulo | NOME+VIDEO |
| 83 | Remada baixa com corda | remada com argola | NOME+VIDEO |
| 88 | Remada com kettlebell | remada unilateral com kettblell | NOME |
| 89 | Remada curvada | remada curvada barra | OK+NOME |
| 94 | Remada máquina | remada aberta máquina | NOME |
| 137 | Press militar estrito | desenvolvimento lateral com barra em pé | NOME |
| 139 | Remada alta | remada alta com barra | NOME |
| 144 | Rosca 21 | rosca direta com barra reta | NOME |
| 145 | Rosca 21 com halteres | rosca direta com halteres | NOME |
| 162 | Rosca martelo | rosca martelo com halteres | NOME+VIDEO |
| 166 | Rosca Scott | rosca scott com barra reta | NOME+VIDEO |
| 173 | Extensão de tríceps na máquina | máquina errada e trocar o nome por tríceps testa na máquina | NOME+VIDEO |
| 175 | Extensão de tríceps unilateral | tríceps unilateral na polia alta | NOME |
| 177 | Mergulho entre bancos | tríceps banco livre com pés suspensos | NOME+VIDEO |
| 178 | Mergulho na máquina assistida | paralela no gráviton | NOME |
| 179 | Mergulho nas paralelas | paralela livre | NOME |
| 180 | Mergulho no banco | tríceps banco livre | NOME+VIDEO |
| 181 | Supino fechado com halteres | supino inclinado com halteres | NOME |
| 182 | Supino fechado no smith | supino inclinado no smith | NOME |
| 183 | Tríceps coice bilateral | crucifixo inverso com halter | NOME |
| 188 | Tríceps francês | tríceps francês com anilha | OK+NOME |
| 220 | Afundo | avanço alternado | NOME |
| 221 | Afundo com barra | avanço alternado com barra | NOME |
| 222 | Afundo com halteres | recuo com halteres | NOME |
| 225 | Afundo reverso com barra | recuo com barra | NOME |
| 229 | Agachamento com barra alta | agachamento com barra | NOME |
| 247 | Agachamento sumô com halter | agachamento sumô com barra | NOME |
| 251 | Avanço estático | afundo com halter | NOME |
| 258 | Leg press 45° unilateral | leg press horizontal | NOME |
| 267 | Passada com barra | avanço com barra | NOME |
| 270 | Subida no step lateral | subida no banco lateral | OK+NOME |
| 271 | Terra sumô com halteres | agachamento sumô com halteres | NOME+VIDEO |
| 274 | Cadeira flexora | cadeira Extensora | NOME |
| 276 | Elevação pélvica com perna estendida | elevação pélvica solo unilateral | NOME |
| 277 | Extensão de quadril na polia | glúteo coice na polia | NOME+VIDEO |
| 285 | Levantamento terra | levantamento terra com barra | OK+NOME |
| 287 | Levantamento terra romeno unilateral | Stiff unilateral ou T ou avião | OK+NOME |
| 300 | Abdução de quadril na máquina | abdução de quadril na máquina abdutora | OK+NOME |
| 304 | Agachamento búlgaro com foco em glúteo | agachamento búlgaro | NOME |
| 306 | Agachamento sumô profundo | agachamento sumô com barra | NOME |
| 309 | Caminhada lateral com elástico | deslocamento lateral com miniband | NOME+VIDEO |
| 311 | Coice de glúteo com caneleira | glúteo coice com caneleira | NOME+VIDEO |
| 314 | Elevação de quadril (hip thrust) | elevação pélvica com barra | OK+NOME |
| 315 | Elevação de quadril com elástico | elevação pélvica com elástico | OK+NOME |
| 326 | Passada profunda para glúteo | avanço com halteres | NOME |
| 329 | Ponte de glúteo no solo | elevação pélvica solo | NOME |
| 330 | Subida no step alta para glúteo | subida no step com halter | NOME+VIDEO |
| 350 | Abdominal na máquina com carga | ajustar o erro da máquina | NOME+VIDEO |
| 352 | Abdominal oblíquo | abdominal oblíquo unilateral | OK+NOME |
| 361 | Elevação de joelhos suspenso | abdominal infra suspenso flexionando as pernas | OK+NOME |
| 362 | Elevação de pernas no banco | abdominal infra no banco | OK+NOME |
| 363 | Elevação de pernas suspenso na barra | abdominal infra suspenso com pernas estendidas | OK+NOME |
| 369 | Limpador de para-brisa suspenso na barra | abdominal pára-brisa suspenso na barra | NOME+VIDEO |
| 378 | Prancha com elevação de perna | mountain climber | OK+NOME |
| 379 | Prancha com remada (renegade) | renegade row | OK+NOME |
| 391 | Sit-up completo | abdominal supra solo completo | OK+NOME |
| 392 | Toes to bar | movimento do Crossfit | OK+NOME |
| 393 | Agachamento com salto sobre a caixa | box jump | OK+NOME |
| 395 | Assault bike | airbike | OK+NOME |
| 396 | Battle rope com agachamento | corda naval | NOME |
| 400 | Burpee com salto na caixa | burpee box jump | OK+NOME |
