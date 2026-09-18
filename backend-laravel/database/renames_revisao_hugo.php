<?php

// Renomeações pedidas pelo Hugo (personal que testa o app) na revisão dos 400
// primeiros vídeos, aplicadas em 17/09/2026 por `exercicios:renomear`.
//
// POR QUE ISTO EXISTE SEPARADO DA REGERAÇÃO DE VÍDEO: boa parte do que a lista
// dele pedia era só o RÓTULO — o vídeo estava certo, o nome é que não era o que
// o personal usa na academia. Classificação item a item em
// `revisao_hugo_livro_razao.tsv` (coluna `classe`): `NOME` e `OK+NOME` não vão
// pro Higgsfield. Ignorar isso já custou 6 gerações à toa.
//
// Aqui estão só os renames LIMPOS: sem colisão de nome, sem trocar o exercício
// de grupo muscular e sem ambiguidade. O que ficou de fora está no fim do
// arquivo, com o motivo — depende de decisão do Hugo ou da poda dos 147.
//
// A chave é o nome ATUAL no banco; o valor é o nome novo. O comando é
// idempotente: quando o nome antigo não existe mais, ele apenas relata.
return [
    // Peito
    'Chest press sentado' => 'Crucifixo',
    'Chest press unilateral' => 'Crucifixo unilateral',   // o vídeo dele segue na fila
    'Supino com halteres' => 'Supino reto com halter',

    // Costas
    'Puxada articulada na máquina' => 'Puxada frente',
    'Puxador triângulo' => 'Puxada triângulo',            // vídeo na fila (pegada)
    'Remada baixa com corda' => 'Remada com argola',      // vídeo na fila
    // Ele escreveu "kettblell"; grafia corrigida de propósito — o nome aparece
    // na tela do personal e do aluno.
    'Remada com kettlebell' => 'Remada unilateral com kettlebell',
    'Remada curvada' => 'Remada curvada barra',
    'Remada máquina' => 'Remada aberta máquina',

    // Ombros
    'Press militar estrito' => 'Desenvolvimento lateral com barra em pé',

    // Bíceps
    'Rosca 21' => 'Rosca direta com barra reta',
    'Rosca martelo' => 'Rosca martelo com halteres',      // vídeo na fila (pegada neutra)
    'Rosca Scott' => 'Rosca Scott com barra reta',        // vídeo na fila (a barra)

    // Tríceps
    'Extensão de tríceps na máquina' => 'Tríceps testa na máquina',
    'Extensão de tríceps unilateral' => 'Tríceps unilateral na polia alta',
    'Mergulho entre bancos' => 'Tríceps banco livre com pés suspensos',
    'Mergulho na máquina assistida' => 'Paralela no gráviton',
    'Mergulho nas paralelas' => 'Paralela livre',
    'Mergulho no banco' => 'Tríceps banco livre',
    'Tríceps francês' => 'Tríceps francês com anilha',

    // Pernas
    'Afundo' => 'Avanço alternado',
    'Afundo com barra' => 'Avanço alternado com barra',
    'Afundo com halteres' => 'Recuo com halteres',
    'Afundo reverso com barra' => 'Recuo com barra',
    'Agachamento com barra alta' => 'Agachamento com barra',
    'Avanço estático' => 'Afundo com halter',
    'Passada com barra' => 'Avanço com barra',
    'Subida no step lateral' => 'Subida no banco lateral',
    'Terra sumô com halteres' => 'Agachamento sumô com halteres',  // vídeo na fila (pegada)

    // Posterior / Glúteos
    'Levantamento terra' => 'Levantamento terra com barra',
    'Abdução de quadril na máquina' => 'Abdução de quadril na máquina abdutora',
    'Caminhada lateral com elástico' => 'Deslocamento lateral com miniband',  // vídeo na fila
    'Coice de glúteo com caneleira' => 'Glúteo coice com caneleira',          // vídeo na fila
    'Elevação de quadril (hip thrust)' => 'Elevação pélvica com barra',
    'Elevação de quadril com elástico' => 'Elevação pélvica com elástico',
    'Ponte de glúteo no solo' => 'Elevação pélvica solo',

    // Core
    'Abdominal na máquina com carga' => 'Abdominal supra máquina',
    'Abdominal oblíquo' => 'Abdominal oblíquo unilateral',
    'Elevação de joelhos suspenso' => 'Abdominal infra suspenso flexionando as pernas',
    'Elevação de pernas no banco' => 'Abdominal infra no banco',
    'Elevação de pernas suspenso na barra' => 'Abdominal infra suspenso com pernas estendidas',
    'Limpador de para-brisa suspenso na barra' => 'Abdominal pára-brisa suspenso na barra',
    'Prancha com remada (renegade)' => 'Renegade row',
    'Sit-up completo' => 'Abdominal supra solo completo',

    // Funcional
    'Agachamento com salto sobre a caixa' => 'Box jump',
    'Assault bike' => 'Airbike',
    'Battle rope com agachamento' => 'Corda naval',
    'Burpee com salto na caixa' => 'Burpee box jump',

    // --- 18/09: os que tinham ficado pro Hugo e o Filipe mandou aplicar ------
    'Levantamento terra com trap bar' => 'Levantamento terra com barra hexagonal',
    'Remada alta' => 'Remada alta com barra',
    'Tríceps coice bilateral' => 'Crucifixo inverso com halteres',
    'Agachamento sumô com halter' => 'Agachamento sumô com barra',
    'Elevação pélvica com perna estendida' => 'Elevação pélvica solo unilateral',
    'Extensão de quadril na polia' => 'Glúteo coice na polia',
    'Passada profunda para glúteo' => 'Avanço com halteres',
    'Subida no step alta para glúteo' => 'Subida no step com halter',
    'Prancha com elevação de perna' => 'Mountain climber',
    // Substituições: o nome novo é de um exercício que o Hugo mandou retirar.
    // O renomear apaga esse antigo (biblioteca_substituida_hugo.php) e passa o
    // nome pra este, que é o que tem o vídeo certo.
    'Rosca 21 com halteres' => 'Rosca direta com halteres',
    'Leg press 45° unilateral' => 'Leg press horizontal',
    'Levantamento terra romeno unilateral' => 'Stiff unilateral',
];

