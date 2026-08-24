<?php

// Dicas de MONTAGEM DE CENA por exercício, injetadas no prompt por
// `php artisan exercicios:gerar-demonstracao`. Lista separada do comando pra
// dar pra revisar a curadoria sem ler código — mesmo padrão de
// database/biblioteca_podada.php.
//
// Por que isto existe: a instrução da biblioteca descreve o MOVIMENTO ("puxe
// os pegadores para baixo até a linha do peito") e nunca a MONTAGEM — de que
// lado a pessoa senta, onde o aparelho fica em relação ao corpo dela. Isso é
// conhecimento implícito de quem treina, e o gerador não tem como inferir:
// ele chuta. Nas puxadas chutou errado em 7 de 7 vídeos, colocando a pessoa
// de costas pro aparelho (revisão do Filipe, 23/08/2026).
//
// Critério pra entrar aqui: exercício em que a montagem já saiu errada numa
// revisão, ou em que ela é sabidamente ambígua. NÃO é lugar pra redescrever o
// movimento — isso é `instructions`, e repetir só polui o prompt.
//
// O texto fica em inglês porque é a parte de cena do prompt, que o modelo
// entende muito melhor nessa língua (ver o comentário de montarPrompt).

// Vale pra toda a família puxada — inclusive a puxada por trás, em que muda só
// para onde a barra desce, não para onde a pessoa olha.
$deFrenteProAparelho = 'He sits facing the machine, with the pulley tower, the bar and the weight stack directly in front of him and above his head; his chest and face are turned toward the equipment and his back is never to it.';

