# Catálogo de alimentos e medidas caseiras

`database/alimentos_pof.php` e `database/medidas_pof.php` são **gerados**, não
escritos à mão. Vêm da Pesquisa de Orçamentos Familiares 2008-2009 do IBGE:

- **Composição nutricional**: [Tabelas de Composição Nutricional dos Alimentos
  Consumidos no Brasil](https://ftp.ibge.gov.br/Orcamentos_Familiares/Pesquisa_de_Orcamentos_Familiares_2008_2009/Tabelas_de_Composicao_Nutricional_dos_Alimentos_Consumidos_no_Brasil/)
  (`tabelacompleta.zip`) — 1.971 combinações de alimento e preparo.
- **Medidas caseiras**: [Tabela de Medidas Referidas para os Alimentos
  Consumidos no Brasil](https://ftp.ibge.gov.br/Orcamentos_Familiares/Pesquisa_de_Orcamentos_Familiares_2008_2009/Tabela_de_Medidas_Referidas_para_os_Alimentos_Consumidos_no_Brasil/)
  (`tabelamedidas_bd.zip`) — 7.771 medidas.

Ambas são publicação oficial do IBGE, de uso livre com citação da fonte.

## Por que a POF e não a TACO

A primeira versão desta tabela usava a TACO (NEPA/UNICAMP), e foi um erro. A
TACO é uma tabela de laboratório: 597 **ingredientes** analisados. Ela não tem
"macarrão cozido" — só "Macarrão, trigo, cru". Não tem pizza. Não tem lasanha.

Quem registra o almoço não comeu ingrediente. A POF mapeou justamente o
alimento **como consumido**, que é a pergunta que o diário faz. E a própria POF
compila os valores da TACO, do USDA e de rótulos (ver a aba "Código e Fonte de
Referência" da planilha): não é uma fonte concorrente, é a TACO mais o que
faltava.

O segundo motivo é a chave. As duas planilhas da POF usam **(código do
alimento, código do preparo)**, então casar composição com medida caseira é um
join exato. A versão anterior tentava casar TACO com IBGE **por nome** e errava
feio — a gema de ovo herdava o peso do ovo inteiro, o molho de tomate herdava o
do tomate fruta. Aquilo cobria 87 dos 597 alimentos. Isto cobre 1.969 de 1.971.

## O que o gerador faz além de copiar

A planilha vem em CAIXA ALTA e sem acento ("MACARRAO", "CARNE MOIDA"), e esse
nome é lido pelo aluno na hora de escolher e pelo personal no diário. O
gerador:

1. **Acentua e capitaliza.** 359 palavras saem de graça, casando com o
   vocabulário já acentuado da TACO; o resto está escrito à mão no gerador.
   Palavra desconhecida fica como veio — melhor "bacon" sem enfeite do que
   acento chutado.
2. **Concorda o preparo com o alimento.** A fonte escreve "COZIDO(A)" porque
   não sabe com o que vai concordar; a tela não pode mostrar isso. "Mandioca,
   cozida", "Miúdos, cozidos", "Macarrão, cozido". Gênero e número saem da
   primeira palavra do nome, com as exceções listadas à mão.
3. **Descarta medida que não é medida.** GRAMA, QUILO, MILILITRO e LITRO saem:
   o app já tem campo de grama, e "2 gramas de arroz" na lista é ruído.
4. **Mantém o volume no nome** de garrafa e lata: "1 lata" não diz nada quando
   a fonte distingue lata de 350 ml de lata de 473 ml.

`database/sinonimos_busca.php` é escrito à mão e entra **só** no campo de
busca: a POF chama "Pão de sal" o que quase todo mundo chama de pão francês.

## Como conferir se uma reimportação saiu certa

O risco real é desalinhar coluna — e aqui ele é maior que na TACO, porque a
ordem é outra (**lipídeos vem antes de carboidrato**). Três checagens pegam:

1. `php artisan test --filter=AlimentoPofTest` — contagem (1.971), valores
   conhecidos, e a lista fechada dos nomes de medida (nome novo numa
   reimportação é justamente o que precisa de olho humano).
2. **Relação de Atwater**: `kcal ≈ 4·proteína + 4·carboidrato + 9·lipídeos`.
   Está no teste `test_relacao_de_atwater_confirma_o_mapeamento_das_colunas`:
   abaixo de 85% dentro da margem é coluna trocada, não particularidade de
   alimento. Bebida alcoólica diverge por natureza (álcool tem 7 kcal/g e não
   entra nos macros).
3. Rodar a suíte **nos dois bancos**. O MySQL compara texto ignorando acento e
   o SQLite não: teste de busca que passa num e falha no outro já aconteceu
   aqui, e era bug de verdade.

Campos nulos são o que a fonte não traz — **nunca 0**. "0 g de proteína" para
um alimento não analisado é dado falso, e o personal decide em cima disso.

## Escopo

Esta tabela existe para o aluno **registrar** o que comeu e para o personal
**ver** o padrão. Ela não vira meta nem plano alimentar: prescrição dietética é
privativa do nutricionista (Lei 8.234/91) e quem usa o TrainOS é profissional
de Educação Física. Ver `app/Support/Nutricao.php`.
