<?php

// Segunda rodada de retiradas, 28/09/2026. O Hugo reviu os vídeos que foram
// refeitos e, nestes seis, o problema não é o vídeo: o exercício não se
// sustenta na biblioteca ("totalmente errado", "melhor tirar").
// Mesma mecânica da retirada anterior: entra na biblioteca_podada (os seeders
// não recriam) e o `exercicios:podar-biblioteca --revisao-hugo` apaga mesmo
// tendo vídeo, poupando o que estiver num treino ou com mídia do personal.
return [
    'Agachamento sissy',
    'Abdominal pára-brisa suspenso na barra',
    'Dragon flag',
    'Hollow hold',
    'Hollow rock',
    'Pallof press',
    // 29/09: o Filipe mandou apagar — o gerador não consegue fazer a remada
    // alta na polia; virou bíceps, tríceps e por fim rosca. Cinco tentativas.
    'Remada alta na polia baixa',
];
