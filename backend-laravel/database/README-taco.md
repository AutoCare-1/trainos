# Tabela de alimentos (TACO)

`database/alimentos_taco.php` é **gerado**, não escrito à mão. Ele carrega os
597 alimentos da Tabela Brasileira de Composição de Alimentos (TACO), 4ª edição
revisada e ampliada — NEPA/UNICAMP, Campinas, 2011.

## Licença

A publicação autoriza expressamente o uso, na ficha catalográfica (p. ii):

> Tabela Brasileira de Composição de Alimentos – TACO é uma publicação do NEPA.
> É permitida a reprodução total ou parcial do material, desde que seja citada
> a fonte.

Por isso a fonte é citada no cabeçalho do arquivo gerado, na migration e aqui.
Se um dia a tela mostrar esses valores em destaque, vale citar também lá.

## De onde vêm os dados

Planilha oficial: <https://nepa.unicamp.br/publicacoes/tabela-taco-excel/>

A aba usada é `CMVCol taco3` (composição centesimal, minerais, vitaminas e
colesterol). As colunas aproveitadas são as que o app mostra:

| Coluna na planilha | Campo |
| --- | --- |
| Descrição dos alimentos | `nome` |
| Energia (kcal) | `kcal` |
| Proteína (g) | `proteina_g` |
| Carboidrato (g) | `carboidrato_g` |
| Lipídeos (g) | `lipideos_g` |
| Fibra Alimentar (g) | `fibra_g` |

Todos os valores são **por 100 g**, como na fonte.

## Convenções da TACO que o import preserva

- **`NA`** (não analisado) vira `null`, **nunca 0**. A diferença importa:
  mostrar "0 g de proteína" para um alimento que não foi analisado é dado
  falso, e o personal decide em cima disso.
- **`Tr`** (traço) vira `0`.

## Como conferir se uma reimportação saiu certa

O risco real ao reimportar é desalinhar coluna (energia entrar onde deveria
entrar proteína, por exemplo). Duas checagens pegam isso:

1. `php artisan test --filter=AlimentoTacoTest` — confere a contagem (597) e
   compara valores conhecidos com a tabela publicada.
2. Relação de Atwater: `kcal ≈ 4·proteína + 4·carboidrato + 9·lipídeos`. Na
   importação atual, 512 dos 579 alimentos com dado completo ficam dentro de
   15%. As divergências são esperadas e explicáveis — bebida alcoólica (álcool
   tem 7 kcal/g e não entra nos macros), fermento em pó (carbonato, não é
   carboidrato metabolizável) e vegetais muito fibrosos (a TACO usa fatores de
   Atwater específicos). Se a taxa de divergência subir muito além disso, é
   sinal de coluna trocada, não de particularidade do alimento.

## Medidas caseiras (arquivo separado)

`database/medidas_caseiras.php` liga cada alimento às suas medidas caseiras
("1 concha = 140 g"). Fonte: **IBGE, POF 2008-2009 — Tabela de Medidas
Referidas para os Alimentos Consumidos no Brasil**, baixada em
`.../Tabela_de_Medidas_Referidas_para_os_Alimentos_Consumidos_no_Brasil/tabelamedidas_bd.zip`
(planilha, não o PDF).

Casar as duas tabelas **não é trivial** e foi onde quase entrou dado errado.
TACO e IBGE usam taxonomias diferentes, e o pareamento automático ingênuo
produz erro grosseiro:

| Erro observado | Causa |
| --- | --- |
| Gema de ovo com 45 g (peso do ovo inteiro) | IBGE não distingue a parte |
| Molho de tomate com a medida do tomate fruta | forma processada herdando a base |
| Leite em pó com "caneca 300 g" do leite líquido | `pó` tem 2 letras e o filtro de tokens descartava |
| Arroz casando com um "alimento" chamado *cozido* | linha malformada na fonte |

Por isso o arquivo marca a origem de cada alimento:

- **`curado`** — par conferido à mão, um por um. São os campeões do diário
  brasileiro (arroz, feijão, ovo, frango, pão, banana...), e justamente os que
  o pareamento automático errava, porque a TACO os nomeia com muito
  qualificador ("Frango, peito, sem pele, grelhado") e o IBGE não.
- **`automatico`** — casamento estrito: todo qualificador do nome na TACO
  precisa existir também no nome do IBGE.

A planilha do IBGE também **perde acento** em alguns nomes de medida. Como
esse nome aparece na tela do aluno e no diário que o personal lê ("2 pedaços de
queijo"), os três casos observados são corrigidos no arquivo gerado: `Copo
medio` → `Copo médio`, `File` → `Filé`, `Pedaco` → `Pedaço`. Numa reimportação,
conferir se a lista de nomes distintos ainda tem só esses três.

A fonte também traz, para alguns alimentos, **dois pesos para a mesma medida**
(a maçã Fuji vinha com "1 unidade" valendo 150 g e 320 g). O seeder faz `upsert`
por `(food_id, nome)`, então nesses casos a última linha calava a primeira em
silêncio — e a maçã ficou com 320 g, que é o peso do prato, não da fruta. Como
não dá pra saber qual das duas é a certa, a medida em conflito é **removida**:
o alimento cai no campo de gramas. `test_fonte_nao_da_dois_pesos_pra_mesma_medida`
olha o arquivo de origem (no banco o conflito já não é mais visível).

A cobertura é baixa de propósito (87 de 597 alimentos). Alimento **sem** medida
cai no campo de gramas, que já existe; alimento com medida **errada** vira
decisão errada do personal, e isso não tem conserto na tela.

## Escopo

Esta tabela existe para o aluno **registrar** o que comeu e para o personal
**ver** o padrão. Ela não vira meta nem plano alimentar: prescrição dietética é
privativa do nutricionista (Lei 8.234/91) e quem usa o TrainOS é profissional
de Educação Física. Ver `app/Support/Nutricao.php`.
