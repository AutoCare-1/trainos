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
    // 'Encolhimento unilateral com halter' ganhou cena na revisão de 25/08 e
    // foi consolidado lá embaixo, junto com a execução que estava aqui.
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

    // --- Tríceps. Mesma categoria do leg press: a instrução existe mas não
    // descreve a montagem. Levantados lendo os 18 restantes um a um, antes de
    // gerar, justamente porque o leg press ensinou que a checagem automática
    // não pega isto.
    'Mergulho nas paralelas' => [
        // Mesmo dado errado de 'Paralelas com peso': cadastrado como peso
        // corporal, mas sem as barras não existe exercício.
        'equipamento' => 'a set of parallel dip bars',
        'execucao' => 'Apoiado nas barras paralelas com os braços estendidos e o tronco na vertical, desça flexionando os cotovelos rentes ao corpo e suba de volta.',
    ],
    'Tríceps coice na polia' => [
        // Instrução circular: "mesma mecânica do coice".
        'execucao' => 'Em pé com o tronco inclinado à frente, segurando a manopla de uma polia baixa com o braço colado ao corpo, estenda o cotovelo para trás até o braço ficar reto e volte controlado.',
    ],
    'Tríceps na máquina unilateral' => [
        'execucao' => 'Sentado na máquina de tríceps com as costas no encosto, apoie o antebraço no pad e estenda um cotovelo de cada vez até o braço ficar reto, voltando devagar.',
    ],
    'Tríceps testa com barra W' => [
        'execucao' => 'Deitado no banco reto segurando a barra W com pegada semipronada, desça a barra até a altura da testa flexionando só os cotovelos e estenda de volta.',
    ],
    'Supino fechado com halteres' => [
        'execucao' => 'Deitado no banco reto com um halter em cada mão, os halteres juntos e encostados um no outro sobre o peito, cotovelos rentes ao tronco e apontados para os pés, empurre para cima mantendo os halteres em contato e desça controlado. Os cotovelos ficam colados ao corpo o tempo todo, nunca abertos para os lados.',
    ],
    'Supino fechado no smith' => [
        'execucao' => 'Deitado no banco sob a barra guiada do smith, mãos próximas na largura dos ombros, desça a barra até a parte baixa do peito com os cotovelos rentes ao tronco e apontados para os pés, e empurre de volta. Os cotovelos ficam colados ao corpo, nunca abertos para os lados.',
    ],
    'Tríceps na polia com pegada cruzada' => [
        'execucao' => 'Em pé de frente para a polia alta, segurando as duas manoplas com os braços cruzados à frente do corpo, estenda os cotovelos para baixo abrindo os braços e volte controlado.',
    ],
    'Tríceps na polia unilateral' => [
        'execucao' => 'Em pé de frente para a polia alta segurando uma manopla com uma das mãos, cotovelo colado ao corpo, estenda o cotovelo para baixo até o braço ficar reto e controle a subida sem deixar o cotovelo abrir.',
    ],
    'Tríceps na polia pegada supinada' => [
        'execucao' => 'Em pé de frente para a polia alta, segurando a barra com as palmas viradas para cima, estenda os cotovelos empurrando a barra para baixo até os braços ficarem retos e volte controlado.',
    ],
    // As três "testa" abaixo diziam só "deitado", sem banco nem trajetória.
    'Tríceps testa com halteres' => [
        'execucao' => 'Deitado no banco reto com um halter em cada mão e os braços na vertical, desça os halteres até a lateral da testa flexionando só os cotovelos e estenda de volta.',
    ],
    // 'Tríceps testa com elástico' consolidado na revisão de 25/08, lá embaixo.
    'Tríceps testa na polia baixa' => [
        // A original só falava de "tensão no ponto mais alongado" — sensação,
        // não movimento.
        'execucao' => 'Deitado no banco reto com a cabeça voltada para a polia baixa, segurando a barra com os braços na vertical, desça as mãos até a testa flexionando só os cotovelos e estenda de volta.',
    ],

    // --- Varredura das 402 instruções (24/08/2026), depois que o leg press
    // mostrou que a checagem automática não pega este defeito. Todos abaixo
    // caem numa de duas formas:
    //   (a) a instrução explica ÊNFASE ou JUSTIFICATIVA — que músculo pega,
    //       por que a variação existe, pra quem serve — e nunca o movimento;
    //   (b) é circular: "mesma mecânica do X".
    // Nos dois casos o prompt sai praticamente só com o nome do exercício.
    // Estes vídeos JÁ FORAM GERADOS com a instrução pobre e estão no ar.

    // (b) circulares
    'Face pull com elástico' => [
        'execucao' => 'Em pé com um elástico ancorado à frente na altura do rosto, puxe as duas mãos em direção aos olhos abrindo bem os cotovelos para os lados, acima da linha das mãos, e volte controlado.',
    ],
    'Levantamento terra com halteres' => [
        'execucao' => 'Em pé com um halter em cada mão à frente das coxas, empurre o quadril para trás descendo os halteres rentes às pernas com as costas retas, e volte a ficar em pé estendendo o quadril.',
    ],
    'Rotação externa na polia' => [
        'execucao' => 'Em pé de lado para a polia, cotovelo colado ao corpo e flexionado a noventa graus, gire o antebraço para fora afastando a mão do abdômen e volte controlado.',
    ],

    // (a) ênfase/justificativa em vez de movimento
    'Rosca de bíceps sentado com halteres' => [
        'execucao' => 'Sentado na ponta do banco com um halter em cada mão e os braços estendidos ao lado do corpo, flexione os cotovelos subindo os halteres até os ombros e desça controlado.',
    ],
    'Rosca direta com barra W' => [
        'execucao' => 'Em pé segurando a barra W com pegada semipronada e os cotovelos colados ao corpo, flexione os cotovelos subindo a barra até os ombros e desça controlado.',
    ],
    'Rosca direta na polia' => [
        'execucao' => 'Em pé de frente para a polia baixa segurando a barra reta com as palmas para cima e os cotovelos colados ao corpo, flexione os cotovelos subindo a barra até os ombros e desça controlado.',
    ],
    'Barra fixa com elástico (assistida)' => [
        'execucao' => 'Pendurado na barra fixa com um elástico preso à barra apoiando um dos pés, puxe o corpo para cima até o queixo passar da barra e desça controlado até os braços estenderem.',
    ],
    'Barra fixa com peso' => [
        'equipamento' => 'a pull-up bar and a dipping belt with a weight plate',
        'execucao' => 'Pendurado na barra fixa com um cinto de lastro na cintura, puxe o corpo para cima até o queixo passar da barra e desça controlado até os braços estenderem.',
    ],
    'Barra fixa pegada neutra' => [
        'equipamento' => 'a pull-up bar with parallel neutral-grip handles',
        'execucao' => 'Pendurado na barra fixa segurando dois pegadores paralelos com as palmas viradas uma para a outra, puxe o corpo para cima até o queixo passar da barra e desça controlado.',
    ],
    'Levantamento terra com trap bar' => [
        'equipamento' => 'a hexagonal trap bar',
        'execucao' => 'Em pé dentro da barra hexagonal segurando os pegadores laterais, empurre o chão com as pernas subindo até ficar totalmente em pé com as costas retas, e desça a barra controlado.',
    ],
    'Remada no banco inclinado' => [
        'execucao' => 'Deitado de bruços num banco inclinado com o peito apoiado e um halter em cada mão pendendo em direção ao chão, puxe os halteres até a lateral do tronco e desça controlado.',
    ],
    'Remada Pendlay' => [
        'execucao' => 'Com o tronco inclinado quase paralelo ao chão e a barra apoiada no solo, puxe a barra explosivamente até o abdômen e devolva ao chão a cada repetição.',
    ],
    'Agachamento búlgaro com foco em glúteo' => [
        'execucao' => 'Em pé com um halter em cada mão e o peito do pé de trás apoiado num banco atrás do corpo, incline o tronco à frente e desça flexionando a perna da frente até o joelho de trás quase tocar o chão, e suba.',
    ],
    // 'Elevação de quadril com pés no banco' consolidado na revisão de 25/08.
    'Desenvolvimento com pegada neutra' => [
        'execucao' => 'Sentado no banco com encosto e um halter em cada mão na altura dos ombros, palmas viradas uma para a outra, empurre os halteres acima da cabeça e desça controlado.',
    ],
    'Elevação lateral sentado' => [
        'execucao' => 'Sentado na ponta do banco com um halter em cada mão ao lado do corpo, eleve os braços lateralmente até a linha dos ombros com os cotovelos levemente flexionados e desça controlado.',
    ],
    'Panturrilha com pés para dentro' => [
        'execucao' => 'Na máquina de panturrilha, com as pontas dos pés na plataforma viradas para dentro, suba na ponta dos pés e desça até alongar bem o tendão.',
    ],
    'Panturrilha com pés para fora' => [
        'execucao' => 'Na máquina de panturrilha, com as pontas dos pés na plataforma viradas para fora, suba na ponta dos pés e desça até alongar bem o tendão.',
    ],
    'Panturrilha sentado unilateral' => [
        'execucao' => 'Sentado na máquina de panturrilha com o pad sobre a coxa, apoie só a ponta de um pé na plataforma e eleve o calcanhar, descendo até alongar bem.',
    ],
    'Peck deck inclinado' => [
        'execucao' => 'Sentado no peck deck com o encosto bem inclinado e as costas apoiadas, feche os dois braços em arco à frente do peito e abra controlado.',
    ],
    'Supino com pegada neutra' => [
        'execucao' => 'Deitado no banco reto com um halter em cada mão e as palmas viradas uma para a outra, empurre os halteres para cima até quase estender os cotovelos e desça controlado.',
    ],
    'Supino com pegada fechada' => [
        'execucao' => 'Deitado no banco reto segurando a barra com as mãos na largura dos ombros, desça a barra até o peito com os cotovelos rentes ao corpo e empurre de volta.',
    ],
    'Flexão de braço inclinada' => [
        'execucao' => 'Com as mãos apoiadas na borda de um banco e os pés no chão, corpo em linha reta, desça o peito em direção ao banco flexionando os cotovelos e empurre de volta.',
    ],
    'Flexão de braço declinada' => [
        'execucao' => 'Em posição de flexão com as mãos no chão e os pés elevados num banco atrás, corpo em linha reta, desça o peito até perto do chão e empurre de volta.',
    ],
    'Flexão de braço com joelhos apoiados' => [
        'execucao' => 'Em posição de flexão com as mãos no chão e os joelhos apoiados no solo, tronco alinhado com as coxas, desça o peito até perto do chão e empurre de volta.',
    ],
    // Mergulho e supino fechado são de tríceps por causa da POSTURA, não do
    // aparelho: tronco na vertical e cotovelo colado ao corpo puxam pro
    // tríceps; tronco inclinado à frente e cotovelo aberto viram peito. Sem
    // dizer isso, o gerador desenha a versão de peito — foi o que o Filipe viu
    // ("não parecem nem de tríceps, parecem mais de peito", 24/08/2026).
    'Mergulho na máquina assistida' => [
        'execucao' => 'Apoiado nas barras paralelas da máquina assistida, com os joelhos sobre a plataforma acolchoada que ajuda a sustentar parte do peso, mantenha o tronco na vertical e os cotovelos rentes ao corpo, apontados para trás, e desça flexionando os cotovelos até noventa graus antes de subir. O tronco não inclina à frente e os cotovelos não abrem para os lados.',
    ],
    'Mergulho entre bancos' => [
        'equipamento' => 'two flat benches',
        'execucao' => 'Mãos apoiadas na borda de um banco atrás do corpo e calcanhares no outro banco à frente, costas rentes ao banco e cotovelos apontados para trás, desça o quadril flexionando os cotovelos até noventa graus e suba. Os cotovelos não abrem para os lados.',
    ],
    // Os clássicos de polia do acervo: a instrução fala do cotovelo, nunca do
    // aparelho. "Cotovelos fixos, estenda o antebraço" pode ser qualquer coisa.
    'Tríceps corda' => [
        'execucao' => 'Em pé de frente para a polia alta segurando as duas pontas de uma corda, cotovelos colados ao corpo, estenda os cotovelos empurrando a corda para baixo e separe as pontas no fim do movimento, voltando controlado.',
    ],
    'Tríceps pulley barra reta' => [
        'execucao' => 'Em pé de frente para a polia alta segurando uma barra reta com as palmas para baixo, cotovelos colados ao corpo, estenda os cotovelos empurrando a barra para baixo até os braços ficarem retos e volte controlado.',
    ],
    'Tríceps francês' => [
        'execucao' => 'Em pé segurando um halter com as duas mãos acima da cabeça, cotovelos apontados para o teto, desça o halter atrás da nuca flexionando só os cotovelos e estenda de volta.',
    ],
    'Extensão de tríceps unilateral' => [
        'execucao' => 'Em pé de frente para a polia alta segurando uma manopla com uma das mãos, cotovelo colado ao corpo, estenda o cotovelo empurrando para baixo até o braço ficar reto e volte controlado.',
    ],
    // 'Tríceps testa' consolidado na revisão de 25/08, lá embaixo.

    'Mergulho no banco' => [
        'execucao' => 'Mãos apoiadas na borda de um banco atrás do corpo e pés no chão à frente com as pernas estendidas, costas rentes ao banco e cotovelos apontados para trás, desça o quadril flexionando os cotovelos até noventa graus e suba.',
    ],
    'Encolhimento inclinado no banco' => [
        'execucao' => 'Deitado de bruços num banco inclinado com o peito apoiado e um halter em cada mão pendendo, eleve os ombros em direção às orelhas sem dobrar os cotovelos e desça controlado.',
    ],
    'Agachamento frontal no smith' => [
        'execucao' => 'Em pé sob a barra guiada do smith apoiada à frente dos ombros, com os cotovelos altos, desça agachando até as coxas ficarem paralelas ao chão e suba.',
    ],
    'Sprint na esteira' => [
        'execucao' => 'Correndo na esteira em velocidade alta, passada longa e braços acompanhando o ritmo.',
    ],

    // --- Acervo wger virando vídeo (24/08/2026). As fotos eram reais e
    // corretas, então ninguém tinha olhado a instrução com olho de prompt.

    // Puxada de polia sem "Puxada" no nome: escapou da família por causa disso.
    'Puxador triângulo' => $deFrenteProAparelho,

    'Barra fixa (pull-up)' => [
        // Cadastrado como peso corporal, mas sem a barra não há exercício —
        // terceiro caso do mesmo dado errado (ver 'Paralelas com peso').
        'equipamento' => 'a pull-up bar',
        'execucao' => 'Pendurado na barra fixa com as palmas viradas para frente e os braços estendidos, puxe o corpo para cima até o queixo passar da barra e desça controlado.',
    ],

    // Isometrias: sustentam a posição, não repetem o movimento.
    'Prancha abdominal' => [
        'execucao' => 'Apoiado nos antebraços e nas pontas dos pés, corpo em linha reta da cabeça aos calcanhares, quadril nivelado e abdômen contraído.',
        'estatico' => true,
    ],
    'Hollow hold' => [
        'execucao' => 'Deitado de barriga para cima, lombar colada no chão, braços estendidos atrás da cabeça e pernas estendidas alguns centímetros acima do solo.',
        'estatico' => true,
    ],
    'Suspensão na barra (dead hang)' => [
        'execucao' => 'Pendurado na barra fixa com os braços estendidos e o corpo relaxado, sustentando o próprio peso pelas mãos.',
        'estatico' => true,
    ],

    // --- Revisão frame a frame dos 402 (25/08/2026). Método: 5 frames por
    // vídeo extraídos com ffmpeg e julgados em tira, em vez de só o primeiro
    // quadro. Resultado: 74 graves, 119 médios, 209 OK.
    //
    // O que essa revisão ensinou, e que muda o critério deste arquivo: a
    // curadoria NÃO move o ponteiro em exercício de máquina ou polia. Os 89
    // exercícios que já tinham dica erraram na mesma proporção dos 313 sem
    // dica nenhuma (45–56% contra 46%), e os oito leg press — todos com
    // parágrafo descrevendo a máquina inteira — saíram como cadeira extensora
    // assim mesmo. A falha ali não é de compreensão, é de repertório visual:
    // o gerador não sabe desenhar aparelho de academia, e mais texto não
    // ensina. Máquina/polia se resolve filmando, não escrevendo.
    //
    // As entradas abaixo são só de peso livre, peso corporal e elástico —
    // família em que o gerador acerta a cena (6% de graves) e o erro é de
    // MOVIMENTO ESCOLHIDO: ele desenhou um exercício vizinho plausível no
    // lugar do pedido. Isso o prompt corrige.
    //
    // Todas em inglês e todas NEGANDO explicitamente o que saiu errado. A
    // negação não é ênfase: é a mesma lição da guarda de membro duplicado —
    // descrever o certo não impede o modelo de desenhar o errado, dizer que o
    // errado não pode aparecer impede.

    // Saiu elevação lateral nas três. O modelo trata "elevação de ombro" como
    // uma coisa só e escolhe a lateral, que é a mais comum nas imagens.
    'Elevação frontal' => 'He raises both dumbbells straight FORWARD, in front of his body, until the arms are horizontal at shoulder height, thumbs leading. The arms travel in the sagittal plane in front of the torso and never out to the sides: this is not a lateral raise. He stops at shoulder height and does not go overhead.',
    'Elevação frontal com barra' => 'He holds a loaded barbell with both hands in front of his thighs, palms down, and raises it straight FORWARD with the elbows locked straight, until the bar is horizontal at shoulder height. The elbows stay extended the whole time, so this is not an upright row. The barbell keeps its weight plates on both ends in every frame.',
    'Elevação frontal com anilha' => [
        'equipamento' => 'one round cast-iron weight plate, held by its rim with both hands',
        'cena' => 'He grips a single flat round weight plate by its outer rim with both hands, like a steering wheel, and raises it straight FORWARD with the elbows nearly locked until it is at shoulder height, then lowers it. He stops at shoulder height and never lifts it overhead. The plate is a solid disc with a hole in the middle and stays the same size and shape in every frame.',
    ],

    // Os quatro "declinado" saíram em banco inclinado — o oposto. Descrever a
    // inclinação em graus não bastou; o que muda é dizer onde a cabeça fica.
    'Supino declinado' => 'He lies on a DECLINE bench with his head LOWER than his hips and his feet higher than his head, hooked under the leg pads at the raised end. The bench slopes downward toward his head. It is not an incline bench and he is not sitting upright. He presses the loaded barbell straight up from his lower chest.',
    'Supino declinado com halteres' => 'He lies on a DECLINE bench with his head LOWER than his hips and his feet hooked at the raised end. The bench slopes downward toward his head, never upward. He presses both dumbbells straight up from his lower chest and lowers them back.',
    'Crucifixo declinado com halteres' => 'He lies on a DECLINE bench with his head LOWER than his hips, feet hooked at the raised end. Arms almost straight with only a small fixed bend at the elbow, he opens both dumbbells wide out to the sides in a big arc until they are level with his chest, then closes them back above his chest. The elbow angle never changes, so this is an arc, not a press.',
    'Crucifixo invertido no banco inclinado' => 'He lies face DOWN, chest resting on the pad of an incline bench, straddling it from behind so his chest is supported and his head is at the high end. Both arms hang straight down toward the floor holding dumbbells. He opens both arms out to the sides in a wide arc until they are level with his shoulders, squeezing the shoulder blades, then lowers them. He is never seated facing away from the bench.',

    // O "no chão" é a única coisa que define o exercício, e saiu num banco.
    'Supino no chão (floor press)' => 'He lies flat on his BACK on the gym floor, directly on the rubber flooring, with his knees bent and feet flat. There is no bench anywhere under his body. He presses the loaded barbell up until his arms are almost straight, and the descent stops when his upper arms touch the floor.',

    // Saiu rosca de bíceps: elástico ancorado embaixo e cotovelo flexionando.
    'Extensão de tríceps com elástico' => 'The resistance band is anchored ABOVE him, at head height or higher behind him, never under his feet. He holds the band with both hands, keeps his upper arms pinned against his ribs and completely still, and EXTENDS his elbows downward until his arms are straight. The movement opens the elbow angle; his hands never curl up toward his shoulders and this is not a biceps curl.',
    'Tríceps testa com elástico' => [
        'execucao' => 'Deitado no banco com o elástico ancorado atrás da cabeça, desça as mãos até a testa flexionando os cotovelos e estenda de volta contra a resistência.',
        'cena' => 'He lies on his back on a flat bench with the resistance band anchored on the floor behind his head. He keeps his upper arms vertical and still, bends only his elbows to bring his hands down beside his forehead, then extends the elbows until the arms are straight above his chest.',
    ],
    'Tríceps testa' => [
        'equipamento' => 'an EZ curl barbell loaded with weight plates on both ends',
        'execucao' => 'Deitado no banco reto segurando a barra W com os braços na vertical, desça a barra até a altura da testa flexionando só os cotovelos e estenda de volta.',
        'cena' => 'He lies on a FLAT bench holding a loaded EZ curl bar with both hands above his chest, arms straight. The bar has visible metal weight plates locked on both ends in every frame and is never a bare smooth tube. Keeping his upper arms vertical and motionless, he bends his elbows to lower the bar all the way down to his forehead — a large, obvious range of motion — and then extends the elbows back to straight.',
    ],

    // Saiu descalço, e sem encolher o ombro.
    'Encolhimento unilateral com halter' => [
        // A execução vinha de cima do arquivo; consolidada aqui porque duas
        // chaves iguais no mesmo array não somam — a de baixo apaga a de cima
        // em silêncio, e o exercício ficaria com prompt cego.
        'execucao' => 'Em pé com um halter numa das mãos e o braço estendido ao lado do corpo, eleve o ombro em direção à orelha sem dobrar o cotovelo e desça controlado.',
        'cena' => 'He stands wearing gym training shoes on both feet — he is never barefoot and never in socks. One dumbbell hangs at arm\'s length by his side. He lifts that shoulder straight UP toward his ear, high enough that the shoulder visibly rises and the dumbbell travels several centimetres, then lets it drop back down. The elbow stays completely straight throughout.',
    ],

    // Quadril: os três saíram como outro exercício inteiro (joelho à frente,
    // búlgaro em pé, prancha alta).
    'Elevação de quadril unilateral' => 'He lies with his upper back resting against the side of a flat bench, hips near the floor, one foot planted on the ground and the other leg extended straight out in the air. He drives his hips UPWARD until his torso and thigh form a straight line, then lowers them. He is lying down against the bench the entire time and is never standing up.',
    'Elevação de quadril com pés no banco' => [
        'execucao' => 'Deitado de costas no chão com os calcanhares apoiados num banco à frente, eleve o quadril até alinhar tronco e coxas e desça controlado.',
        'cena' => 'He lies on his BACK on the floor with both heels resting up on the seat of a flat bench, knees bent. He drives his hips upward off the floor until his body forms a straight line from shoulders to knees, then lowers them back down. He is lying on the floor the whole time; he is never standing and this is not a lunge.',
    ],
    'Fire hydrant' => 'He is on all fours on the floor, both hands and both knees down. Keeping the knee bent at ninety degrees, he lifts one knee OUT TO THE SIDE, away from the midline of his body, opening the hip sideways like a gate, then lowers it. The knee travels laterally, never forward toward his chest, and his hands stay on the floor.',
    'Abdução em pé com elástico' => 'He stands with a resistance loop band around both ankles, holding a rack upright for balance. He lifts one straight leg OUT TO THE SIDE, away from his body, far enough that the band visibly stretches and the gap between his feet opens wide, then brings it back. The sideways travel of the leg is large and obvious.',

    // Hiperextensão sem o banco romano é só uma pessoa se curvando.
    'Hiperextensão com foco em posterior' => [
        'equipamento' => 'a 45-degree back extension bench (roman chair)',
        'cena' => 'He is positioned in a 45-degree back extension bench, with the padded support against his upper thighs and his ankles locked under the rear foot rollers. His whole body is held by the apparatus. He hinges at the hips, lowering his torso toward the floor, then raises it until his body is in a straight line. The bench is visible under him in every frame; he is never standing freely on the floor.',
    ],

    'Agachamento sissy' => 'Standing and holding a rack upright with one hand for balance, he pushes his KNEES FORWARD past his toes and leans his torso BACKWARD, so that his knees, hips and shoulders stay in one straight diagonal line as he lowers. His hips never travel backward and he never folds forward at the waist: this is not a normal squat.',

    'Afundo lateral' => 'He steps wide out to ONE side and bends only that leg, sinking his hips down over that foot while the OTHER leg stays completely straight with its foot flat on the floor. The two legs are always doing different things — one deeply bent, one extended — and both knees never bend together, so this is not a sumo squat.',
];
