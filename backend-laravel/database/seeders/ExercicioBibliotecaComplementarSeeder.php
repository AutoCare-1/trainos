<?php

namespace Database\Seeders;

use App\Models\Exercise;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Terceira leva da biblioteca de exercícios, pedida pelo personal em 31/08/2026:
 * com 402 itens ele ainda ficava sem repertório pra montar treino.
 *
 * O que ESTA leva deliberadamente não faz: ressuscitar os 244 nomes cortados em
 * 22/08 (database/biblioteca_podada.php). Aquela poda tinha critério escrito —
 * variação quase idêntica, nota de prescrição, equipamento que academia comum
 * não tem, exótico demais — e voltar atrás nela em silêncio só devolveria o
 * problema que ela resolveu (achar o exercício certo no meio de 646).
 *
 * Por consequência, quase nada aqui é musculação clássica: os 13 grupos antigos
 * já estavam saturados (49 de Pernas, 48 de Costas, 46 de Peito), e toda
 * "variação nova" que sobrava caía no critério (a) do corte. O que faltava de
 * verdade era território que a biblioteca nunca teve:
 *
 *  - Esportivo: preparo específico pra tênis, corrida, futebol, natação, luta,
 *    vôlei/basquete, ciclismo, golfe, escalada, surf e raquete de praia.
 *  - Mobilidade / Alongamento / Ativação: o começo e o fim de toda sessão, que
 *    o personal prescrevia de cabeça porque não existia no app.
 *  - Equilíbrio / Prevenção: aluno idoso, pós-lesão e prevenção de recidiva.
 *  - Reforço de musculação/hipertrofia só onde havia buraco real (calistenia,
 *    argolas, deslizadores, aparelhos que a biblioteca não citava).
 *
 * Mesmas duas regras da leva anterior:
 *  1. Nada de foto — cai no fallback de animação do ExerciseAnimation, que
 *     escolhe o padrão pelo nome/grupo (frontend/lib/exercisePatterns.ts). Os
 *     grupos novos ganharam padrão próprio lá ('stretch' e 'balance'), senão
 *     alongamento e equilíbrio animariam como figura genérica balançando.
 *  2. O campo `equipment` alimenta App\Support\Progressao. Todo equipamento
 *     novo daqui (bastão, rolo, bosu, disco, argolas, toalha, cadeira) está
 *     mapeado lá com incremento zero — sem isso o app sugeriria "+2,5 kg" num
 *     alongamento de panturrilha na parede.
 *
 * Complementa os dois seeders anteriores em vez de substituí-los: só insere
 * nome que ainda não existe (ver run()).
 */
class ExercicioBibliotecaComplementarSeeder extends Seeder
{
    public function run(): void
    {
        // De propósito não é updateOrCreate, pelo mesmo motivo do seeder
        // anterior: um nome repetido sobrescreveria o registro do ExerciseSeeder
        // e apagaria a foto real dele.
        $jaExistem = Exercise::pluck('name')->all();
        $jaExistem = array_combine($jaExistem, $jaExistem) ?: [];

        // Trava contra a leva nova reintroduzir pela porta dos fundos algo que a
        // curadoria de 22/08 tirou. Se um nome daqui colidir com a lista podada,
        // é bug de curadoria desta leva — melhor não inserir do que desfazer a
        // poda sem ninguém perceber.
        $podados = array_flip(require database_path('biblioteca_podada.php'));

        $novos = [];
        foreach (self::linhas() as [$nome, $grupo, $equipamento, $instrucoes]) {
            if (isset($jaExistem[$nome]) || isset($podados[$nome])) {
                continue;
            }
            $novos[] = [
                'name' => $nome,
                'muscle_group' => $grupo,
                'equipment' => $equipamento,
                'instructions' => $instrucoes,
            ];
        }

        foreach (array_chunk($novos, 100) as $lote) {
            Exercise::insert(array_map(fn ($ex) => $ex + [
                'id' => (string) Str::uuid(),
                'created_at' => now(),
            ], $lote));
        }
    }