// ---------------------------------------------------------------------------
// FORA DESTE LOTE, e por quê. Nada aqui é esquecimento.
//
// 1. Esperam a PODA dos 147 rodar — o nome novo ainda pertence a um exercício
//    que só sai lá (o índice de `name` é unique, então renomear antes falha):
//    #145 Rosca 21 com halteres  -> rosca direta com halteres  (hoje é o #157)
//    #258 Leg press 45° unilateral -> leg press horizontal      (hoje é o #259)
//
// 2. COLIDEM com exercício que fica na biblioteca — decisão do Hugo, é preciso
//    saber qual dos dois fica com o nome:
//    #181 Supino fechado com halteres -> supino inclinado com halteres (#40)
//    #182 Supino fechado no smith     -> supino inclinado no smith (#43)
//    #274 Cadeira flexora             -> cadeira extensora (#253)
//    #304 Agachamento búlgaro com foco em glúteo -> agachamento búlgaro (#227)
//    #247 e #306 viram os DOIS "agachamento sumô com barra".
//
// 3. O nome novo pede outro GRUPO MUSCULAR, e renomear não move de grupo —
//    ficariam arquivados no lugar errado:
//    #65 (Costas->Posterior), #139 (Ombros->Costas), #183 (Tríceps->Ombros,
//    e é outro exercício), #276 (Posterior->Glúteos), #277 (Posterior->Glúteos),
//    #326 e #330 (Glúteos->Pernas).
//
// 4. AMBÍGUO: pode ser ele nomeando o que viu, não pedindo rename.
//    #287 "Stiff unilateral ou T ou avião" (três opções)
//    #378 "✅ mountain climber" num "Prancha com elevação de perna"
//    #392 "✅ movimento do Crossfit" — comentário, não nome
