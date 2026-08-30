# Retomar: vídeos de demonstração dos exercícios

Ponto de partida pra próxima conversa. Escrito em 25/08/2026, depois de revisar
os 402 um a um.

## O estado (atualizado 26/08, depois da rodada 3)

- 402 vídeos. Já foram regerados **74**: 19 de peso livre (25/08), 43 de
  perna/polia e 12 de outras máquinas (26/08). **62 melhoraram; sobram 12
  graves.**
- Filipe decidiu **não filmar** — a saída é geração mesmo.
- Restam **918 créditos** no Higgsfield (~229 vídeos). Não é o gargalo.
- **A descoberta que destravou:** descrever o APARELHO em inglês, peça por peça,
  e NEGAR NOMINALMENTE a peça errada que apareceu. Isso levou a máquina/polia de
  ~58% para **88% de acerto** numa rodada. Ver "O que funciona" abaixo.

## O que funciona (use isto em qualquer regeração nova)

Duas negações carregaram quase todo o ganho da rodada 2:

- **Perna:** `No padded roller or pad ever touches his ankles, shins or the
  front of his lower legs (...) this is not a leg extension chair.` O gerador
  tem uma cadeira extensora genérica na cabeça e cai nela em leg press, mesa
  flexora e panturrilha. Negar o rolinho resolveu quase tudo.
- **Polia:** `A single steel cable runs in one straight unbroken line from the
  handle to one pulley wheel and stays clearly attached in every single frame.
  There is never a second cable (...) The attachment keeps exactly the same
  shape, length and thickness from the first frame to the last.` Resolve cabo
  sumindo, cabo de duas torres e corda que encurta.

- **Unilateral:** descrever o que a OUTRA mão/perna faz, não só dizer que uma
  trabalha — `the other arm hangs relaxed at his side, clearly holding nothing
  at all`. Pegou 3 de 3 na rodada 3, contra 0 de 7 na rodada 2.
- **Aparelho parado:** `The rectangular weight stack visibly RISES as he pulls
  and sinks back down.` Dá uma peça concreta pra animar; metade dos defeitos de
  máquina era simplesmente nada se mexendo.

Descrever o movimento em português (campo `execucao`) continua não adiantando.
O que muda o resultado é o campo `cena`, em inglês, com a negação.

## Onde está tudo

| O quê | Onde |
|---|---|
| Veredito item a item dos 402 | `database/revisao_demonstracoes_2026-08-25.md` |
| Relatório visual (com as tiras de frames) | https://claude.ai/code/artifact/36c109e5-bd01-48bc-933b-35677c0384c0 |
| Curadoria de prompt | `database/dicas_demonstracao.php` |
| Vídeos (fora do git, 416 MB) | `public/uploads/exercise-demos/` |
| Originais dos 19 regerados | `public/uploads/_backup-2026-08-25/` |

## A medição que fecha a questão da curadoria

Os 89 exercícios com dica escrita à mão erraram tanto quanto os 313 sem dica:

| Tipo de dica | n | % erro |
|---|---|---|
| Cena, em inglês | 11 | 45% |
| Execução, em português | 71 | 56% |
| Execução + equipamento | 7 | 42% |
| Nenhuma | 313 | 46% |

Os oito leg press têm um parágrafo curado descrevendo a máquina inteira
("sentado no leg press com os dois pés na plataforma") e sete saíram como
cadeira extensora. **Não gastar crédito regerando máquina/polia esperando
resultado diferente.**

## O que fazer a seguir, na ordem que eu faria

### 1. Os 12 que continuam errados (originais mantidos)

Da rodada 2 (perna/polia): `cadeira-flexora`, `cadeira-flexora-unilateral`
(saem como extensora — o caso mais teimoso da biblioteca),
`panturrilha-com-pes-para-dentro`, `puxada-por-tras`, `elevacao-frontal-na-polia`.

