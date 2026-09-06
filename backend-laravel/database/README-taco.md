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

## Escopo

Esta tabela existe para o aluno **registrar** o que comeu e para o personal
**ver** o padrão. Ela não vira meta nem plano alimentar: prescrição dietética é
privativa do nutricionista (Lei 8.234/91) e quem usa o TrainOS é profissional
de Educação Física. Ver `app/Support/Nutricao.php`.