    /** @return array<int, array{0: string, 1: string, 2: string, 3: string}> [nome, grupo, equipamento, instruções] */
    public static function linhas(): array
    {
        return [
            // ---- Esportivo -----------------------------------------------------
            // Preparo específico. O grupo é o esporte de origem do gesto, não o
            // músculo: o personal chega aqui procurando "o que dou pro aluno que
            // joga tênis", não "o que dou pra deltoide posterior".

            // Tênis e raquete
            ['Rotação de tronco explosiva na polia (saque)', 'Esportivo', 'Polia', 'Pés fixos, gire o tronco puxando o cabo de baixo para cima como no saque.'],
            ['Golpe de forehand com elástico', 'Esportivo', 'Elástico', 'Elástico preso atrás, reproduza a trajetória do forehand liderando com o quadril.'],
            ['Golpe de backhand com elástico', 'Esportivo', 'Elástico', 'Puxe o elástico no plano do backhand, terminando com o tronco de frente para a rede.'],
            ['Saque de tênis com medicine ball', 'Esportivo', 'Medicine ball', 'Arremesse a bola para cima e à frente reproduzindo a extensão do saque.'],
            ['Arremesso rotacional de medicine ball na parede', 'Esportivo', 'Medicine ball', 'De lado para a parede, gire o quadril e arremesse a bola na altura do peito.'],
            ['Desaceleração lateral com passo de recuperação', 'Esportivo', 'Peso corporal', 'Desloque-se de lado, freie no pé externo e volte ao centro em um passo.'],
            ['Split step com salto', 'Esportivo', 'Peso corporal', 'Pequeno salto com aterrissagem em base larga, pronto para sair para qualquer lado.'],
            ['Passo cruzado para a rede', 'Esportivo', 'Peso corporal', 'Cruze a perna de trás à frente e avance em diagonal, como na subida à rede.'],
            ['Deslocamento em leque na quadra', 'Esportivo', 'Peso corporal', 'Saia do centro para cinco pontos diferentes e volte, sempre de frente para a rede.'],
            ['Recuo em três passos para o fundo', 'Esportivo', 'Peso corporal', 'Recue em passos cruzados até a linha de fundo mantendo o tronco de frente.'],
            ['Puxada rotacional na polia alta (topspin)', 'Esportivo', 'Polia', 'Puxe o cabo de cima para baixo em diagonal, acompanhando com a rotação do tronco.'],
            ['Freio excêntrico de ombro na polia (pós-saque)', 'Esportivo', 'Polia', 'Deixe o cabo puxar o braço para cima e freie a descida em três segundos.'],
            ['Pronossupinação com bastão lastrado', 'Esportivo', 'Bastão', 'Cotovelo apoiado a 90 graus, gire o bastão para dentro e para fora sem mexer o ombro.'],
            ['Smash aéreo simulado com elástico', 'Esportivo', 'Elástico', 'Elástico preso acima da cabeça, reproduza o movimento do smash com o braço estendido.'],
            ['Deslocamento em quadra pequena com giro', 'Esportivo', 'Peso corporal', 'Dois passos laterais, giro de 180 graus e volta, como no padel e no beach tennis.'],

            // Corrida
            ['Educativo de corrida: skipping A', 'Esportivo', 'Peso corporal', 'Avance pisando na ponta do pé, subindo o joelho até a altura do quadril a cada passo.'],
            ['Educativo de corrida: skipping B', 'Esportivo', 'Peso corporal', 'Suba o joelho e estenda a perna à frente antes de puxar o pé de volta ao solo.'],
            ['Educativo de corrida: dribling baixo', 'Esportivo', 'Peso corporal', 'Passos curtos e rápidos na ponta do pé, avançando poucos centímetros por passo.'],
            ['Passada saltada (bounding)', 'Esportivo', 'Peso corporal', 'Passadas longas e saltadas, buscando tempo no ar em vez de velocidade.'],
            ['Marcha atlética no plano', 'Esportivo', 'Peso corporal', 'Caminhe rápido sem tirar os dois pés do chão, girando o quadril a cada passo.'],
            ['Tiro em subida na rampa', 'Esportivo', 'Peso corporal', 'Sprint curto em subida, com tronco inclinado à frente e braços ativos.'],
            ['Corrida em curva fechada', 'Esportivo', 'Peso corporal', 'Corra em círculo apoiando mais no pé externo, alternando o sentido a cada volta.'],
            ['Aterrissagem de salto em profundidade (drop jump)', 'Esportivo', 'Step', 'Desça do caixote, aterrisse suave e salte imediatamente para cima.'],
            ['Salto alternado em profundidade', 'Esportivo', 'Step', 'Desça do caixote e salte de volta apoiando em uma perna de cada vez.'],
            ['Arranque de 10 metros a partir da posição deitada', 'Esportivo', 'Peso corporal', 'Deitado de bruços, levante e acelere o mais rápido possível por dez metros.'],

            // Futebol
            ['Condução de bola em ziguezague', 'Esportivo', 'Bola', 'Conduza a bola entre cones alternando o pé de toque, sem perder o controle.'],
            ['Chute com resistência de elástico', 'Esportivo', 'Elástico', 'Elástico no tornozelo, execute o gesto do chute vencendo a resistência.'],
            ['Chute interno resistido no elástico', 'Esportivo', 'Elástico', 'Elástico puxando para fora, leve a perna para dentro como no passe de trivela.'],
            ['Deslocamento defensivo lateral com toque no cone', 'Esportivo', 'Peso corporal', 'Passos laterais em posição baixa tocando o cone de cada lado.'],
            ['Aceleração de 5 metros com mudança de direção', 'Esportivo', 'Peso corporal', 'Acelere cinco metros, freie e saia na direção oposta sem passo extra.'],
            ['Salto de cabeceio com aterrissagem', 'Esportivo', 'Peso corporal', 'Salto vertical com contramovimento e aterrissagem amortecida nos dois pés.'],
            ['Freio de desaceleração em uma perna', 'Esportivo', 'Peso corporal', 'Corra três passos e pare apoiando só uma perna, segurando o joelho alinhado.'],
            ['Giro de 180 graus com arranque', 'Esportivo', 'Peso corporal', 'Gire o corpo meia volta e saia em aceleração no primeiro passo.'],

            // Natação
            ['Puxada de nado no elástico (crawl)', 'Esportivo', 'Elástico', 'Tronco inclinado à frente, reproduza a braçada do crawl puxando o elástico.'],
            ['Puxada de nado na polia deitado', 'Esportivo', 'Polia', 'Deitado de bruços no banco, puxe os cabos no trajeto da braçada até a coxa.'],
            ['Batida de pernas no banco', 'Esportivo', 'Peso corporal', 'Deitado de bruços com as pernas para fora do banco, bata alternando curto e rápido.'],
            ['Rotação de ombro em decúbito ventral (nadador)', 'Esportivo', 'Peso corporal', 'De bruços, leve os braços da cintura até acima da cabeça sem encostar no chão.'],
            ['Streamline na parede', 'Esportivo', 'Peso corporal', 'Costas na parede, braços estendidos acima da cabeça, alinhe orelha entre os braços.'],
            ['Remada de peito no elástico', 'Esportivo', 'Elástico', 'Reproduza a braçada do nado peito abrindo e fechando os braços à frente do tronco.'],
            ['Tríceps de finalização de braçada na polia', 'Esportivo', 'Polia', 'Tronco inclinado, estenda o cotovelo até passar a mão da linha do quadril.'],

            // Luta e boxe
            ['Golpe reto com halteres leves', 'Esportivo', 'Halteres', 'Em guarda, alterne socos retos girando o pé de trás a cada golpe.'],
            ['Cruzado com rotação de quadril no elástico', 'Esportivo', 'Elástico', 'Elástico preso atrás, gire quadril e tronco para lançar o cruzado.'],
            ['Joelhada com resistência de elástico', 'Esportivo', 'Elástico', 'Elástico na coxa, leve o joelho à frente e para cima como na joelhada em clinch.'],
            ['Sprawl', 'Esportivo', 'Peso corporal', 'Da guarda, jogue as pernas para trás caindo em prancha e volte de pé.'],
            ['Ponte de luta (ponte de pescoço assistida)', 'Esportivo', 'Peso corporal', 'Deitado, eleve o quadril apoiando parte do peso nas mãos, sem forçar a cervical.'],
            ['Puxada de gola no elástico (kuzushi)', 'Esportivo', 'Elástico', 'Puxe o elástico em diagonal para baixo, como ao desequilibrar o adversário.'],
            ['Levantada técnica (technical stand-up)', 'Esportivo', 'Peso corporal', 'Sentado, apoie uma mão e o pé oposto e levante mantendo a guarda à frente.'],
            ['Rotação de tronco com anilha em guarda', 'Esportivo', 'Anilha', 'Em base de luta, gire a anilha de um lado ao outro na altura do peito.'],

            // Vôlei e basquete
            ['Salto vertical com contramovimento e alcance', 'Esportivo', 'Peso corporal', 'Agache rápido e salte buscando o ponto mais alto com a mão.'],
            ['Salto contínuo com joelhos ao peito', 'Esportivo', 'Peso corporal', 'Saltos seguidos puxando os joelhos ao peito, com contato curto no solo.'],
            ['Manchete simulada com elástico', 'Esportivo', 'Elástico', 'Braços estendidos e unidos, resista ao elástico subindo do quadril ao ombro.'],
            ['Arremesso acima da cabeça com medicine ball', 'Esportivo', 'Medicine ball', 'Arremesse a bola para cima e à frente estendendo tronco, ombros e quadril.'],
            ['Deslocamento defensivo em posição baixa', 'Esportivo', 'Peso corporal', 'Passos laterais em semiagachamento sem cruzar os pés, tronco ereto.'],
            ['Bloqueio duplo com salto lateral', 'Esportivo', 'Peso corporal', 'Deslocamento lateral de dois passos seguido de salto com braços estendidos.'],
            ['Salto lateral sobre linha com aterrissagem em apoio único', 'Esportivo', 'Peso corporal', 'Salte para o lado e aterrisse em uma perna só, segurando dois segundos.'],
            ['Troca rápida de pés no step (foot fire)', 'Esportivo', 'Step', 'Alterne os pés no step o mais rápido possível mantendo o tronco estável.'],

            // Ciclismo
            ['Pedalada em cadência alta na bicicleta', 'Esportivo', 'Bicicleta', 'Carga leve e cadência acima de 100 rpm, sem quicar no selim.'],
            ['Tiro sentado com carga alta na bicicleta', 'Esportivo', 'Bicicleta', 'Carga pesada e cadência baixa, sentado, por trinta segundos.'],
            ['Pedalada unilateral na bicicleta', 'Esportivo', 'Bicicleta', 'Pedale com uma perna de cada vez, buscando movimento redondo em todo o giro.'],
            ['Sustentação de tronco em posição aero', 'Esportivo', 'Peso corporal', 'Apoio nos antebraços com quadril alto, sustente a posição de guidão sem afundar a lombar.'],
            ['Extensão de tronco em posição de guidão', 'Esportivo', 'Peso corporal', 'De bruços, eleve o tronco simulando a postura sobre a bicicleta e sustente.'],

            // Golfe, escalada e surf
            ['Rotação de tronco para swing de golfe na polia', 'Esportivo', 'Polia', 'Gire o tronco puxando o cabo em diagonal, do quadril ao ombro oposto.'],
            ['Swing de golfe resistido com elástico', 'Esportivo', 'Elástico', 'Reproduza o swing completo contra a resistência do elástico, sem parar no topo.'],
            ['Desaceleração rotacional com medicine ball para golfe', 'Esportivo', 'Medicine ball', 'Gire com a bola e freie o movimento no final, sem soltar.'],
            ['Suspensão em pegada aberta para escalada', 'Esportivo', 'Peso corporal', 'Suspenda-se na borda com os dedos abertos, ombros ativos, por tempo curto.'],
            ['Puxada com pegada de pinça para escalada', 'Esportivo', 'Peso corporal', 'Segure a borda em pinça entre polegar e dedos e puxe o corpo poucos centímetros.'],
            ['Escalada horizontal na parede (traverse)', 'Esportivo', 'Peso corporal', 'Desloque-se lateralmente na parede mantendo três pontos de apoio.'],
            ['Pop-up de surf no solo', 'Esportivo', 'Peso corporal', 'De bruços, empurre o chão e traga os pés à base de surf em um movimento só.'],
            ['Remada de surf em decúbito ventral', 'Esportivo', 'Peso corporal', 'De bruços no banco, alterne braçadas longas mantendo o peito elevado.'],
            ['Equilíbrio na prancha de balanço', 'Esportivo', 'Disco', 'Sobre a prancha instável, mantenha o rolo centralizado sem tocar as bordas.'],
            ['Agachamento em base de surf com rotação', 'Esportivo', 'Peso corporal', 'Base lateral, agache e gire o tronco alternando os lados.'],
            ['Aceleração de braço com elástico para arremesso', 'Esportivo', 'Elástico', 'Elástico preso atrás, acelere o braço à frente reproduzindo o arremesso.'],
            ['Freio excêntrico de cotovelo pós-arremesso na polia', 'Esportivo', 'Polia', 'Após estender o braço, freie a volta do cabo lentamente.'],
            ['Passada lateral defensiva com elástico na cintura', 'Esportivo', 'Elástico', 'Elástico puxando para o lado, desloque-se contra a resistência em posição baixa.'],

            // ---- Mobilidade ----------------------------------------------------
            // Movimento ativo em amplitude, não sustentação passiva: o que é
            // segurar posição está em Alongamento.
            ['Mobilidade torácica em quatro apoios (open book)', 'Mobilidade', 'Peso corporal', 'Deitado de lado com joelhos flexionados, abra o braço de cima acompanhando com o olhar.'],
            ['Rotação torácica deitado de lado', 'Mobilidade', 'Peso corporal', 'Joelho de cima apoiado, gire o tronco levando o ombro em direção ao chão.'],
            ['Gato e camelo', 'Mobilidade', 'Peso corporal', 'Em quatro apoios, alterne arredondar e arquear a coluna vértebra por vértebra.'],
            ['Mobilidade de quadril 90/90', 'Mobilidade', 'Peso corporal', 'Sentado com as pernas em noventa graus, incline o tronco sobre a perna da frente.'],
            ['Transição 90/90 com rotação', 'Mobilidade', 'Peso corporal', 'Gire os joelhos de um lado ao outro sem apoiar as mãos no chão.'],
            ['Sustentação em agachamento profundo com apoio', 'Mobilidade', 'Peso corporal', 'Agache o mais fundo que conseguir segurando um apoio à frente, calcanhares no chão.'],
            ['Círculo de quadril em quatro apoios', 'Mobilidade', 'Peso corporal', 'Em quatro apoios, desenhe círculos amplos com o joelho sem mover o tronco.'],
            ['Mobilidade de tornozelo na parede', 'Mobilidade', 'Peso corporal', 'Joelho à frente em direção à parede sem tirar o calcanhar do chão.'],
            ['Mobilidade de tornozelo com elástico', 'Mobilidade', 'Elástico', 'Elástico puxando o tornozelo para trás, avance o joelho sobre a ponta do pé.'],
            ['Deslizamento de escápula na parede (wall slide)', 'Mobilidade', 'Peso corporal', 'Antebraços na parede, deslize os braços para cima sem descolar punho e cotovelo.'],
            ['Passagem de bastão pela cabeça', 'Mobilidade', 'Bastão', 'Pegada larga no bastão, leve os braços de frente para trás sem dobrar o cotovelo.'],
            ['Círculo de ombro com bastão', 'Mobilidade', 'Bastão', 'Desenhe círculos amplos com o bastão à frente do corpo, controlando o final.'],
            ['Rotação de ombro no batente da porta', 'Mobilidade', 'Peso corporal', 'Antebraço apoiado no batente, gire o tronco para o lado oposto abrindo o peito.'],
            ['Mobilidade cervical em flexão e extensão', 'Mobilidade', 'Peso corporal', 'Leve o queixo ao peito e depois olhe para cima, sem forçar o final da amplitude.'],
            ['Rotação cervical controlada', 'Mobilidade', 'Peso corporal', 'Gire a cabeça lentamente de um ombro ao outro mantendo o tronco parado.'],
            ['Alongamento dinâmico do flexor de quadril', 'Mobilidade', 'Peso corporal', 'Em passada, empurre o quadril à frente e volte, sem sustentar a posição.'],
            ['Mobilidade de punho em quatro apoios', 'Mobilidade', 'Peso corporal', 'Mãos apoiadas, transfira o peso à frente e para trás variando a direção dos dedos.'],
            ['Rotação de quadril em pé (hip airplane)', 'Mobilidade', 'Peso corporal', 'Em apoio único com tronco inclinado, gire o quadril abrindo e fechando.'],
            ['Balanço de perna frontal', 'Mobilidade', 'Peso corporal', 'Com apoio lateral, balance a perna à frente e atrás em amplitude crescente.'],
            ['Balanço de perna lateral', 'Mobilidade', 'Peso corporal', 'Balance a perna de um lado ao outro à frente do corpo, sem girar o tronco.'],
            ['Rotação de tronco sentado com bastão', 'Mobilidade', 'Bastão', 'Bastão nos ombros, gire o tronco de um lado ao outro com o quadril fixo.'],
            ['Mobilidade de coluna em posição de criança', 'Mobilidade', 'Peso corporal', 'Sentado sobre os calcanhares, caminhe com as mãos para os lados alongando o dorsal.'],
            ['Cachorro olhando para baixo com pedalada', 'Mobilidade', 'Peso corporal', 'Na posição de V invertido, alterne flexionar um joelho e estender o outro.'],
            ['Escorpião deitado', 'Mobilidade', 'Peso corporal', 'De bruços, leve o pé em direção à mão oposta girando a lombar sem forçar.'],
            ['Ponte torácica lateral', 'Mobilidade', 'Peso corporal', 'De lado apoiado no antebraço, gire o tronco passando o braço livre por baixo.'],
            ['Mobilidade de quadril em pé com joelho alto e rotação', 'Mobilidade', 'Peso corporal', 'Suba o joelho, abra para fora e desça, alternando as pernas.'],
            ['Mobilidade de coluna torácica sobre o rolo', 'Mobilidade', 'Rolo', 'Rolo na altura das escápulas, estenda a coluna sobre ele sem arquear a lombar.'],
            ['Respiração diafragmática deitada', 'Mobilidade', 'Peso corporal', 'Deitado com joelhos flexionados, inspire expandindo as costelas, não o peito.'],
            ['Mobilidade de ombro em suspensão passiva com pés apoiados', 'Mobilidade', 'Peso corporal', 'Segure a barra com os pés no chão e deixe o peso abrir os ombros.'],
            ['Mobilidade de quadril em passada com apoio no cotovelo', 'Mobilidade', 'Peso corporal', 'Em passada profunda, leve o cotovelo ao chão por dentro do pé e gire abrindo o peito.'],
            ['Rotação em quatro apoios com mão na nuca', 'Mobilidade', 'Peso corporal', 'Mão na nuca, gire o tronco levando o cotovelo para cima e depois para dentro.'],
            ['Agachamento em cócoras com rotação alternada', 'Mobilidade', 'Peso corporal', 'No fundo do agachamento, gire o tronco e leve um braço para cima de cada vez.'],
            ['Mobilidade de joelho em círculos controlados', 'Mobilidade', 'Peso corporal', 'Joelhos semiflexionados e mãos apoiadas, desenhe círculos lentos com os joelhos.'],
            ['Mobilidade de cotovelo com rotação controlada', 'Mobilidade', 'Peso corporal', 'Braço estendido, gire a palma ao máximo para dentro e para fora sem mover o ombro.'],
            ['Liberação miofascial de quadríceps no rolo', 'Mobilidade', 'Rolo', 'De bruços com o rolo na coxa, role da virilha ao joelho parando nos pontos sensíveis.'],
            ['Liberação miofascial de banda iliotibial no rolo', 'Mobilidade', 'Rolo', 'Deitado de lado, role a lateral da coxa do quadril ao joelho.'],
            ['Liberação miofascial de dorsal no rolo', 'Mobilidade', 'Rolo', 'Deitado de lado com o braço estendido, role a lateral das costas sob a axila.'],
            ['Liberação miofascial de panturrilha no rolo', 'Mobilidade', 'Rolo', 'Sentado com a panturrilha sobre o rolo, role do tornozelo ao joelho.'],
            ['Liberação miofascial de posterior de coxa no rolo', 'Mobilidade', 'Rolo', 'Sentado sobre o rolo, role a parte de trás da coxa do glúteo ao joelho.'],
            ['Liberação miofascial de tibial anterior no rolo', 'Mobilidade', 'Rolo', 'Em quatro apoios com a canela sobre o rolo, role a frente da perna.'],
            ['Liberação miofascial de glúteo na bola', 'Mobilidade', 'Bola', 'Sentado sobre a bola, cruze o tornozelo no joelho e busque o ponto de tensão.'],
            ['Liberação de fáscia plantar na bolinha', 'Mobilidade', 'Bola', 'Em pé, role a sola do pé sobre a bolinha do calcanhar aos dedos.'],
            ['Liberação de peitoral na bola na parede', 'Mobilidade', 'Bola', 'Bola entre o peito e a parede, mova o braço lentamente buscando o ponto tenso.'],

            // ---- Alongamento ---------------------------------------------------
            // Posição sustentada. O equivalente dinâmico de vários destes está em
            // Mobilidade, de propósito: são prescrições diferentes (fim da sessão
            // x aquecimento) e o personal escolhe uma ou outra.
            ['Alongamento de isquiotibiais sentado', 'Alongamento', 'Peso corporal', 'Sentado com a perna estendida, incline o tronco à frente mantendo as costas retas.'],
            ['Alongamento de isquiotibiais em pé', 'Alongamento', 'Peso corporal', 'Calcanhar apoiado à frente, empurre o quadril para trás sem arredondar a lombar.'],
            ['Alongamento de isquiotibiais deitado com elástico', 'Alongamento', 'Elástico', 'Deitado, eleve a perna estendida puxando pelo elástico até sentir tensão.'],
            ['Alongamento de isquiotibiais em passada com apoio no banco', 'Alongamento', 'Peso corporal', 'Perna estendida sobre o banco, incline o tronco à frente mantendo o quadril quadrado.'],
            ['Alongamento de quadríceps em pé', 'Alongamento', 'Peso corporal', 'Puxe o calcanhar ao glúteo mantendo os joelhos juntos e o quadril neutro.'],
            ['Alongamento de quadríceps deitado de lado', 'Alongamento', 'Peso corporal', 'Deitado de lado, puxe o pé de cima ao glúteo sem arquear a lombar.'],
            ['Alongamento de quadríceps na parede ajoelhado', 'Alongamento', 'Peso corporal', 'Joelho no chão e pé apoiado na parede, avance o quadril lentamente.'],
            ['Alongamento de flexores do quadril ajoelhado', 'Alongamento', 'Peso corporal', 'Um joelho no chão, empurre o quadril à frente contraindo o glúteo do lado de trás.'],
            ['Alongamento de glúteo deitado (figura 4)', 'Alongamento', 'Peso corporal', 'Cruze o tornozelo sobre o joelho oposto e puxe a coxa em direção ao peito.'],
            ['Alongamento de glúteo sentado na cadeira', 'Alongamento', 'Cadeira', 'Sentado, cruze o tornozelo no joelho e incline o tronco à frente.'],
            ['Alongamento de piriforme sentado', 'Alongamento', 'Peso corporal', 'Sentado com o joelho cruzado, gire o tronco para o lado da perna de cima.'],
            ['Alongamento de adutores sentado (borboleta)', 'Alongamento', 'Peso corporal', 'Solas dos pés unidas, deixe os joelhos caírem e incline o tronco à frente.'],
            ['Alongamento de adutores em afastamento lateral', 'Alongamento', 'Peso corporal', 'Pernas bem afastadas, desloque o peso para um lado flexionando aquele joelho.'],
            ['Alongamento de virilha em agachamento profundo', 'Alongamento', 'Peso corporal', 'No fundo do agachamento, empurre os joelhos para fora com os cotovelos.'],
            ['Alongamento de panturrilha na parede', 'Alongamento', 'Peso corporal', 'Perna de trás estendida e calcanhar no chão, empurre a parede à frente.'],
            ['Alongamento de sóleo com joelho flexionado', 'Alongamento', 'Peso corporal', 'Mesma posição da panturrilha, mas com o joelho de trás dobrado.'],
            ['Alongamento de tibial anterior ajoelhado', 'Alongamento', 'Peso corporal', 'Sentado sobre os calcanhares com o peito do pé no chão, recue o peso do corpo.'],
            ['Alongamento de peitoral no batente', 'Alongamento', 'Peso corporal', 'Braço apoiado no batente a noventa graus, gire o tronco para o lado oposto.'],
            ['Alongamento de peitoral com as mãos atrás da cabeça', 'Alongamento', 'Peso corporal', 'Mãos entrelaçadas na nuca, leve os cotovelos para trás abrindo o peito.'],
            ['Alongamento de dorsal na barra', 'Alongamento', 'Peso corporal', 'Segure a barra e recue o quadril, deixando o peso alongar as laterais das costas.'],
            ['Alongamento de dorsal ajoelhado no banco', 'Alongamento', 'Peso corporal', 'Cotovelos no banco e quadril recuado, deixe o peito descer em direção ao chão.'],
            ['Alongamento de tríceps acima da cabeça', 'Alongamento', 'Peso corporal', 'Cotovelo apontado para cima, puxe-o com a mão oposta atrás da cabeça.'],
            ['Alongamento de bíceps na parede', 'Alongamento', 'Peso corporal', 'Braço estendido para trás com a palma na parede, gire o tronco para o lado oposto.'],
            ['Alongamento de deltoide posterior cruzando o braço', 'Alongamento', 'Peso corporal', 'Cruze o braço à frente do peito e puxe pelo cotovelo, sem levantar o ombro.'],
            ['Alongamento de rotadores externos deitado (sleeper stretch)', 'Alongamento', 'Peso corporal', 'Deitado de lado com o ombro a noventa graus, gire o antebraço para baixo sem dor.'],
            ['Alongamento de manguito rotador na porta', 'Alongamento', 'Peso corporal', 'Mão apoiada acima da cabeça no batente, avance o corpo devagar.'],
            ['Alongamento de antebraço em extensão', 'Alongamento', 'Peso corporal', 'Braço estendido com a palma para cima, puxe os dedos para baixo com a outra mão.'],
            ['Alongamento de antebraço em flexão', 'Alongamento', 'Peso corporal', 'Braço estendido com a palma para baixo, puxe as costas da mão em direção ao corpo.'],
            ['Alongamento de punho em extensão na mesa', 'Alongamento', 'Peso corporal', 'Costas das mãos apoiadas na mesa com os dedos para trás, recue o corpo devagar.'],
            ['Alongamento de trapézio superior sentado', 'Alongamento', 'Peso corporal', 'Sentado segurando a cadeira, incline a cabeça para o lado oposto.'],
            ['Alongamento cervical lateral', 'Alongamento', 'Peso corporal', 'Puxe a cabeça para o lado com a mão, mantendo o ombro oposto baixo.'],
            ['Alongamento de escaleno e cervical anterior', 'Alongamento', 'Peso corporal', 'Olhe para cima e para o lado, com a mão fixando a clavícula do lado oposto.'],
            ['Alongamento de lombar em posição fetal', 'Alongamento', 'Peso corporal', 'Deitado, abrace os dois joelhos e aproxime-os do peito.'],
            ['Alongamento de lombar com joelhos cruzados', 'Alongamento', 'Peso corporal', 'Deitado, cruze uma perna sobre a outra e deixe os joelhos caírem para o lado.'],
            ['Alongamento de coluna em torção sentado', 'Alongamento', 'Peso corporal', 'Sentado com uma perna cruzada, gire o tronco apoiando o cotovelo no joelho.'],
            ['Alongamento de cadeia posterior com toalha', 'Alongamento', 'Toalha', 'Deitado, passe a toalha na sola do pé e puxe a perna estendida em sua direção.'],
            ['Alongamento de cadeia anterior em pé na parede', 'Alongamento', 'Peso corporal', 'Mãos na parede acima da cabeça, avance o quadril alongando peito e abdome.'],
            ['Alongamento de banda iliotibial em pé', 'Alongamento', 'Peso corporal', 'Cruze a perna atrás da outra e incline o tronco para o lado oposto.'],
            ['Alongamento de psoas em decúbito dorsal na maca', 'Alongamento', 'Peso corporal', 'Deitado na borda da maca, deixe uma perna cair enquanto abraça a outra.'],
            ['Alongamento de quadril em passada profunda com rotação', 'Alongamento', 'Peso corporal', 'Em passada, apoie a mão no chão e gire o tronco abrindo o braço para cima.'],
            ['Alongamento de abdome em decúbito ventral (esfinge)', 'Alongamento', 'Peso corporal', 'De bruços apoiado nos antebraços, eleve o peito sem tensionar a lombar.'],
            ['Alongamento de coluna suspenso na barra com pés no chão', 'Alongamento', 'Peso corporal', 'Segure a barra com os pés apoiados e deixe o tronco pendurar, soltando a lombar.'],
            ['Postura da criança', 'Alongamento', 'Peso corporal', 'Sentado sobre os calcanhares com os braços à frente, relaxe o tronco sobre as coxas.'],
            ['Postura do pombo', 'Alongamento', 'Peso corporal', 'Perna da frente dobrada no chão e a de trás estendida, desça o tronco à frente.'],
            ['Alongamento de ombro em rotação interna com toalha', 'Alongamento', 'Toalha', 'Toalha nas costas, puxe com a mão de cima levando a de baixo para o alto.'],
            ['Alongamento de cadeia lateral em pé com braço acima da cabeça', 'Alongamento', 'Peso corporal', 'Braço estendido acima da cabeça, incline o tronco para o lado oposto.'],

            // ---- Ativação ------------------------------------------------------
            // Aquecimento e preparação, carga baixa de propósito. Não entra aqui
            // nada que já exista com carga nos grupos de musculação.
            ['Flexão escapular (scapular push-up)', 'Ativação', 'Peso corporal', 'Em prancha alta com cotovelos travados, aproxime e afaste as escápulas.'],
            ['Retração escapular na polia baixa sentado', 'Ativação', 'Polia', 'Puxe só com as escápulas, sem dobrar o cotovelo, e volte controlando.'],
            ['Ativação de serrátil na parede', 'Ativação', 'Peso corporal', 'Mãos na parede com cotovelos estendidos, empurre afastando as escápulas.'],
            ['Marcha de ativação com elástico nos tornozelos', 'Ativação', 'Elástico', 'Parado no lugar, suba um joelho de cada vez vencendo a tensão do elástico.'],
            ['Ativação de core com respiração 360 graus', 'Ativação', 'Peso corporal', 'Mãos nas costelas, inspire expandindo para os lados e para trás, não para cima.'],
            ['Aquecimento articular de ombros com círculos', 'Ativação', 'Peso corporal', 'Braços estendidos, faça círculos pequenos crescendo até a amplitude total.'],
            ['Aquecimento de quadril com círculos em pé', 'Ativação', 'Peso corporal', 'Mãos na cintura, desenhe círculos amplos com o quadril nos dois sentidos.'],
            ['Polichinelo com abertura de braços em Y', 'Ativação', 'Peso corporal', 'Polichinelo terminando com os braços em diagonal, não acima da cabeça.'],
            ['Corrida no lugar com toque de calcanhar lateral', 'Ativação', 'Peso corporal', 'Corra parado tocando o calcanhar na mão do mesmo lado, aberta para fora.'],
            ['Aquecimento de agachamento com bastão acima da cabeça', 'Ativação', 'Bastão', 'Bastão estendido acima da cabeça, agache até onde conseguir sem inclinar o tronco.'],
            ['Aquecimento de punhos e dedos', 'Ativação', 'Peso corporal', 'Abra e feche as mãos e gire os punhos nos dois sentidos antes de pegar carga.'],
            ['Elevação de ponta do pé no degrau (tibial)', 'Ativação', 'Step', 'Calcanhares no degrau, eleve a ponta dos pés e desça controlando.'],
            ['Aquecimento específico com barra vazia', 'Ativação', 'Barra', 'Execute o padrão do exercício principal com a barra sem anilhas, buscando amplitude.'],
            ['Aquecimento de ombro com halteres leves em três planos', 'Ativação', 'Halteres', 'Poucos quilos, faça elevação à frente, lateral e em diagonal em sequência.'],
            ['Ativação de peitoral com isometria de palmas', 'Ativação', 'Peso corporal', 'Palmas unidas à frente do peito, empurre uma contra a outra e sustente.'],
            ['Ativação de dorsal com puxada isométrica no elástico', 'Ativação', 'Elástico', 'Puxe o elástico até a altura do peito e sustente a tensão sem mover os braços.'],
            ['Aquecimento de coluna com rotações em pé', 'Ativação', 'Peso corporal', 'Braços soltos, gire o tronco de um lado ao outro deixando os braços baterem no corpo.'],
            ['Ativação de quadril com agachamento pulsado', 'Ativação', 'Peso corporal', 'Na metade do agachamento, faça pulsos curtos sem subir nem descer por completo.'],
            ['Bom dia sem carga para aquecimento', 'Ativação', 'Peso corporal', 'Mãos na nuca, empurre o quadril para trás inclinando o tronco com a coluna reta.'],
            ['Aquecimento de quadril em passada com rotação torácica', 'Ativação', 'Peso corporal', 'A cada passada, gire o tronco para o lado da perna da frente.'],
            ['Aquecimento de joelho com extensão sentado sem carga', 'Ativação', 'Peso corporal', 'Sentado na borda do banco, estenda e flexione o joelho em ritmo lento.'],
            ['Aquecimento de tronco com rotação e toque no pé', 'Ativação', 'Peso corporal', 'Em pé com pernas afastadas, gire e toque a mão no pé oposto, alternando.'],

            // ---- Equilíbrio ----------------------------------------------------
            // Prescrição de aluno idoso, pós-lesão e retorno ao esporte. A
            // biblioteca não tinha nada disso: só sobrava mandar "fica num pé só".
            ['Apoio unipodal com olhos abertos', 'Equilíbrio', 'Peso corporal', 'Fique sobre uma perna com o joelho levemente flexionado, sem apoiar o outro pé.'],
            ['Apoio unipodal com olhos fechados', 'Equilíbrio', 'Peso corporal', 'Mesma posição, olhos fechados, com apoio por perto para segurança.'],
            ['Apoio unipodal em superfície instável', 'Equilíbrio', 'Bosu', 'Sobre o bosu, mantenha uma perna só com o quadril nivelado.'],
            ['Marcha em linha (tandem)', 'Equilíbrio', 'Peso corporal', 'Caminhe encostando o calcanhar na ponta do pé de trás a cada passo.'],
            ['Caminhada para trás em linha reta', 'Equilíbrio', 'Peso corporal', 'Ande de costas sobre uma linha imaginária, tocando primeiro a ponta do pé.'],
            ['Apoio unipodal com alcance frontal', 'Equilíbrio', 'Peso corporal', 'Sobre uma perna, alcance o mais longe possível à frente com a outra sem tocar o chão.'],
            ['Apoio unipodal com alcance multidirecional', 'Equilíbrio', 'Peso corporal', 'Alcance à frente, na diagonal interna e na externa, voltando ao centro a cada vez.'],
            ['Apoio unipodal com alcance de objeto no solo', 'Equilíbrio', 'Peso corporal', 'Em uma perna, incline o tronco e pegue um objeto no chão, voltando sem apoiar.'],
            ['Apoio unipodal com movimento de braços em três planos', 'Equilíbrio', 'Peso corporal', 'Mantenha a perna de apoio firme enquanto move os braços à frente, ao lado e acima.'],
            ['Apoio unipodal sobre espuma com giro de cabeça', 'Equilíbrio', 'Disco', 'Sobre a espuma, gire a cabeça devagar para os lados sem perder a base.'],
            ['Apoio unipodal com perturbação no elástico', 'Equilíbrio', 'Elástico', 'Elástico na cintura puxando para os lados, resista sem tirar o pé do chão.'],
            ['Agachamento unipodal no disco de equilíbrio', 'Equilíbrio', 'Disco', 'Sobre o disco, desça em meio agachamento na perna de apoio e volte.'],
            ['Agachamento no bosu com apoio bipodal', 'Equilíbrio', 'Bosu', 'Com os dois pés no bosu, agache até a metade mantendo os joelhos alinhados.'],
            ['Prancha com apoio no bosu', 'Equilíbrio', 'Bosu', 'Antebraços no bosu, sustente a prancha corrigindo as oscilações.'],
            ['Salto sobre o bosu com aterrissagem estável', 'Equilíbrio', 'Bosu', 'Salte para cima do bosu e segure a aterrissagem por dois segundos.'],
            ['Salto frontal com aterrissagem em apoio único', 'Equilíbrio', 'Peso corporal', 'Salte à frente e aterrisse em uma perna só, segurando o joelho alinhado.'],
            ['Transferência de peso lateral controlada', 'Equilíbrio', 'Peso corporal', 'Pés afastados, passe o peso de um pé ao outro devagar sem mover os pés.'],
            ['Transferência de peso em base tandem com resistência elástica', 'Equilíbrio', 'Elástico', 'Um pé à frente do outro, resista ao elástico deslocando o peso à frente e atrás.'],
            ['Subida no step com pausa em apoio único', 'Equilíbrio', 'Step', 'Suba no step e segure dois segundos com a perna de trás no ar antes de descer.'],
            ['Passada com pausa em apoio único', 'Equilíbrio', 'Peso corporal', 'Ao subir da passada, pare na posição de uma perna antes de dar o passo seguinte.'],
            ['Elevação de joelho com equilíbrio e rotação', 'Equilíbrio', 'Peso corporal', 'Suba o joelho e gire o tronco para o lado oposto sem perder o apoio.'],
            ['Cegonha com passagem de objeto', 'Equilíbrio', 'Peso corporal', 'Em uma perna, passe um objeto ao redor do corpo e por baixo do joelho elevado.'],
            ['Deslocamento sobre linha com obstáculos baixos', 'Equilíbrio', 'Peso corporal', 'Caminhe sobre a linha passando por cima de obstáculos baixos alternando as pernas.'],
            ['Equilíbrio dinâmico em agachamento com transferência de peso', 'Equilíbrio', 'Peso corporal', 'No meio agachamento, desloque o peso lateralmente de um pé ao outro.'],

            // ---- Prevenção -----------------------------------------------------
            // Prevenção de lesão e retorno pós-lesão. Não substitui fisioterapia:
            // é o que o personal prescreve como parte do treino.
            ['Prancha de Copenhague', 'Prevenção', 'Peso corporal', 'De lado com o pé de cima no banco, eleve o quadril sustentando pelo adutor.'],
            ['Prancha de Copenhague com joelho apoiado', 'Prevenção', 'Peso corporal', 'Versão mais fácil, com o joelho da perna de cima apoiado no banco.'],
            ['Ponte de glúteo com aperto de adutores', 'Prevenção', 'Peso corporal', 'Em ponte, aperte uma bola ou almofada entre os joelhos durante toda a sustentação.'],
            ['Deslizamento de isquiotibiais no disco', 'Prevenção', 'Disco', 'Em ponte com os pés nos discos, estenda as pernas devagar e puxe de volta.'],
            ['Deslizamento de calcanhar bilateral no disco', 'Prevenção', 'Disco', 'Deitado, deslize os dois calcanhares para longe e traga de volta sem largar o quadril.'],
            ['Excêntrico de isquiotibiais no deslizador com uma perna', 'Prevenção', 'Disco', 'Em ponte unipodal, estenda a perna do disco em cinco segundos e volte com as duas.'],
            ['Estabilização lombar em quatro apoios com elástico', 'Prevenção', 'Elástico', 'Elástico puxando para o lado, mantenha a coluna imóvel enquanto resiste.'],
            ['Ativação de transverso do abdome deitado', 'Prevenção', 'Peso corporal', 'Deitado, puxe o umbigo para dentro sem prender a respiração nem mover a pelve.'],
            ['Estabilização lombar em ponte com marcha', 'Prevenção', 'Peso corporal', 'Na ponte de glúteo, alterne tirar um pé do chão sem deixar o quadril cair.'],
            ['Estabilização de tronco em posição de urso com toque', 'Prevenção', 'Peso corporal', 'Joelhos a poucos centímetros do chão, toque o ombro oposto sem girar o quadril.'],
            ['Estabilização escapular em prancha com apoio na parede', 'Prevenção', 'Peso corporal', 'Antebraços na parede, sustente a posição afastando as escápulas.'],
            ['Retração e depressão escapular em decúbito ventral', 'Prevenção', 'Peso corporal', 'De bruços, puxe as escápulas para baixo e para dentro sem usar os braços.'],
            ['Rotação externa de ombro a 90 graus com elástico', 'Prevenção', 'Elástico', 'Cotovelo na altura do ombro, gire o antebraço para cima controlando a volta.'],
            ['Elevação de calcanhar excêntrica para tendão de aquiles', 'Prevenção', 'Step', 'Suba com os dois pés e desça em um só, em cinco segundos, abaixo do degrau.'],
            ['Excêntrico de sóleo unilateral no degrau', 'Prevenção', 'Step', 'Mesma descida lenta, mas com o joelho flexionado para levar a carga ao sóleo.'],
            ['Inversão de tornozelo com elástico', 'Prevenção', 'Elástico', 'Elástico puxando para fora, gire a planta do pé para dentro e volte devagar.'],
            ['Eversão de tornozelo com elástico', 'Prevenção', 'Elástico', 'Elástico puxando para dentro, gire a planta do pé para fora e volte devagar.'],
            ['Dorsiflexão de tornozelo com elástico', 'Prevenção', 'Elástico', 'Elástico no peito do pé, puxe a ponta em direção à canela.'],
            ['Elevação do arco plantar (short foot)', 'Prevenção', 'Peso corporal', 'Sem dobrar os dedos, aproxime a base do dedão do calcanhar levantando o arco.'],
            ['Fortalecimento de pé com toalha', 'Prevenção', 'Toalha', 'Com os dedos do pé, puxe a toalha no chão em sua direção.'],
            ['Propriocepção de joelho em semiagachamento com elástico', 'Prevenção', 'Elástico', 'Elástico acima do joelho, sustente o semiagachamento resistindo ao desvio para dentro.'],
            ['Controle de valgo dinâmico no step', 'Prevenção', 'Step', 'Suba no step observando o joelho: ele não pode cair para dentro em nenhum momento.'],
            ['Descida controlada do step em apoio único', 'Prevenção', 'Step', 'Desça do step em três segundos tocando o calcanhar de leve no chão.'],
            ['Mobilização de quadril em decúbito dorsal com elástico', 'Prevenção', 'Elástico', 'Elástico na virilha puxando para trás, leve o joelho ao peito e volte.'],
            ['Mobilização neural do nervo ciático sentado', 'Prevenção', 'Peso corporal', 'Sentado, estenda o joelho e puxe a ponta do pé enquanto olha para cima e para baixo.'],
            ['Mobilização neural do nervo mediano', 'Prevenção', 'Peso corporal', 'Braço aberto ao lado com a palma para cima, incline a cabeça para o lado oposto e volte.'],
            ['Fortalecimento de pescoço em isometria manual', 'Prevenção', 'Peso corporal', 'Empurre a cabeça contra a própria mão nas quatro direções, sem deixá-la mover.'],
            ['Isometria de cervical em decúbito dorsal', 'Prevenção', 'Peso corporal', 'Deitado, eleve a cabeça poucos centímetros com o queixo recuado e sustente.'],
            ['Fortalecimento de pescoço com elástico em quatro direções', 'Prevenção', 'Elástico', 'Elástico ao redor da cabeça, resista à tração à frente, atrás e nos dois lados.'],
            ['Descompressão lombar com elevação de pernas na cadeira', 'Prevenção', 'Cadeira', 'Deitado com as panturrilhas sobre a cadeira e quadril e joelhos a noventa graus.'],

            // ---- Reforço de musculação e hipertrofia ---------------------------
            // Só o que era buraco real nos 13 grupos antigos: calistenia, argolas,
            // deslizadores e aparelhos que a biblioteca não citava. Variação de
            // pegada e de ângulo NÃO entra — é o critério (a) da poda de 22/08.

            // Peito
            ['Supino inclinado unilateral com halter', 'Peito', 'Halter', 'Um halter por vez, com o outro braço relaxado, exigindo o core para não girar.'],
            ['Flexão de braço com deslocamento lateral', 'Peito', 'Peso corporal', 'Faça a flexão, desloque as mãos e os pés para o lado e repita.'],
            ['Flexão de braço nas argolas', 'Peito', 'Argolas', 'Argolas baixas, desça o peito entre elas girando as palmas para fora ao subir.'],
            ['Paralelas nas argolas', 'Peito', 'Argolas', 'Mergulho nas argolas com tronco inclinado à frente, estabilizando a oscilação.'],
            ['Crucifixo na polia deitado no banco', 'Peito', 'Polia', 'Banco entre as polias baixas, abra e feche os braços com leve flexão de cotovelo.'],
            ['Flexão de braço com abertura nos discos', 'Peito', 'Disco', 'Mãos sobre os discos, deslize um braço para o lado ao descer e puxe de volta ao subir.'],

            // Costas
            ['Puxada frontal ajoelhado na polia', 'Costas', 'Polia', 'Ajoelhado de frente para a polia alta, puxe a barra até o peito sem recuar o tronco.'],
            ['Remada nas argolas com pés elevados', 'Costas', 'Argolas', 'Corpo na horizontal com os pés no banco, puxe as argolas até as costelas.'],
            ['Barra fixa nas argolas', 'Costas', 'Argolas', 'Puxe até o queixo passar as argolas, deixando as palmas girarem naturalmente.'],
            ['Prancha com abertura de pernas nos discos', 'Core', 'Disco', 'Em prancha com os pés nos discos, afaste e junte as pernas sem deixar o quadril cair.'],
            ['Remada curvada no smith', 'Costas', 'Smith', 'Tronco a quarenta e cinco graus sob a barra guiada, puxe até o abdome.'],

            // Ombros
            ['Flexão de braço em pique (pike push-up)', 'Ombros', 'Peso corporal', 'Quadril alto em V invertido, desça a cabeça em direção ao chão entre as mãos.'],
            ['Parada de mão na parede', 'Ombros', 'Peso corporal', 'De cabeça para baixo com os pés na parede, sustente com o corpo alinhado.'],
            ['Desenvolvimento em parada de mão na parede', 'Ombros', 'Peso corporal', 'Na parada de mão, flexione os cotovelos até a cabeça quase tocar o chão e empurre.'],

            // Bíceps
            ['Rosca nas argolas', 'Bíceps', 'Argolas', 'Corpo inclinado para trás com as palmas para cima, puxe flexionando só os cotovelos.'],
            ['Rosca bayesiana com o braço atrás do corpo', 'Bíceps', 'Polia', 'De costas para a polia baixa e o cotovelo atrás da linha do tronco, flexione sem trazê-lo à frente.'],

            // Tríceps
            ['Extensão de tríceps nas argolas', 'Tríceps', 'Argolas', 'Corpo inclinado à frente, desça flexionando os cotovelos e estenda para voltar.'],
            ['Mergulho nas argolas', 'Tríceps', 'Argolas', 'Tronco vertical nas argolas, desça até noventa graus e suba estendendo os cotovelos.'],

            // Pernas
            ['Agachamento com deslocamento lateral no disco', 'Pernas', 'Disco', 'Um pé sobre o disco, deslize-o para o lado descendo em agachamento e puxe de volta.'],
            ['Belt squat na máquina', 'Pernas', 'Máquina', 'Carga presa no cinto pelo quadril, agache sem nenhuma compressão na coluna.'],
            ['Leg press vertical', 'Pernas', 'Máquina', 'Deitado sob a plataforma, empurre para cima sem tirar a lombar do apoio.'],

            // Core
            ['L-sit no solo', 'Core', 'Peso corporal', 'Sentado com as mãos no chão, eleve o quadril e as pernas estendidas à frente.'],
            ['L-sit nas paralelas', 'Core', 'Peso corporal', 'Nas paralelas com os braços travados, sustente as pernas estendidas na horizontal.'],
            ['Dragon flag', 'Core', 'Peso corporal', 'Deitado segurando o banco atrás da cabeça, eleve o corpo reto e desça sem dobrar o quadril.'],
            ['Limpador de para-brisa suspenso na barra', 'Core', 'Peso corporal', 'Pendurado com as pernas elevadas, leve os pés de um lado ao outro.'],
            ['Serra abdominal no disco', 'Core', 'Disco', 'Em prancha com os pés nos discos, empurre o corpo para trás e puxe de volta.'],
            ['Prancha com deslizamento de braços no disco', 'Core', 'Disco', 'Em prancha com as mãos nos discos, deslize um braço à frente por vez sem girar o quadril.'],

            // Funcional
            ['Muscle-up na barra', 'Funcional', 'Peso corporal', 'Puxada explosiva seguida da transição do peito sobre a barra e extensão dos braços.'],
            ['Muscle-up nas argolas', 'Funcional', 'Argolas', 'Mesma transição da barra, com a instabilidade das argolas na virada.'],
            ['Front lever progressivo', 'Funcional', 'Peso corporal', 'Pendurado, sustente o corpo na horizontal com os joelhos recolhidos ao peito.'],
            ['Back lever progressivo', 'Funcional', 'Peso corporal', 'De costas para a barra, sustente o corpo na horizontal com os joelhos recolhidos.'],
            ['Flexão em pseudo planche', 'Funcional', 'Peso corporal', 'Mãos na altura da cintura e ombros à frente das mãos, faça a flexão inclinado.'],
            ['Devil press com halteres', 'Funcional', 'Halteres', 'Burpee segurando os halteres e, ao subir, leve-os acima da cabeça em um arco.'],
            ['Man maker com halteres', 'Funcional', 'Halteres', 'Flexão, uma remada de cada lado, e desenvolvimento ao ficar de pé.'],
            ['Corrida de vai e vem (shuttle run)', 'Funcional', 'Peso corporal', 'Corra até a marca, toque o chão e volte, repetindo em distâncias crescentes.'],
        ];
    }
}