Da rodada 1 (peso livre): `fire-hydrant`, `agachamento-sissy`, `afundo-lateral`,
`supino-declinado-com-halteres`, `crucifixo-declinado-com-halteres`.

Da rodada 3 (instalados, mas fracos): `remada-maquina-unilateral` (aparelho
certo, ainda bilateral) e `encolhimento-na-maquina` (ombro não sobe).

Vale uma rodada nova com negação mais dura, agora que se sabe que a negação
nominal é o que funciona. ~48 créditos.

### 2. O problema do "unilateral"

Sete vídeos ficaram com o aparelho certo mas usando as duas pernas/mãos:
`leg-press-unilateral`, `leg-press-45-unilateral`, `mesa-flexora-unilateral`,
`cadeira-extensora-unilateral`, `panturrilha-sentado-unilateral`,
`panturrilha-unilateral-no-leg-press`, `remada-baixa-unilateral`. Já estão
instalados (o aparelho certo vale mais que o anterior), mas se for atacar,
o padrão é o mesmo: negar nominalmente — "the other foot is lifted completely
off the plate and rests on the floor beside it".

### 3. O acessório que não vira corda

`triceps-corda`, `rosca-martelo-na-corda`, `triceps-corda-acima-da-cabeca`,
`puxador-triangulo` — execução certa, mas o acessório sai como barra. A
descrição da corda já está bem detalhada e mesmo assim não pegou; pode ser
limite do gerador.

### 4. Os médios que sobraram
~110, quase todos "a variação do nome não aparece": pegada neutra sai pronada,
declinado sai inclinado. Dá pra viver com eles.

## Como gerar (o comando artisan NÃO gera)

`HIGGSFIELD_KEY_ID/SECRET` não estão no `.env`, então
`exercicios:gerar-demonstracao` só serve pra montar e conferir prompt com
`--dry-run`. A geração real sai pelo **conector MCP do Higgsfield**:

- modelo `seedance_2_0_mini`, `resolution: "480p"`, `duration: 4`,
  `aspect_ratio: "9:16"`, `generate_audio: false`
- as 6 fotos de `storage/app/private/higgsfield/referencias` como
  `image_references`
- **4 créditos** por vídeo (`seedance_2_0` fast custa 6, `seedance_2_5` custa 10)

Duas armadilhas:
1. O default de resolução de todo modelo é **720p**. Sem `resolution` explícito,
   custa 2,5× mais.
2. A primeira submissão costuma ser recusada com uma recomendação de preset
   cinematográfico. Não cobra nada, mas só passa reenviando com
   `declined_preset_id` (o id vem no erro).

## Como revisar (e como não estourar o limite)

O método que funcionou: extrair 5 frames por vídeo e julgar a tira, não o
primeiro quadro.

```bash
ffmpeg -v error -i VIDEO.mp4 \
  -vf "select='eq(n\,4)+eq(n\,26)+eq(n\,48)+eq(n\,70)+eq(n\,92)',scale=310:540,tile=5x1" \
  -frames:v 1 -q:v 4 TIRA.jpg -y
```

**Fazer em conversas separadas, ~100 vídeos por conversa.** Revisar os 402 numa
conversa só consumiu 84% do limite da sessão, porque cada imagem aberta fica na
memória e é relida a cada resposta seguinte. Em lotes, a memória zera entre eles
e o custo é uma fração.

## Duas coisas que não são deste assunto mas ficam pendentes

- Storage R2 decidido mas não executado: os `.mp4` estão fora do git, então quem
  clona não recebe vídeo nenhum. Código pronto
  (`exercicios:publicar-demonstracoes`, `config/demonstracoes.php`), falta criar
  bucket e credenciais.
- 3 arquivos de E2E untracked desde 27/07 (`SeedE2e.php`, `frontend/e2e/`,
  `playwright.config.ts`), deixados de propósito — são anteriores ao redesign e
  os seletores provavelmente quebram.
