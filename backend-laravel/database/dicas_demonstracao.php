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

// O arquivo tem dois blocos: este, histórico, e o `$maquinas` lá embaixo, da
// revisão de 26/08. Os dois são unidos com array_replace() no fim, então uma
// chave repetida no segundo SOBRESCREVE a daqui — de propósito e à vista.
// Antes eram um array só, e chave repetida apagava a anterior em silêncio.
$base = [
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

// ---------------------------------------------------------------------------
// Revisão de 26/08/2026: máquina de perna e polia.
//
// O Filipe decidiu não filmar, então estes vão de geração mesmo sabendo que a
// família erra ~40% das vezes. O que muda aqui em relação às tentativas
// anteriores é O QUE se descreve. As dicas antigas descreviam o MOVIMENTO em
// português ("empurre a plataforma até quase estender os joelhos") e os oito
// leg press saíram como cadeira extensora assim mesmo. Estas descrevem o
// APARELHO em inglês, peça por peça, e negam nominalmente a peça errada que
// apareceu — o rolinho no tornozelo.
//
// Esse rolinho é a raiz de quase toda a família: o gerador tem um aparelho de
// perna genérico na cabeça (cadeira extensora) e cai nele em leg press, mesa
// flexora, cadeira flexora e panturrilha. Por isso "no padded roller touches
// his ankles" aparece repetido: é a negação que importa, não enfeite.
//
// Nas polias, o defeito é o cabo: some, aparece dobrado, vem de duas torres ao
// mesmo tempo ou muda de comprimento no meio do vídeo. Daí $caboUnico.
// ---------------------------------------------------------------------------

$semRolo = 'No padded roller or pad ever touches his ankles, shins or the front of his lower legs, and his feet are never hooked under or over a pad: this is not a leg extension chair.';

$legPress = 'He sits deep in a 45-degree leg press sled machine, his back and hips pressed flat into the reclined padded backrest low behind him. Both feet press FLAT against a large steel footplate that stands in front of him and above him, with heavy weight plates loaded on the sides of the sled. He bends his knees to let the whole sled travel down toward his chest, then presses the footplate away until his knees are almost straight. '.$semRolo;

$caboUnico = 'A single steel cable runs in one straight unbroken line from the handle to one pulley wheel, and it stays clearly attached to the handle in every single frame. There is never a second cable, never a cable arriving from another direction or another tower, and the handle never floats loose in his hands. The attachment keeps exactly the same shape, length and thickness from the first frame to the last.';

$corda = 'The attachment is a thick black nylon ROPE with a frayed end and a rubber stop at each end — it is not a bar and never turns into one. He grips one rope end in each hand with the palms facing each other. '.$caboUnico;

$maquinas = [

    // --- Leg press. Sete dos oito saíram como cadeira extensora.
    'Leg press 45°' => ['cena' => $legPress.' Both feet sit flat in the middle of the plate, hip width apart.'],
    'Leg press 45° unilateral' => ['cena' => $legPress.' Only ONE foot is on the plate, planted in its centre; the other leg is bent out of the way beside the sled and touches nothing.'],
    'Leg press unilateral' => ['cena' => $legPress.' Only ONE foot is on the plate, planted in its centre; the other leg is bent out of the way beside the sled and touches nothing.'],
    'Leg press pés juntos' => ['cena' => $legPress.' Both feet are placed in the very centre of the plate with the inner edges of the shoes touching each other.'],
    'Leg press pés afastados' => ['cena' => $legPress.' The feet are placed very wide apart, near the two outer edges of the plate, with the toes turned outwards.'],
    'Leg press pés baixos' => ['cena' => $legPress.' Both feet are placed low down on the plate, close to its bottom edge, so the knees travel far.'],

    // --- Cadeira extensora: o rolo fica POR CIMA do tornozelo e a perna sobe.
    'Cadeira extensora unilateral' => ['cena' => 'He sits upright in a leg extension machine, back against the tall pad, knees bent over the front edge of the seat and a padded roller resting ON TOP of his ankles, in front of his shins. He straightens ONE knee at a time, lifting the roller forward and upward until that leg is almost horizontal, then lowers it; the other foot stays down. The weight stack beside the seat visibly rises and falls with the movement.'],

    // --- Cadeira flexora sentada: coxa presa por cima, calcanhar desce PRA TRÁS.
    'Cadeira flexora' => ['cena' => 'He sits in a seated leg curl machine with a thick padded bar clamped down ACROSS THE TOP OF HIS THIGHS holding him in the seat, and a roller behind his calves low down near his ankles. He drives his heels DOWN and BACKWARD underneath the seat, bending both knees hard, then lets them come back. His legs never straighten out horizontally in front of him — that would be the opposite machine.'],
    'Cadeira flexora unilateral' => ['cena' => 'He sits in a seated leg curl machine with a thick padded bar clamped down ACROSS THE TOP OF HIS THIGHS and a roller behind his calves near the ankles. He drives ONE heel down and backward underneath the seat, bending that knee hard, while the other leg stays still. His legs never straighten out horizontally in front of him.'],

    // --- Mesa flexora: de BRUÇOS. Saiu de costas, estendendo a perna.
    'Mesa flexora' => ['cena' => 'He lies FACE DOWN, chest and stomach flat on the angled bench of a lying leg curl machine, gripping the handles under the front edge. A padded roller rests against the BACK of his ankles just above his heels. He bends both knees to pull his heels up toward his glutes in a big arc, then lowers them. He is never on his back and his legs never extend forward.'],
    'Mesa flexora unilateral' => ['cena' => 'He lies FACE DOWN on the angled bench of a lying leg curl machine, gripping the front handles, with a padded roller against the BACK of ONE ankle. He bends that single knee to pull the heel up toward his glute, then lowers it; the other leg stays flat on the bench. He is never on his back.'],

    // --- Panturrilha: almofada em cima da COXA, ponta do pé na borda.
    'Panturrilha sentado' => ['cena' => 'He sits on a seated calf raise machine with a heavily padded bar clamped down ON TOP OF HIS THIGHS, just behind his knees, and weight plates loaded on the arm of the machine. Only the BALLS of his feet rest on a small raised footplate near the floor; his heels hang off the back of it in empty air. He pushes the balls of his feet down to lift both heels as high as they go, then lets the heels sink far below the platform. His knees stay bent at ninety degrees the whole time and never straighten. '.$semRolo],
    'Panturrilha sentado unilateral' => ['cena' => 'He sits on a seated calf raise machine with a padded bar clamped down ON TOP OF HIS THIGHS and plates loaded on the machine arm. Only the ball of ONE foot rests on the small raised footplate, heel hanging off in the air; the other foot is flat on the floor to the side. He lifts that single heel as high as it goes, then lets it sink below the platform. The knee stays bent at ninety degrees. '.$semRolo],
    'Panturrilha com pés para dentro' => ['cena' => 'He stands on a standing calf raise machine with his shoulders under the two padded shoulder rests, only the BALLS of both feet on the raised step and the heels hanging off behind. The toes are turned clearly INWARD toward each other, pigeon-toed, so the heels point outward. He lifts both heels as high as they go, then lets them sink well below the step. Only the ankles move; the knees stay straight.'],
    'Panturrilha no leg press' => ['cena' => 'He sits in a 45-degree leg press sled machine with both knees almost straight and locked out. Only the BALLS of both feet rest on the very bottom edge of the large steel footplate, with the heels hanging off into empty space below it. He pushes with the balls of his feet to move the whole loaded sled a short distance, then lets his heels drop below the edge of the plate. Only the ankle joints move and the knees never bend. '.$semRolo],
    'Panturrilha unilateral no leg press' => ['cena' => 'He sits in a 45-degree leg press sled with the knee almost straight and locked. Only the ball of ONE foot rests on the bottom edge of the large steel footplate, heel hanging off into empty space; the other leg is bent away to the side, touching nothing. He pushes with that single foot to move the sled a short distance, then lets the heel drop below the plate edge. The knee never bends. '.$semRolo],

    // --- Hack e GHR: aparelhos que ele nunca desenhou.
    'Hack machine' => ['cena' => 'He STANDS upright inside a hack squat machine, his back flat against the steeply angled padded backrest behind him and both shoulders pressed up under two thick shoulder pads. His feet are flat on the angled footplate below him and weight plates are loaded on the pegs at the sides. He bends his knees to slide the whole carriage down the angled rails, then pushes back up. He is never seated and never holds handles out in front of him.'],
    'Glute ham raise' => ['cena' => 'He kneels FACING DOWN in a glute-ham developer: knees on the rear pad, both ankles clamped between two rollers behind him, and his body held in one straight line from knees to head. He lowers his torso forward and down toward the floor by straightening his knees, then pulls himself back up using the back of his thighs. He is never lying on his back and never does a sit-up.'],

    // --- Polia: bíceps. Cabo tem que vir da polia BAIXA, do chão.
    'Rosca direta na polia' => ['cena' => 'He stands facing a cable tower, a straight bar in both hands with palms up. The cable comes UP from a single pulley at FLOOR level, directly in front of his feet, so the bar is pulled downward toward the ground and he curls against that. The pulley is never above his head. '.$caboUnico],
    'Rosca invertida na polia' => ['cena' => 'He stands facing a cable tower holding a straight bar with an OVERHAND grip, knuckles facing forward and palms facing down toward the floor. The cable comes UP from a single pulley at FLOOR level in front of his feet. He curls the bar up toward his chest keeping the palms turned down the whole time. '.$caboUnico],
    'Rosca com corda na polia baixa' => ['cena' => 'He stands facing a cable tower. '.$corda.' The rope hangs from a single pulley at FLOOR level directly in front of his feet, never from the sides and never from above. He curls both rope ends up toward his shoulders.'],
    'Rosca martelo na corda' => ['cena' => 'He stands facing a cable tower. '.$corda.' The rope hangs from a single pulley at FLOOR level in front of his feet. He curls both rope ends up toward his shoulders keeping the palms facing each other the entire time, thumbs on top, like holding two hammers.'],

    // --- Polia: tríceps. Cotovelo colado ao tronco, só o antebraço desce.
    'Tríceps corda' => ['cena' => 'He stands facing a cable tower. '.$corda.' The rope hangs from a single pulley HIGH above his head. He pins both upper arms hard against his ribs so the elbows never move from his sides, and pushes both rope ends straight DOWN until the arms are completely straight, spreading the two ends apart at the bottom. Only the forearms travel. His hands stay closed around the rope in every frame and never let go.'],
    'Tríceps pulley barra reta' => ['cena' => 'He stands facing a cable tower holding a short straight bar with an overhand grip, the cable coming DOWN from a single pulley high above his head. His upper arms stay pinned against his ribs and completely motionless. Starting with the bar at chest height and the elbows bent, he pushes the bar straight DOWN until the arms are fully straight against his thighs, then lets it come back to chest height. Only the forearms move: the bar never travels above his shoulders and his shoulders never move. '.$caboUnico],
    'Tríceps na polia pegada supinada' => ['cena' => 'He stands facing a cable tower holding a short straight bar with an UNDERHAND grip, palms turned up toward the ceiling. The cable comes DOWN from a single pulley high above his head. His upper arms stay pinned against his ribs, and he pushes the bar straight DOWN from chest height until the arms are fully straight. The bar never goes above his shoulders and his shoulders never move. '.$caboUnico],
    'Tríceps na polia unilateral' => ['cena' => 'He stands facing a cable tower gripping a single small D-handle in ONE hand, palm facing in. The cable comes DOWN from a single pulley high above his head. That upper arm stays pinned against his ribs and completely still while he EXTENDS the elbow, driving the hand straight down until the arm is fully straight beside his thigh, then lets it bend back to chest height. The hand travels downward as the arm straightens — it never curls up toward the shoulder. '.$caboUnico],
    'Extensão de tríceps unilateral' => ['cena' => 'He stands facing a cable tower gripping a single small D-handle in ONE hand. The cable comes DOWN from a single pulley high above his head. The upper arm is pinned against his ribs and stays still while he EXTENDS the elbow, pushing the hand straight down until the arm is fully straight, then letting it bend back up. The movement opens the elbow angle; the hand never curls up toward the shoulder. '.$caboUnico],
    'Tríceps na polia com pegada cruzada' => ['cena' => 'He stands between two cable towers, one D-handle in each hand, the two cables coming DOWN from a pulley high on each tower and CROSSING in front of his chest so the left hand holds the right tower cable. Both upper arms stay pinned against his ribs while he pushes both hands straight DOWN and outward until both arms are fully straight beside his thighs. Both cables stay attached and visible, crossed, in every single frame — they never disappear.'],
    'Tríceps coice na polia' => ['cena' => 'He stands beside a cable tower and bends his torso far FORWARD from the hips until his chest is almost parallel to the floor, one hand braced on his knee. The other hand holds a D-handle with the upper arm raised and pinned tight against his ribs, elbow pointing backwards and upward. The cable comes from a pulley at knee height in front of him. He extends the elbow to swing the forearm straight back until the whole arm is one straight line behind him, then lets it fold back. The torso stays bent forward the entire time and he never stands upright. '.$caboUnico],
    'Tríceps corda acima da cabeça' => ['cena' => 'He stands with his BACK to a cable tower, stepped forward, torso leaning slightly ahead. '.$corda.' The rope comes from a single pulley at FLOOR level BEHIND him, so it runs up his back and over his shoulder — never down from above. He holds both rope ends beside his ears with the elbows pointing up at the ceiling, then EXTENDS both elbows to push the rope up and forward over his head until the arms are straight.'],
    'Tríceps francês na polia' => ['cena' => 'He SITS on an upright bench placed in front of a cable tower, feet flat on the floor, back straight. He holds a short bar with both hands behind his head, elbows pointing up at the ceiling. The cable comes from a single pulley at FLOOR level behind the bench. He extends both elbows to push the bar up over his head until the arms are straight, then lowers it behind his head. He is never sitting on the floor. '.$caboUnico],

    // --- Polia: costas e peito.
    'Puxada por trás' => ['cena' => 'He sits facing a lat pulldown machine with his thighs locked under the pads and a long straight bar in both hands, wide overhand grip, the cable running up to a single pulley above his head. He leans his head and neck slightly FORWARD and pulls the bar DOWN BEHIND HIS HEAD until it reaches the back of his neck, level with the base of his skull. The bar passes behind the head and never comes down in front of his face or chest. '.$caboUnico],
    'Pulldown com braços estendidos' => ['cena' => 'He stands facing a cable tower with his feet back and his torso hinged FORWARD at the hips, chest low. He holds a straight bar with both hands, and his ELBOWS STAY COMPLETELY LOCKED STRAIGHT from the first frame to the last — they never bend at any point. With straight arms he sweeps the bar down in a wide arc from head height until it touches the front of his thighs, then lets it rise back. The cable comes DOWN from a single pulley high above him. '.$caboUnico],
    'Puxador triângulo' => ['cena' => 'He sits facing a lat pulldown machine, thighs locked under the pads. The attachment is one small solid metal TRIANGLE with two short parallel handles close together in the middle of it; he grips those two handles with palms facing each other. The triangle is a single rigid piece that keeps exactly the same shape in every frame and never splits into two separate bars. He pulls it straight down to the top of his chest. '.$caboUnico],
    'Remada baixa unilateral' => ['cena' => 'He sits at a low seated row machine, feet braced on the front platform, holding a single small D-handle in ONE hand only; the other hand rests on his knee and holds nothing. The cable runs HORIZONTALLY from a pulley at floor level in front of him, level with his stomach. He pulls that single handle back to the side of his waist, driving the elbow far behind his torso, then lets the arm extend fully forward. '.$caboUnico],
    'Pullover na polia alta' => ['cena' => 'He stands facing a cable tower with his torso hinged FORWARD at the hips. He holds a straight bar with both hands and his ELBOWS STAY LOCKED STRAIGHT throughout — they never bend. With straight arms he pulls the bar down in a wide arc from above his head until it reaches the front of his thighs, then lets it travel back up. '.$caboUnico],
    'Encolhimento na polia' => ['cena' => 'He stands facing a cable tower holding a straight bar in both hands at arm\'s length in front of his thighs, arms hanging completely straight. The cable runs from the bar DOWN to a single pulley at FLOOR level and is clearly attached and taut in every frame. Keeping the elbows locked straight, he lifts both SHOULDERS straight up toward his ears as high as they will go, then lets them drop. Only the shoulders move.'],
    'Crossover' => ['cena' => 'He stands in the middle between two cable towers, one D-handle in each hand, the cables coming DOWN from a pulley high on each tower. He starts with both arms wide open out to the sides at shoulder height, then sweeps both hands forward and inward in a wide arc until they MEET AND CROSS in front of his lower chest, one wrist over the other, then opens them wide again. The hands must clearly come together in the middle — the arms are never left open the whole time. Both cables stay attached and taut in every frame.'],
    'Crossover de baixo para cima' => ['cena' => 'He stands between two cable towers, one D-handle in each hand, both cables coming UP from a pulley at FLOOR level on each tower — never from above. He starts with both arms down and wide beside his thighs, then sweeps both hands upward and inward in a wide arc until they meet in front of his face at eye height. The handles stay attached to the cables in every frame and never turn into dumbbells.'],

    // --- Polia: quadril e ombro. Estes viraram outro exercício inteiro.
    'Abdução de quadril na polia' => ['cena' => 'He stands SIDEWAYS to a cable tower, one hand holding the tower for balance, with an ankle strap around the ankle of the leg FURTHEST from the tower. The cable runs horizontally from a pulley at FLOOR level and crosses in front of his standing leg. Keeping that leg completely straight, he lifts it OUT TO THE SIDE, away from the midline of his body, as high as it will go, then brings it back across. The leg travels sideways in the frontal plane and the knee never comes up toward his chest.'],
    'Adução de quadril na polia' => ['cena' => 'He stands SIDEWAYS to a cable tower, one hand on the tower for balance, with an ankle strap on the leg NEAREST the tower. The cable runs horizontally from a pulley at FLOOR level out to the side. Keeping that leg completely straight, he pulls it INWARD across the front of his standing leg, crossing the midline of his body, then lets it travel back out to the side. The leg sweeps sideways across the body and the knee never comes up toward his chest.'],
    'Elevação frontal na polia' => ['cena' => 'He stands with his BACK to a cable tower holding a single D-handle, the cable coming UP from a pulley at FLOOR level and passing between his legs behind him. Keeping the elbow straight, he raises that arm straight FORWARD in front of his body until the hand is at shoulder height, then lowers it. The arm travels forward in front of the torso and never out to the side: this is not a lateral raise. '.$caboUnico],
    'Elevação lateral na polia' => ['cena' => 'He stands SIDEWAYS to a cable tower, upright with the torso vertical, holding a single D-handle in the hand FURTHEST from the tower. The cable comes from a pulley at FLOOR level and crosses in front of his body. Keeping the elbow almost straight, he raises that arm OUT TO THE SIDE, away from the tower, until it is horizontal at shoulder height, then lowers it. The arm travels sideways, never forward, and his torso never bends over. '.$caboUnico],
];

// ---------------------------------------------------------------------------
// Rodada 3 (26/08/2026): as 12 "outras máquinas", que ficaram de fora do recorte
// perna/polia da rodada anterior. Mesma receita que deu 88% lá: descrever o
// aparelho em inglês peça por peça e negar por nome o que saiu errado.
//
// Aqui a peça errada varia mais que na perna (lá era sempre o rolinho), então
// cada negação é específica: o pegador que flutua solto sem nada ligando ele à
// máquina, a placa de peso que nunca se move, a mão que sobra num exercício
// unilateral.
//
// $placaSobe é o denominador comum: em quase todas o defeito visível era o
// aparelho parado. Dizer que a placa sobe e desce dá ao gerador uma peça
// concreta pra animar, em vez de só "ele repete o movimento".
// ---------------------------------------------------------------------------

$placaSobe = 'The rectangular weight stack of the machine is clearly visible at its side and it visibly RISES as he pulls or pushes and sinks back down as he returns, so the machine is obviously loaded and working.';

$umaMaoSo = 'He uses ONE arm only: the working hand grips its handle while the other arm hangs relaxed at his side or rests on his thigh, clearly holding nothing at all.';

$outras = [

    'Remada máquina' => ['cena' => 'He sits at a chest-supported row machine, chest and stomach pressed against a large upright padded chest pad, feet on the footrests. Both hands grip two handles that are the ends of the machine\'s own rigid steel arms — the handles are always solidly attached to the machine by those visible arms and never float loose in the air. He pulls both handles back toward his ribs, driving the elbows far behind his torso, then lets the arms extend fully forward again: a large, obvious travel. '.$placaSobe],
    'Remada máquina unilateral' => ['cena' => 'He sits at a chest-supported row machine, chest against the upright padded chest pad. '.$umaMaoSo.' That handle is the end of the machine\'s own rigid steel arm and is always solidly attached to the machine, never floating loose. He pulls it back toward his ribs, elbow driving far behind the torso, then lets the arm extend fully forward. '.$placaSobe],

    'Extensão lombar na máquina' => ['cena' => 'He sits upright in a seated back extension machine, facing AWAY from the machine\'s column, with a thick padded bar pressed across his UPPER BACK behind his shoulder blades and a lap belt across his thighs. He pushes backwards against that back pad, extending his spine until his torso is well behind vertical, then lets it come back forward. The pad behind his back is what he pushes, and his arms are folded across his chest doing nothing. '.$placaSobe],
    'Abdominal na máquina com carga' => ['cena' => 'He sits in an abdominal crunch machine with a padded chest bar across the front of his chest and both hands gripping the handles beside his head. He crunches FORWARD, curling his ribs down toward his hips against the chest pad, then lets his torso come back up. The chest pad travels forward with him. '.$placaSobe],

    'Elevação de quadril na máquina' => ['cena' => 'He sits in a hip thrust machine with his upper back braced against a pad behind him, feet flat on a footplate in front, and a thick padded belt bar clamped down ACROSS HIS HIPS. He drives his HIPS upward against that hip pad until his torso and thighs form a straight line, then lowers them. The pad on his hips visibly rises and falls. '.$placaSobe],
    'Elevação de quadril no smith' => ['cena' => 'He lies on the FLOOR with his upper back resting against the long side of a flat bench, knees bent and feet flat on the floor. The loaded Smith machine barbell rests horizontally across his hips, padded. He drives his hips UPWARD until his torso and thighs form a straight line and the bar rises with them, then lowers back toward the floor. He is lying against the bench the whole time and is never seated on the ground.'],

    'Extensão de tríceps na máquina' => ['cena' => 'He sits in a triceps extension machine with his back against the pad and both FOREARMS resting on an angled pad in front of his chest, elbows bent and pointing down. He pushes both hands DOWN and forward, extending the elbows until the arms are straight, then lets them fold back. His hands stay below shoulder height at all times and nothing is ever pushed above his head. '.$placaSobe],
    'Tríceps na máquina unilateral' => ['cena' => 'He sits in a triceps extension machine with his back against the pad. '.$umaMaoSo.' That forearm rests on the angled pad with the elbow bent, and he pushes the hand DOWN and forward until that arm is straight, then lets it fold back. The hand stays below shoulder height and nothing is pushed above his head. '.$placaSobe],

    'Encolhimento na máquina' => ['cena' => 'He stands upright inside a shrug machine holding one handle at each side of his hips, arms hanging completely straight down. Keeping the elbows locked straight, he lifts both SHOULDERS straight UP toward his ears — the shoulders visibly travel several centimetres and his neck appears to shorten — then lets them drop all the way down. Only the shoulders move; the elbows never bend. '.$placaSobe],

    'Chest press unilateral' => ['cena' => 'He sits in a chest press machine with his back flat against the upright pad. '.$umaMaoSo.' He pushes that single handle straight FORWARD, away from his chest, until the arm is almost fully extended in front of him, then lets it come back to his chest. The hand travels forward and back in front of the torso, not out to the sides. '.$placaSobe],
    'Voador na máquina unilateral' => ['cena' => 'He sits in a pec deck machine with his back against the pad. '.$umaMaoSo.' Keeping that arm almost straight, he sweeps the single machine arm in a wide horizontal ARC from out at his side until it is in front of the centre of his chest, then lets it open back out wide. The arm sweeps sideways in an arc, it does not push forward. '.$placaSobe],

    'Supino declinado no smith' => ['cena' => 'He lies on a DECLINE bench set inside a Smith machine, with his head LOWER than his hips and his feet hooked under the pads at the raised end. The bench slopes downward toward his head; it is never an incline bench and he is never sitting upright. He presses the guided Smith barbell straight up from his lower chest and lowers it back.'],
];



// ---------------------------------------------------------------------------
// LEVA COMPLEMENTAR (31/08/2026) — esportivo, mobilidade, alongamento,
// ativação, equilíbrio, prevenção.
//
// Estes 274 caem quase todos na faixa BOA da auditoria de 25/08 (peso livre /
// peso corporal / cardio: 6% de erro grave). Só 14 são máquina/polia/smith, a
// faixa de 42%. Mas a leva traz DUAS coisas que a auditoria nunca mediu, e é
// pra elas que existe este bloco:
//
// 1. Prop que a biblioteca nunca teve. Rolo de espuma, bosu, disco
//    deslizante, argolas e bastão nunca passaram pelo gerador. Pela lição da
//    rodada 2 — o que corrige não é descrever mais, é NEGAR NOMINALMENTE a
//    peça errada — cada um desses entra com a negação do objeto em que ele
//    provavelmente vai virar (o disco vira anilha, a argola vira barra).
//
// 2. Exercício em que ficar parado é o certo. 75 dos 274 são posição
//    sustentada. Sem `estatico`, o prompt manda "He repeats the full movement
//    twice" e o gerador inventa repetição num alongamento — e, pior, a régua
//    da auditoria ("amplitude quase nula = GRAVE") se inverte: aqui amplitude
//    nula é o acerto.
// ---------------------------------------------------------------------------

$roloDeEspuma = 'The only equipment is one plain cylindrical foam roller lying on the floor, roughly 15 cm thick and 45 cm long, in a single solid colour. He rolls his body slowly back and forth over it. That cylinder is never a barbell, never a dumbbell, never a weight plate and never a bench, and there is exactly one of them.';

$bosu = 'He uses a BOSU: one rubber dome mounted on a flat circular platform, dome side UP, resting on the gym floor. It is never a flat balance board, never a step platform, never a full stability ball and never a weight plate.';

$discoDeslizante = 'Each working foot or hand rests on a small flat plastic sliding disc the size of a saucer, lying flush on the floor and sliding over it. Those discs are never weight plates, never a step platform, never a skateboard and never a wheel — they stay flat on the ground and keep the same size in every frame.';

$argolas = 'He holds two wooden gymnastic rings hanging from long straps from the ceiling, one ring in each hand. The two rings hang free and separate from each other in every single frame; they are never joined into a single bar, never a barbell and never a TRX handle, and each strap stays attached to the ceiling the whole time.';

$bastao = 'He holds one plain straight wooden stick about the thickness of a broom handle, with nothing mounted on either end. It is never a loaded barbell and never has weight plates on it.';

$elasticoIntegro = 'A single continuous elastic resistance band runs unbroken from his hands to its anchor point and stays clearly attached in every single frame. There is never a second band, it never turns into a steel cable, a rope or a chain, and it keeps the same colour and thickness from the first frame to the last.';

// Posição sustentada: a régua da auditoria se inverte aqui.
$isometricosComplementar = [
    'Alongamento de isquiotibiais sentado',
    'Alongamento de isquiotibiais em pé',
    'Alongamento de isquiotibiais deitado com elástico',
    'Alongamento de isquiotibiais em passada com apoio no banco',
    'Alongamento de quadríceps em pé',
    'Alongamento de quadríceps deitado de lado',
    'Alongamento de quadríceps na parede ajoelhado',
    'Alongamento de flexores do quadril ajoelhado',
    'Alongamento de glúteo deitado (figura 4)',
    'Alongamento de glúteo sentado na cadeira',
    'Alongamento de piriforme sentado',
    'Alongamento de adutores sentado (borboleta)',
    'Alongamento de adutores em afastamento lateral',
    'Alongamento de virilha em agachamento profundo',
    'Alongamento de panturrilha na parede',
    'Alongamento de sóleo com joelho flexionado',
    'Alongamento de tibial anterior ajoelhado',
    'Alongamento de peitoral no batente',
    'Alongamento de peitoral com as mãos atrás da cabeça',
    'Alongamento de dorsal na barra',
    'Alongamento de dorsal ajoelhado no banco',
    'Alongamento de tríceps acima da cabeça',
    'Alongamento de bíceps na parede',
    'Alongamento de deltoide posterior cruzando o braço',
    'Alongamento de rotadores externos deitado (sleeper stretch)',
    'Alongamento de manguito rotador na porta',
    'Alongamento de antebraço em extensão',
    'Alongamento de antebraço em flexão',
    'Alongamento de punho em extensão na mesa',
    'Alongamento de trapézio superior sentado',
    'Alongamento cervical lateral',
    'Alongamento de escaleno e cervical anterior',
    'Alongamento de lombar em posição fetal',
    'Alongamento de lombar com joelhos cruzados',
    'Alongamento de coluna em torção sentado',
    'Alongamento de cadeia posterior com toalha',
    'Alongamento de cadeia anterior em pé na parede',
    'Alongamento de banda iliotibial em pé',
    'Alongamento de psoas em decúbito dorsal na maca',
    'Alongamento de quadril em passada profunda com rotação',
    'Alongamento de abdome em decúbito ventral (esfinge)',
    'Alongamento de coluna suspenso na barra com pés no chão',
    'Postura da criança',
    'Postura do pombo',
    'Alongamento de ombro em rotação interna com toalha',
    'Alongamento de cadeia lateral em pé com braço acima da cabeça',
    'Apoio unipodal com olhos abertos',
    'Apoio unipodal com olhos fechados',
    'Apoio unipodal em superfície instável',
    'Prancha com apoio no bosu',
    'Prancha de Copenhague',
    'Prancha de Copenhague com joelho apoiado',
    'Ponte de glúteo com aperto de adutores',
    'Estabilização lombar em quatro apoios com elástico',
    'Estabilização escapular em prancha com apoio na parede',
    'Elevação do arco plantar (short foot)',
    'Propriocepção de joelho em semiagachamento com elástico',
    'Fortalecimento de pescoço em isometria manual',
    'Isometria de cervical em decúbito dorsal',
    'L-sit no solo',
    'L-sit nas paralelas',
    'Parada de mão na parede',
    'Front lever progressivo',
    'Back lever progressivo',
    'Streamline na parede',
    'Sustentação de tronco em posição aero',
    'Extensão de tronco em posição de guidão',
    'Suspensão em pegada aberta para escalada',
    'Equilíbrio na prancha de balanço',
    'Sustentação em agachamento profundo com apoio',
    'Mobilidade de ombro em suspensão passiva com pés apoiados',
    'Respiração diafragmática deitada',
    'Ativação de peitoral com isometria de palmas',
    'Ativação de dorsal com puxada isométrica no elástico',
    'Ativação de core com respiração 360 graus',
];

// Prop novo -> negação nominal do objeto em que ele costuma virar.
$propsComplementar = [
    'Rolo' => [
        'Mobilidade de coluna torácica sobre o rolo',
        'Liberação miofascial de quadríceps no rolo',
        'Liberação miofascial de banda iliotibial no rolo',
        'Liberação miofascial de dorsal no rolo',
        'Liberação miofascial de panturrilha no rolo',
        'Liberação miofascial de posterior de coxa no rolo',
        'Liberação miofascial de tibial anterior no rolo',
    ],
    'Bosu' => [
        'Apoio unipodal em superfície instável',
        'Agachamento no bosu com apoio bipodal',
        'Prancha com apoio no bosu',
        'Salto sobre o bosu com aterrissagem estável',
    ],
    'Disco' => [
        'Equilíbrio na prancha de balanço',
        'Apoio unipodal sobre espuma com giro de cabeça',
        'Agachamento unipodal no disco de equilíbrio',
        'Deslizamento de isquiotibiais no disco',
        'Deslizamento de calcanhar bilateral no disco',
        'Excêntrico de isquiotibiais no deslizador com uma perna',
        'Flexão de braço com abertura nos discos',
        'Prancha com abertura de pernas nos discos',
        'Agachamento com deslocamento lateral no disco',
        'Serra abdominal no disco',
        'Prancha com deslizamento de braços no disco',
    ],
    'Argolas' => [
        'Flexão de braço nas argolas',
        'Paralelas nas argolas',
        'Remada nas argolas com pés elevados',
        'Barra fixa nas argolas',
        'Rosca nas argolas',
        'Extensão de tríceps nas argolas',
        'Mergulho nas argolas',
        'Muscle-up nas argolas',
    ],
    'Bastão' => [
        'Pronossupinação com bastão lastrado',
        'Passagem de bastão pela cabeça',
        'Círculo de ombro com bastão',
        'Rotação de tronco sentado com bastão',
        'Aquecimento de agachamento com bastão acima da cabeça',
    ],
    'Elástico' => [
        'Golpe de forehand com elástico',
        'Golpe de backhand com elástico',
        'Smash aéreo simulado com elástico',
        'Chute com resistência de elástico',
        'Chute interno resistido no elástico',
        'Puxada de nado no elástico (crawl)',
        'Remada de peito no elástico',
        'Cruzado com rotação de quadril no elástico',
        'Joelhada com resistência de elástico',
        'Puxada de gola no elástico (kuzushi)',
        'Manchete simulada com elástico',
        'Swing de golfe resistido com elástico',
        'Aceleração de braço com elástico para arremesso',
        'Passada lateral defensiva com elástico na cintura',
        'Mobilidade de tornozelo com elástico',
        'Alongamento de isquiotibiais deitado com elástico',
        'Marcha de ativação com elástico nos tornozelos',
        'Ativação de dorsal com puxada isométrica no elástico',
        'Apoio unipodal com perturbação no elástico',
        'Transferência de peso em base tandem com resistência elástica',
        'Estabilização lombar em quatro apoios com elástico',
        'Rotação externa de ombro a 90 graus com elástico',
        'Inversão de tornozelo com elástico',
        'Eversão de tornozelo com elástico',
        'Dorsiflexão de tornozelo com elástico',
        'Propriocepção de joelho em semiagachamento com elástico',
        'Mobilização de quadril em decúbito dorsal com elástico',
        'Fortalecimento de pescoço com elástico em quatro direções',
    ],
];

// A instrução destes some inteira no filtro de prescrição de
// instrucaoVisual() — quase sempre por causa da palavra "sustente" — e o
// prompt sairia cego, só com o nome. Reescritas aqui em termos visuais.
$execucaoComplementar = [
    // Instrução circular: "mesma posição", "versão mais fácil" e "mesma
    // transição" só fazem sentido pra quem acabou de ler o exercício de cima.
    // O gerador recebe um exercício por vez e não tem esse "de cima".
    'Alongamento de sóleo com joelho flexionado' => 'Em passada com as mãos na parede, perna de trás com o joelho dobrado e o calcanhar firme no chão.',
    'Apoio unipodal com olhos fechados' => 'Em pé sobre uma perna só, joelho levemente flexionado, o outro pé no ar e os olhos fechados.',
    'Prancha de Copenhague com joelho apoiado' => 'Deitado de lado apoiado no antebraço, joelho da perna de cima apoiado num banco, quadril erguido em linha reta.',
    'Excêntrico de sóleo unilateral no degrau' => 'Sobre um degrau em apoio de uma perna só, joelho dobrado, descendo o calcanhar lentamente abaixo da borda.',
    'Muscle-up nas argolas' => 'Pendurado nas argolas, puxa o corpo com força e gira os punhos passando o peito acima das argolas até estender os braços.',
    'Pedalada em cadência alta na bicicleta' => 'Sentado numa bicicleta ergométrica de academia, pedalando rápido com as pernas girando em alta rotação.',
    'Tiro sentado com carga alta na bicicleta' => 'Sentado numa bicicleta ergométrica, pedalando com força e lentamente contra carga pesada, tronco firme.',
    'Sustentação de tronco em posição aero' => 'Apoiado nos antebraços no chão, quadril alto e costas retas, como quem se debruça sobre o guidão.',
    'Extensão de tronco em posição de guidão' => 'Deitado de bruços no chão, peito e ombros erguidos alguns centímetros e braços à frente.',
    'Ativação de peitoral com isometria de palmas' => 'Em pé, palmas das mãos unidas à frente do peito, cotovelos abertos, empurrando uma mão contra a outra.',
    'Ativação de dorsal com puxada isométrica no elástico' => 'Em pé, puxando um elástico até a altura do peito com os cotovelos junto ao corpo, braços parados.',
    'Aquecimento de joelho com extensão sentado sem carga' => 'Sentado na borda de um banco, estendendo e dobrando um joelho de cada vez, sem peso nenhum na perna.',
    'Prancha com apoio no bosu' => 'Prancha com os dois antebraços sobre a cúpula do bosu, corpo em linha reta da cabeça aos calcanhares.',
    'Estabilização escapular em prancha com apoio na parede' => 'Em pé inclinado com os antebraços apoiados na parede, corpo reto, empurrando a parede.',
    'Propriocepção de joelho em semiagachamento com elástico' => 'Meio agachamento com um elástico ao redor das coxas, joelhos abertos alinhados com os pés.',
    'Isometria de cervical em decúbito dorsal' => 'Deitado de costas no chão, cabeça erguida poucos centímetros com o queixo recuado.',
    'Parada de mão na parede' => 'De cabeça para baixo, mãos no chão e pés apoiados na parede, corpo estendido em linha reta.',
    'L-sit nas paralelas' => 'Sustentado nas barras paralelas com os braços travados e as pernas estendidas à frente, na horizontal.',
    'Front lever progressivo' => 'Pendurado numa barra fixa, corpo na horizontal com a barriga para cima e os joelhos recolhidos ao peito.',
    'Back lever progressivo' => 'Pendurado numa barra fixa de costas, corpo na horizontal com a barriga para baixo e os joelhos recolhidos.',
];

$complementar = [];
foreach ($propsComplementar as $equipamento => $nomes) {
    $cena = ['Rolo' => $roloDeEspuma, 'Bosu' => $bosu, 'Disco' => $discoDeslizante,
             'Argolas' => $argolas, 'Bastão' => $bastao, 'Elástico' => $elasticoIntegro][$equipamento];
    foreach ($nomes as $nome) {
        $complementar[$nome]['cena'] = $cena;
    }
}
foreach ($isometricosComplementar as $nome) {
    $complementar[$nome]['estatico'] = true;
}
foreach ($execucaoComplementar as $nome => $texto) {
    $complementar[$nome]['execucao'] = $texto;
}

return array_replace($base, $maquinas, $outras, $complementar);
