# Retomar: vídeos de demonstração dos exercícios

Ponto de partida pra próxima conversa. Escrito em 25/08/2026, depois de revisar
os 402 um a um.

## O estado, em três linhas

- 402 vídeos, todos gerados por IA. **~60 graves e ~115 médios ainda no ar.**
- O erro se concentra em **máquina e polia**: 42% de graves ali, contra 6% no
  resto (peso livre, peso corporal, cardio).
- Escrever dica melhor no prompt **não resolve** o lado de máquina. Já foi
  medido, não é palpite — ver abaixo.

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

### 1. Filmar os 129 de máquina e polia
O modelo dos vídeos é o próprio Filipe, que já cedeu a imagem, e as máquinas
existem numa academia real. É uma tarde com celular em tripé, sai melhor que
qualquer geração e não custa crédito. Enquanto não rolar, alternativas:
licenciar acervo pronto, ou voltar o boneco animado nesses (um esquema
abstrato correto ensina mais que um vídeo realista errado).

### 2. Os 5 que a regeração não resolveu
`fire-hydrant` · `agachamento-sissy` · `afundo-lateral` ·
`supino-declinado-com-halteres` · `crucifixo-declinado-com-halteres`

Estão com o vídeo ORIGINAL (errado) instalado, porque o novo não ficou melhor.
Regerar de novo é ~50/50, uns 20 créditos por tentativa. Detalhe revelador:
`supino-declinado` (barra) acertou com **exatamente o mesmo texto** que
`supino-declinado-com-halteres` errou — nessa faixa o resultado é sorteio, não
prompt.

### 3. Os médios
~115, quase todos do tipo "a variação do nome não aparece": pegada neutra sai
pronada, declinado sai inclinado, unilateral usa as duas mãos. Dá pra viver com
eles; se for tratar, tratar em lote por família.

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
