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

    // ---- Segunda lista do Hugo (13/10/2026, numeração de 509) ----
    // Só os pedidos que são APENAS nome (o vídeo já está certo). Nome novo é o
    // texto dele com a primeira letra maiúscula; corrigidos só erros de digitação
    // (spriting, bozu, alongamneto). Livro-razão: revisao_hugo_2026-10-13.tsv.
    // Ficam DEPOIS dos antigos de propósito: alguns encadeiam (Chest press
    // sentado -> Crucifixo -> Crucifixo na máquina) e o comando roda em ordem.
    'Crucifixo' => 'Crucifixo na máquina', // #2
    'Crucifixo reto' => 'Crucifixo reto com halter', // #5
    'Crucifixo unilateral' => 'Crucifixo unilateral na máquina', // #6
    'Flexão de braço inclinada' => 'Flexão de braço inclinado no banco', // #11
    'Pullover' => 'Pullover deitado com halter', // #15
    'Pullover na polia alta' => 'Pullover na polia alta com barra', // #16
    'Supino inclinado' => 'Supino inclinado com barra', // #19
    'Supino reto' => 'Supino reto com barra', // #24
    'Supino reto com halter' => 'Supino reto com halteres', // #25
    'Puxada frontal pegada neutra' => 'Puxada neutra', // #37
    'Puxada frontal pegada supinada' => 'Puxada aberta', // #38
    'Remada baixa' => 'Remada baixa com triângulo', // #42
    'Remada curvada barra' => 'Remada curvada barra pronada', // #45
    'Remada curvada com halteres' => 'Remada curvada pronada com halteres', // #46
    'Remada invertida no TRX' => 'Remada pronada no TRX', // #48
    'Remada no banco inclinado' => 'Remada neutra com halter no banco inclinado', // #49
    'Remada serrote' => 'Remada unilateral com halter (serrote)', // #50
    'Desenvolvimento alternado com halteres' => 'Desenvolvimento pegada neutra alternada em pé', // #56
    'Desenvolvimento Arnold' => 'Desenvolvimento Arnold em pé', // #57
    'Desenvolvimento com halteres' => 'Desenvolvimento lateral com halteres sentado', // #58
    'Desenvolvimento em pé com halteres' => 'Desenvolvimento lateral com halteres em pé', // #59
    'Desenvolvimento na máquina' => 'Desenvolvimento lateral na máquina', // #61
    'Desenvolvimento no smith' => 'Desenvolvimento lateral no smith em pé', // #62
    'Elevação lateral' => 'Elevação lateral com halteres', // #66
    'Tríceps francês unilateral' => 'Tríceps francês unilateral com halter', // #108
    'Hack machine' => 'Agachamento hack', // #153
    'Subida no step com halter' => 'Subida no caixote', // #160
    'Stiff' => 'Stiff com barra', // #171
    'Elevação de quadril no smith' => 'Elevação pélvica no smith', // #189
    'Elevação de quadril unilateral' => 'Elevação pélvica unilateral', // #190
    'Elevação pélvica com elástico' => 'Elevação pélvica solo com elástico', // #192
    'Abdominal declinado' => 'Abdominal supra no banco declinado', // #205
    'Deslocamento lateral (skater)' => 'Salto lateral', // #258
    'Pular corda alternando os pés' => 'Pular corda', // #266
    'Aceleração de 5 metros com mudança de direção' => 'Deslocamento frontal e posterior', // #279
    'Desaceleração rotacional com medicine ball para golfe' => 'Rotação de tronco com medicine ball', // #294
    'Deslocamento defensivo lateral com toque no cone' => 'Deslocamento lateral com toque no cone', // #295
    'Educativo de corrida: dribling baixo' => 'Sprinting estacionário', // #298
    'Equilíbrio na prancha de balanço' => 'Equilíbrio no bosu', // #301
    'Golpe reto com halteres leves' => 'Sombra com halteres', // #309
    'Rotação de tronco com anilha em guarda' => 'Rotação de tronco com anilha', // #329
    'Polichinelo com abertura de braços em Y' => 'Polichinelo', // #368
    'Alongamento dinâmico do flexor de quadril' => 'Afundo sem carga', // #371
    'Deslizamento de escápula na parede (wall slide)' => 'Mobilidade de ombro YW na parede', // #377
    'Liberação miofascial de banda iliotibial no rolo' => 'Liberação miofascial na posterior', // #381
    'Rotação em quatro apoios com mão na nuca' => 'Mobilidade torácica rotação em 3 apoios', // #406
    'Apoio unipodal com perturbação no elástico' => 'Apoio unipodal com elástico', // #418
    'Cegonha com passagem de objeto' => 'Apoio unipodal com passagem de objeto', // #422
    'Deslocamento sobre linha com obstáculos baixos' => 'Escada de agilidade', // #423
    'Passada com pausa em apoio único' => 'Recuo com elevação de quadril', // #427
    'Subida no step com pausa em apoio único' => 'Subida no step com elevação de quadril', // #431
    'Descida controlada do step em apoio único' => 'Mobilidade de tornozelo no step', // #436
    'Estabilização lombar em ponte com marcha' => 'Ponte alternando as pernas', // #445
    'Rotação externa de ombro a 90 graus com elástico' => 'Abdução horizontal do ombro a 90 graus com elástico', // #463
    'Alongamento de quadril em passada profunda com rotação' => 'Mobilidade de quadril com rotação de tronco', // #501
    'Alongamento de virilha em agachamento profundo' => 'Alongamento de adutores agachado', // #507
    'Postura da criança' => 'Balasana - postura de criança', // #508

    // 13/10, dependem de uma retirada: o nome passa do #256 ao #253 e do #397
    // ao #503 (biblioteca_substituida_hugo.php apaga o antigo). O #3 sai junto
    // com o #4 pra não ficarem dois crucifixos inclinados lado a lado.
    'Crucifixo declinado com halteres' => 'Crucifixo inclinado com halter', // #3
    'Corrida com calcanhar no glúteo' => 'Corrida estacionária', // #253
    'Alongamento de sóleo com joelho flexionado' => 'Mobilidade de tornozelo na parede', // #503
];