return [
    // ATENÇÃO: 'Puxada alta no cavalete' NÃO entra aqui, apesar do nome. É
    // levantamento em pé com barra ("puxe a barra do chão até o peito com um
    // movimento explosivo de quadril"), não puxada de polia — mandar a pessoa
    // sentar de frente pra uma torre inventaria um aparelho que o exercício
    // não usa. Lição: o nome sugere a família, a instrução decide.
    'Puxada articulada na máquina' => $deFrenteProAparelho,
    'Puxada frontal' => $deFrenteProAparelho,
    'Puxada frontal com pegada em V' => $deFrenteProAparelho,
    'Puxada frontal pegada aberta' => $deFrenteProAparelho,
    'Puxada frontal pegada neutra' => $deFrenteProAparelho,
    'Puxada frontal pegada supinada' => $deFrenteProAparelho,
    'Puxada frontal unilateral' => $deFrenteProAparelho,
    'Puxada na máquina sentado' => $deFrenteProAparelho,
    'Puxada por trás' => $deFrenteProAparelho,

    // O modelo desenhava o cotovelo abaixo da mão, o que vira rosca direta aos
    // olhos de quem só vê o vídeo.
    'Remada alta na polia' => 'His elbows lead the movement and rise above his wrists, out to the sides like wings — this is a shoulder exercise, not a biceps curl.',

    // --- Exercícios cuja instrução da biblioteca não descreve nada de visual.
    // Sem `execucao` aqui o prompt sai cego, só com o nome — e o gerador não
    // sabe o que é "agachamento pausado" nem "voador unilateral".

    // A instrução era só prescrição ("sustente pelo tempo prescrito"), que
    // instrucaoVisual() descarta com razão. Sobrava um prompt sem execução — e,
    // pior, com o "repete o movimento duas vezes" padrão, que é o oposto de uma
    // isometria. Daí o `estatico`.
    'Agachamento isométrico na parede' => [
        'execucao' => 'Costas retas apoiadas na parede, joelhos flexionados a noventa graus e coxas paralelas ao chão, braços relaxados ao lado do corpo.',
        'estatico' => true,
    ],

    'Agachamento pausado' => [
        'execucao' => 'Barra nas costas, desça até as coxas ficarem paralelas ao chão, segure parado um instante no fundo do agachamento e só então suba.',
    ],

    'Voador na máquina unilateral' => [
        'execucao' => 'Sentado na máquina de voador com as costas no encosto, feche um braço de cada vez em arco à frente do peito e retorne controlado.',
    ],

    // Instrução circular: "mesma execução do floor press". Não diz nada pra
    // quem (ou o que) nunca viu um floor press.
    'Floor press com halteres' => [
        'execucao' => 'Deitado no chão com os joelhos flexionados, empurre os halteres para cima até os braços quase estendidos; os cotovelos tocam o chão e param a descida.',
    ],

    // O campo `equipment` diz "Peso corporal", mas o exercício precisa de
    // barras paralelas e cinto de lastro. Sem o override, o prompt afirmava
    // "no equipment" e brigava com a própria descrição do movimento — daria
    // alguém fazendo mergulho no ar. É dado errado na biblioteca; corrigir o
    // seeder mudaria o que o personal lê na tela, então fica só no prompt.
    'Paralelas com peso' => [
        'equipamento' => 'a set of parallel dip bars and a dipping belt with a weight plate',
        'execucao' => 'Apoiado nas barras paralelas com os braços estendidos, desça flexionando os cotovelos com o tronco inclinado à frente e suba de volta.',
    ],

    // --- Os demais cegos da biblioteca (levantados em 23/08/2026 rodando
    // execucao() sobre os 402). Onze deles já tinham ganhado vídeo com prompt
    // cego antes deste levantamento e foram regerados.

    'Rosca 21' => [
        'execucao' => 'Em pé segurando a barra com as palmas para cima, faça a rosca em três amplitudes: só na metade de baixo, só na metade de cima e depois completa.',
    ],
    'Rosca 21 com halteres' => [
        'execucao' => 'Em pé com um halter em cada mão e as palmas para cima, faça a rosca em três amplitudes: só na metade de baixo, só na metade de cima e depois completa.',
    ],
    'Cadeira extensora unilateral' => [
        'execucao' => 'Sentado na cadeira extensora com as costas no encosto, estenda uma perna de cada vez até quase travar o joelho e desça controlado.',
    ],
    'Mesa flexora com pausa' => [
        'execucao' => 'Deitado de barriga para baixo na mesa flexora, flexione os joelhos trazendo os calcanhares em direção ao glúteo, segure parado um instante no topo e desça controlado.',
    ],
    'Elevação de quadril na máquina' => [
        'execucao' => 'Sentado na máquina de elevação de quadril, com o apoio acolchoado sobre a pelve e as costas no encosto, empurre o quadril para cima até alinhar tronco e coxas e desça controlado.',
    ],
    'Encolhimento unilateral com halter' => [
        'execucao' => 'Em pé com um halter numa das mãos e o braço estendido ao lado do corpo, eleve o ombro em direção à orelha sem dobrar o cotovelo e desça controlado.',
    ],
    'Superman no solo' => [
        'execucao' => 'Deitado de barriga para baixo com braços e pernas estendidos, eleve ao mesmo tempo os braços e as pernas do chão contraindo a lombar, e desça.',
    ],
    'Bird dog' => [
        'execucao' => 'Em quatro apoios, estenda ao mesmo tempo o braço direito à frente e a perna esquerda para trás até alinhá-los com o tronco, volte e alterne os lados.',
    ],
    'Corrida na esteira' => [
        'execucao' => 'Correndo na esteira em ritmo constante, tronco ereto e braços acompanhando a passada.',
    ],
    'Caminhada inclinada na esteira' => [
        'execucao' => 'Caminhando na esteira com a plataforma bem inclinada, passos firmes e tronco ereto, sem se apoiar no corrimão.',
    ],
    'Corrida com elevação de joelhos' => [
        'execucao' => 'Correndo no lugar, eleve alternadamente os joelhos até a altura do quadril em ritmo rápido.',
    ],
    'Bicicleta ergométrica' => [
        'execucao' => 'Sentado na bicicleta ergométrica com as mãos no guidão, pedale em ritmo constante.',
    ],
    'Pular corda alternando os pés' => [
        'execucao' => 'Pulando corda, alterne o apoio de um pé para o outro a cada giro da corda, em ritmo leve e constante.',
    ],

    // --- Leg press. Um caso diferente dos cegos: a instrução EXISTE, então
    // execucao() não acusa nada — mas ela descreve só a diferença entre as
    // variações ("base ampla recruta mais adutores e glúteos"), ou seja, que
    // músculo pega. Nunca diz que a pessoa senta na máquina e empurra uma
    // plataforma. O gerador não faz ideia do que é um leg press, e o Filipe
    // reprovou a maioria em 23/08/2026. Cada uma passa a descrever a máquina
    // inteira e só depois a variação.
    'Leg press horizontal' => [
        'execucao' => 'Sentado no leg press horizontal com as costas apoiadas no encosto e os dois pés na plataforma à sua frente, empurre a plataforma para longe até quase estender os joelhos e desça controlado, sem travar o joelho no fim.',
    ],
    'Leg press unilateral' => [
        'execucao' => 'Sentado no leg press com as costas apoiadas no encosto, apoie apenas um pé no centro da plataforma — a outra perna fica afastada dela — e empurre até quase estender o joelho, descendo controlado.',
    ],
    'Leg press 45° unilateral' => [
        'execucao' => 'Sentado no leg press inclinado a 45° com as costas apoiadas no encosto, apoie apenas um pé no centro da plataforma — a outra perna fica afastada dela — e empurre até quase estender o joelho, descendo controlado.',
    ],
    'Leg press pés afastados' => [
        'execucao' => 'Sentado no leg press com as costas apoiadas no encosto, os pés na plataforma bem afastados um do outro e as pontas viradas para fora, empurre a plataforma até quase estender os joelhos e desça controlado.',
    ],
    'Leg press pés juntos' => [
        'execucao' => 'Sentado no leg press com as costas apoiadas no encosto e os dois pés juntos no centro da plataforma, empurre a plataforma até quase estender os joelhos e desça controlado.',
    ],
    'Leg press pés altos' => [
        'execucao' => 'Sentado no leg press com as costas apoiadas no encosto e os pés apoiados na parte alta da plataforma, empurre a plataforma até quase estender os joelhos e desça controlado.',
    ],
    'Leg press pés baixos' => [
        'execucao' => 'Sentado no leg press com as costas apoiadas no encosto e os pés apoiados na parte baixa da plataforma, empurre a plataforma até quase estender os joelhos e desça controlado.',
    ],
    'Panturrilha unilateral no leg press' => [
        'execucao' => 'Sentado no leg press com o joelho estendido, apoie só a ponta de um pé na borda inferior da plataforma e mova a plataforma usando apenas o tornozelo, subindo e descendo o calcanhar.',
    ],

    // Isometrias: sustentam a posição, não repetem o movimento.
    'Hollow hold' => [
        'execucao' => 'Deitado de barriga para cima, lombar colada no chão, braços estendidos atrás da cabeça e pernas estendidas alguns centímetros acima do solo.',
        'estatico' => true,
    ],
    'Suspensão na barra (dead hang)' => [
        'execucao' => 'Pendurado na barra fixa com os braços estendidos e o corpo relaxado, sustentando o próprio peso pelas mãos.',
        'estatico' => true,
    ],
];
