<?php

namespace Database\Seeders;

use App\Models\Exercise;
use Illuminate\Database\Seeder;

/**
 * Ampliação da biblioteca de exercícios (327 itens), pedida por personal
 * trainers que precisavam de repertório pra montar treino sem ficar limitados
 * aos 75 originais.
 *
 * Por que não veio do wger.de (fonte do ExerciseSeeder): o acervo deles tem só
 * 66 traduções em português no total e 365 imagens pro catálogo inteiro — não
 * dá pra chegar a centenas de exercícios em pt-BR por lá. Estes foram escritos
 * com a nomenclatura usada em academia no Brasil e, por isso, **não têm foto**:
 * caem no fallback de animação do ExerciseAnimation, que escolhe o padrão de
 * movimento pelo nome/grupo (ver frontend/lib/exercisePatterns.ts). O personal
 * ainda pode subir o próprio vídeo por exercício (ExerciseMediaOverride).
 *
 * Complementa o ExerciseSeeder em vez de substituí-lo: aquele é dono dos 75
 * com foto real, e este só insere o que ainda não existe (ver run()).
 *
 * O campo `equipment` não é decorativo — App\Support\Progressao usa ele pra
 * decidir o incremento de carga sugerido. Equipamento sem carga somável
 * (elástico, TRX, bola, corda, cardio) precisa estar mapeado lá com incremento
 * zero, senão o app sugere "+2,5 kg" num elástico.
 */
class ExercicioBibliotecaAmpliadaSeeder extends Seeder
{
    public function run(): void
    {
        // Só insere o que falta. De propósito não é updateOrCreate: um nome
        // repetido aqui sobrescreveria o registro do ExerciseSeeder e apagaria
        // a foto real dele (image_url/image_credit ficariam nulos).
        $jaExistem = Exercise::pluck('name')->all();
        $jaExistem = array_combine($jaExistem, $jaExistem);

        // Curadoria de 22/08/2026: 244 destes foram cortados por serem variação
        // quase idêntica de outro, nota de prescrição (isometria/pausa) ou
        // equipamento que academia comum não tem. Sem esta checagem, rodar o
        // seeder de novo ressuscitaria todos eles e desfaria a poda em silêncio.
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
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'created_at' => now(),
            ], $lote));
        }
    }

    /** @return array<int, array{0: string, 1: string, 2: string, 3: string}> [nome, grupo, equipamento, instruções] */
    private static function linhas(): array
    {
        return [
            // ---- Peito ---------------------------------------------------------
            ['Supino inclinado com halteres', 'Peito', 'Halteres', 'Banco a 30-45°, empurre os halteres acima do peito sem travar o cotovelo.'],
            ['Supino declinado com halteres', 'Peito', 'Halteres', 'Banco declinado, empurre os halteres na linha do peito inferior.'],
            ['Supino reto no smith', 'Peito', 'Smith', 'Barra guiada na linha do mamilo, controle a descida até encostar levemente no peito.'],
            ['Supino inclinado no smith', 'Peito', 'Smith', 'Banco inclinado sob a barra guiada, empurre até a extensão quase completa.'],
            ['Supino inclinado na máquina', 'Peito', 'Máquina', 'Sentado, empurre os pegadores para cima e à frente na linha do peito superior.'],
            ['Crucifixo', 'Peito', 'Máquina', 'Costas apoiadas, empurre os pegadores à frente até quase estender os cotovelos.'],
            ['Crucifixo unilateral', 'Peito', 'Máquina', 'Empurre um braço de cada vez, evitando girar o tronco para compensar.'],
            ['Crucifixo inclinado com halteres', 'Peito', 'Halteres', 'Banco inclinado, abra os braços em arco com cotovelos levemente flexionados.'],
            ['Crucifixo declinado com halteres', 'Peito', 'Halteres', 'Banco declinado, abra em arco e feche acima do peito inferior.'],
            ['Flexão de braço inclinada', 'Peito', 'Peso corporal', 'Mãos apoiadas num banco — versão mais fácil, ideal para iniciantes.'],
            ['Flexão de braço com pegada aberta', 'Peito', 'Peso corporal', 'Mãos bem além da largura dos ombros, foco no peitoral externo.'],
            ['Flexão de braço com joelhos apoiados', 'Peito', 'Peso corporal', 'Joelhos no chão, tronco alinhado — progressão inicial da flexão.'],
            ['Flexão de braço no TRX', 'Peito', 'TRX', 'Alças na altura do quadril, desça o peito entre as mãos mantendo o core firme.'],
            ['Flexão com rotação', 'Peito', 'Peso corporal', 'Ao subir, gire o tronco e estenda um braço para o teto.'],
            ['Pullover na polia alta', 'Peito', 'Polia', 'Em pé, braços quase estendidos, puxe a barra até a coxa contraindo o peito e o dorsal.'],
            ['Paralelas com peso', 'Peito', 'Peso corporal', 'Mergulho nas paralelas com cinto de lastro, tronco inclinado à frente.'],

            // ---- Costas --------------------------------------------------------
            ['Puxada frontal pegada neutra', 'Costas', 'Polia', 'Punhos voltados um para o outro, puxe até o peito superior.'],
            ['Puxada frontal pegada supinada', 'Costas', 'Polia', 'Palmas voltadas para você — recruta mais o bíceps junto ao dorsal.'],
            ['Puxada frente', 'Costas', 'Máquina', 'Braços independentes acompanham a trajetória natural de cada lado.'],
            ['Barra fixa pegada supinada (chin-up)', 'Costas', 'Peso corporal', 'Palmas voltadas para você, suba até o queixo passar da barra.'],
            ['Barra fixa pegada neutra', 'Costas', 'Peso corporal', 'Punhos paralelos, versão mais amigável para o ombro.'],
            ['Barra fixa com elástico (assistida)', 'Costas', 'Elástico', 'Elástico apoiando o pé reduz o peso e permite treinar a barra desde o início.'],
            ['Remada invertida no TRX', 'Costas', 'TRX', 'Puxe o peito até as alças mantendo o corpo em prancha rígida.'],
            ['Remada curvada pegada supinada', 'Costas', 'Barra', 'Palmas para cima, cotovelos rentes ao corpo, puxe até o umbigo.'],
            ['Remada curvada com halteres', 'Costas', 'Halteres', 'Tronco inclinado a 45°, puxe os dois halteres até a lateral do abdômen.'],
            ['Remada no banco inclinado', 'Costas', 'Halteres', 'Peito apoiado no banco inclinado elimina o balanço do tronco.'],
            ['Remada serrote', 'Costas', 'Halter', 'Joelho e mão apoiados no banco, puxe o halter até a lateral do tronco.'],
            ['Remada baixa unilateral', 'Costas', 'Polia', 'Um braço por vez, permitindo alongar bem o dorsal na volta.'],
            ['Remada alta na polia', 'Costas', 'Polia', 'Puxe a barra até a altura do queixo com os cotovelos acima das mãos.'],
            ['Remada unilateral com kettlebell', 'Costas', 'Kettlebell', 'Tronco inclinado, puxe o kettlebell até a lateral do abdômen.'],
            ['Levantamento terra com halteres', 'Costas', 'Halteres', 'Mesma mecânica do terra, com halteres ao lado do corpo.'],
            ['Levantamento terra com trap bar', 'Costas', 'Barra', 'Barra hexagonal mantém a carga alinhada ao corpo e poupa a lombar.'],
            ['Extensão lombar na máquina', 'Costas', 'Máquina', 'Sentado, estenda o tronco para trás contra o encosto acolchoado.'],
            ['Bom dia com barra', 'Costas', 'Barra', 'Barra nas costas, quadril para trás mantendo as costas retas.'],
            ['Remada com argola', 'Costas', 'Polia', 'Puxe a corda até o abdômen separando as pontas ao final.'],

            // ---- Ombros --------------------------------------------------------
            ['Desenvolvimento Arnold', 'Ombros', 'Halteres', 'Comece com as palmas para você e gire os punhos enquanto empurra para cima.'],
            ['Desenvolvimento no smith', 'Ombros', 'Smith', 'Barra guiada à frente do rosto, empurre até quase estender os cotovelos.'],
            ['Desenvolvimento na máquina', 'Ombros', 'Máquina', 'Sentado, empurre os pegadores para cima sem travar a articulação.'],
            ['Push press', 'Ombros', 'Barra', 'Use um leve impulso de pernas para vencer o ponto mais difícil do desenvolvimento.'],
            ['Elevação lateral sentado', 'Ombros', 'Halteres', 'Sentado elimina o balanço do quadril e isola melhor o deltoide medial.'],
            ['Elevação lateral unilateral na polia', 'Ombros', 'Polia', 'Um braço por vez, apoiando a mão livre para estabilizar o tronco.'],
            ['Elevação lateral com elástico', 'Ombros', 'Elástico', 'Pise no elástico e eleve os braços lateralmente contra a resistência.'],
            ['Elevação frontal com anilha', 'Ombros', 'Anilha', 'Segure a anilha pelas laterais e eleve à frente até a altura dos olhos.'],
            ['Elevação frontal alternada', 'Ombros', 'Halteres', 'Eleve um braço por vez, alternando a cada repetição.'],
            ['Crucifixo invertido no banco inclinado', 'Ombros', 'Halteres', 'Peito apoiado no banco inclinado, abra os braços em arco.'],
            ['Remada alta na polia baixa', 'Ombros', 'Polia', 'Cotovelos acima das mãos durante toda a subida.'],
            ['Rotação externa com elástico', 'Ombros', 'Elástico', 'Cotovelo colado ao corpo a 90°, gire o antebraço para fora — manguito rotador.'],
            ['Rotação externa na polia', 'Ombros', 'Polia', 'Mesma mecânica da rotação externa, com tensão constante do cabo.'],
            ['Rotação interna na polia', 'Ombros', 'Polia', 'Gire o antebraço em direção ao abdômen mantendo o cotovelo fixo.'],
            ['Desenvolvimento em pé com halteres', 'Ombros', 'Halteres', 'Em pé, core firme, empurre os halteres acima da cabeça.'],
            ['Desenvolvimento lateral com barra em pé', 'Ombros', 'Barra', 'Sem impulso de pernas: só o ombro sobe a barra do peito ao topo.'],
            ['Desenvolvimento alternado com halteres', 'Ombros', 'Halteres', 'Empurre um braço por vez enquanto o outro segura na altura do ombro.'],
            ['Face pull ajoelhado', 'Ombros', 'Polia', 'Ajoelhado, puxe a corda até o rosto abrindo bem os cotovelos.'],
            ['Crucifixo invertido sentado curvado', 'Ombros', 'Halteres', 'Sentado com o tronco sobre as coxas, abra os braços para trás.'],

            // ---- Bíceps --------------------------------------------------------
            ['Rosca direta com barra W', 'Bíceps', 'Barra W', 'Pegada semipronada na barra W alivia o punho na rosca direta.'],
            ['Rosca direta na polia', 'Bíceps', 'Polia', 'Polia baixa com barra reta mantém tensão constante em toda a amplitude.'],
            ['Rosca direta com elástico', 'Bíceps', 'Elástico', 'Pise no elástico e flexione os cotovelos contra a resistência crescente.'],
            ['Rosca Scott com barra W', 'Bíceps', 'Barra W', 'Braços apoiados no banco Scott, desça até quase estender e suba controlado.'],
            ['Rosca Scott com halter unilateral', 'Bíceps', 'Halter', 'Um braço por vez no banco Scott, sem impulso do tronco.'],
            ['Rosca Scott na máquina', 'Bíceps', 'Máquina', 'Braços apoiados no pad, flexione contra a resistência da máquina.'],
            ['Rosca martelo alternada', 'Bíceps', 'Halteres', 'Pegada neutra, suba um braço de cada vez — foca braquial e antebraço.'],
            ['Rosca inclinada no banco', 'Bíceps', 'Halteres', 'Banco a 45°, braços pendendo atrás do corpo — máximo alongamento do bíceps.'],
            ['Rosca spider', 'Bíceps', 'Barra W', 'Peito apoiado no banco inclinado, braços na vertical durante todo o movimento.'],
            ['Rosca 21 com halteres', 'Bíceps', 'Halteres', '7 reps na metade inferior, 7 na superior e 7 completas.'],
            ['Rosca invertida na polia', 'Bíceps', 'Polia', 'Pegada pronada na barra da polia baixa — braquiorradial e antebraço.'],
            ['Rosca com corda na polia baixa', 'Bíceps', 'Polia', 'Corda com punhos neutros, gire levemente para fora no topo.'],
            ['Rosca de bíceps sentado com halteres', 'Bíceps', 'Halteres', 'Sentado no banco, elimina o balanço do quadril.'],

            // ---- Tríceps -------------------------------------------------------
            ['Tríceps na polia com barra V', 'Tríceps', 'Polia', 'Barra em V na polia alta, estenda os cotovelos mantendo-os colados ao tronco.'],
            ['Tríceps na polia pegada supinada', 'Tríceps', 'Polia', 'Palmas para cima, empurre a barra até estender — foco na cabeça medial.'],
            ['Tríceps testa com halteres', 'Tríceps', 'Halteres', 'Deitado, desça os halteres até a lateral da testa e estenda.'],
            ['Tríceps testa com barra W', 'Tríceps', 'Barra W', 'Pegada semipronada reduz o incômodo no punho durante a extensão.'],
            ['Tríceps francês sentado com halter', 'Tríceps', 'Halter', 'Um halter com as duas mãos, desça atrás da nuca e estenda.'],
            ['Tríceps francês unilateral', 'Tríceps', 'Halter', 'Um braço por vez atrás da cabeça, cotovelo apontado para o teto.'],
            ['Tríceps francês na polia', 'Tríceps', 'Polia', 'Sentado de costas para a polia, estenda os braços acima da cabeça.'],
            ['Tríceps coice com halter', 'Tríceps', 'Halter', 'Tronco inclinado, braço colado ao corpo, estenda o cotovelo para trás.'],
            ['Tríceps coice na polia', 'Tríceps', 'Polia', 'Mesma mecânica do coice, com tensão constante do cabo.'],
            ['Tríceps coice bilateral', 'Tríceps', 'Halteres', 'Tronco inclinado, estenda os dois braços para trás simultaneamente.'],
            ['Paralela livre', 'Tríceps', 'Peso corporal', 'Tronco na vertical e cotovelos rentes ao corpo — ênfase em tríceps.'],
            ['Paralela no gráviton', 'Tríceps', 'Máquina', 'Máquina assistida permite ajustar o quanto do peso corporal você sustenta.'],
            ['Supino fechado no smith', 'Tríceps', 'Smith', 'Barra guiada com pegada fechada, cotovelos rentes ao tronco.'],
            ['Tríceps testa na máquina', 'Tríceps', 'Máquina', 'Sentado, estenda os cotovelos contra o pad da máquina.'],
            ['Flexão diamante para tríceps', 'Tríceps', 'Peso corporal', 'Mãos formando losango sob o peito, cotovelos rentes ao corpo.'],
            ['Tríceps na polia com pegada cruzada', 'Tríceps', 'Polia', 'Mãos cruzadas nas manoplas, estenda abrindo os braços.'],
            ['Tríceps banco livre com pés suspensos', 'Tríceps', 'Peso corporal', 'Mãos num banco e pés no outro, desça até 90° de cotovelo.'],
            ['Supino fechado com halteres', 'Tríceps', 'Halteres', 'Halteres juntos sobre o peito, empurre mantendo-os em contato.'],

            // ---- Antebraço -----------------------------------------------------
            ['Farmer walk', 'Antebraço', 'Halteres', 'Caminhe segurando cargas pesadas ao lado do corpo, ombros para trás.'],
            ['Suspensão na barra (dead hang)', 'Antebraço', 'Peso corporal', 'Fique pendurado na barra pelo tempo prescrito, ombros ativos.'],

            // ---- Trapézio ------------------------------------------------------
            ['Encolhimento no smith', 'Trapézio', 'Smith', 'Barra guiada à frente das coxas, eleve os ombros sem flexionar os cotovelos.'],
            ['Encolhimento com kettlebell', 'Trapézio', 'Kettlebell', 'Kettlebells ao lado do corpo, eleve os ombros controlando a descida.'],

            // ---- Pernas --------------------------------------------------------
            ['Agachamento frontal', 'Pernas', 'Barra', 'Barra apoiada na frente dos ombros, cotovelos altos e tronco ereto.'],
            ['Agachamento com halteres', 'Pernas', 'Halteres', 'Halteres ao lado do corpo, desça mantendo o peito aberto.'],
            ['Agachamento com kettlebell (goblet)', 'Pernas', 'Kettlebell', 'Kettlebell junto ao peito, desça entre os joelhos.'],
            ['Agachamento com salto', 'Pernas', 'Peso corporal', 'Desça no agachamento e suba explosivo saindo do chão.'],
            ['Agachamento isométrico na parede', 'Pernas', 'Peso corporal', 'Costas na parede e joelhos a 90°, sustente pelo tempo prescrito.'],
            ['Agachamento com elástico', 'Pernas', 'Elástico', 'Elástico acima dos joelhos força a abertura durante todo o movimento.'],
            ['Agachamento frontal no smith', 'Pernas', 'Smith', 'Barra guiada apoiada à frente dos ombros.'],
            ['Agachamento cossaco', 'Pernas', 'Peso corporal', 'Desça lateralmente sobre uma perna mantendo a outra estendida.'],
            ['Agachamento sissy', 'Pernas', 'Peso corporal', 'Incline o tronco para trás flexionando só os joelhos — isola o quadríceps.'],
            ['Agachamento unilateral (pistol)', 'Pernas', 'Peso corporal', 'Desça sobre uma perna só, mantendo a outra estendida à frente.'],
            ['Agachamento búlgaro com halteres', 'Pernas', 'Halteres', 'Pé de trás elevado no banco, desça até o joelho quase tocar o chão.'],
            ['Cadeira extensora unilateral', 'Pernas', 'Máquina', 'Uma perna por vez, pausa de 1s na contração máxima.'],
            ['Recuo com halteres', 'Pernas', 'Halteres', 'Passo à frente, desça até o joelho de trás quase tocar o chão.'],
            ['Avanço alternado com barra', 'Pernas', 'Barra', 'Barra nas costas, dê um passo à frente mantendo o tronco ereto.'],
            ['Recuo com barra', 'Pernas', 'Barra', 'Passo para trás com a barra nas costas, tronco ereto.'],
            ['Avanço com barra', 'Pernas', 'Barra', 'Caminhada com passadas longas e a barra apoiada nas costas.'],
            ['Subida no step (step-up)', 'Pernas', 'Halteres', 'Suba no banco empurrando com o calcanhar da perna de cima.'],
            ['Subida no banco lateral', 'Pernas', 'Halteres', 'Suba de lado no banco, controlando a descida.'],
            ['Leg press 45° unilateral', 'Pernas', 'Máquina', 'Uma perna por vez na plataforma inclinada.'],
            ['Agachamento com barra', 'Pernas', 'Barra', 'Barra sobre o trapézio, tronco mais vertical e foco em quadríceps.'],
            ['Extensão de joelho com caneleira', 'Pernas', 'Caneleira', 'Sentado, estenda o joelho contra o peso da caneleira.'],
            ['Agachamento sumô com halter', 'Pernas', 'Halter', 'Pés bem afastados e pontas para fora, halter entre as pernas.'],
            ['Agachamento sumô com kettlebell', 'Pernas', 'Kettlebell', 'Base ampla, desça o kettlebell entre as pernas.'],
            ['Agachamento sumô com halteres', 'Pernas', 'Halteres', 'Base ampla, empurre o chão mantendo as costas retas.'],
            ['Agachamento com peso corporal', 'Pernas', 'Peso corporal', 'Desça até as coxas ficarem paralelas ao chão, joelhos alinhados aos pés.'],
            ['Agachamento no TRX', 'Pernas', 'TRX', 'Segure as alças e desça no agachamento com apoio parcial.'],
            ['Afundo com halter', 'Pernas', 'Halteres', 'Sem dar passo: apenas suba e desça na posição de afundo.'],

            // ---- Posterior -----------------------------------------------------
            ['Flexora em pé na máquina', 'Posterior', 'Máquina', 'Em pé, flexione uma perna de cada vez contra o rolo.'],
            ['Stiff com halteres', 'Posterior', 'Halteres', 'Joelhos quase estendidos, desça os halteres rentes à perna.'],
            ['Stiff no smith', 'Posterior', 'Smith', 'Barra guiada rente às pernas, quadril para trás.'],
            ['Levantamento terra romeno unilateral', 'Posterior', 'Halter', 'Quadril para trás sobre uma perna só, tronco e perna livre em linha.'],
            ['Good morning no smith', 'Posterior', 'Smith', 'Barra guiada nas costas, incline o tronco à frente com joelhos semiflexionados.'],
            ['Elevação pélvica com perna estendida', 'Posterior', 'Peso corporal', 'Uma perna estendida no ar, eleve o quadril com a outra.'],
            ['Terra romeno com kettlebell', 'Posterior', 'Kettlebell', 'Quadril para trás com o kettlebell rente às pernas.'],
            ['Extensão de quadril na polia', 'Posterior', 'Polia', 'Tornozeleira na polia baixa, estenda o quadril para trás.'],
            ['Hiperextensão com foco em posterior', 'Posterior', 'Peso corporal', 'Costas arredondadas de propósito e joelhos estendidos priorizam o posterior.'],
            ['Balanço com kettlebell (swing)', 'Posterior', 'Kettlebell', 'Projete o quadril à frente para lançar o kettlebell até a altura do peito.'],
            ['Terra romeno com elástico', 'Posterior', 'Elástico', 'Elástico sob os pés, quadril para trás mantendo as costas retas.'],

            // ---- Glúteos -------------------------------------------------------
            ['Elevação de quadril unilateral', 'Glúteos', 'Peso corporal', 'Uma perna apoiada, suba o quadril sem deixá-lo cair para o lado.'],
            ['Elevação de quadril na máquina', 'Glúteos', 'Máquina', 'Máquina específica de hip thrust, contraia 1s no topo.'],
            ['Elevação de quadril no smith', 'Glúteos', 'Smith', 'Barra guiada sobre o quadril, costas apoiadas no banco.'],
            ['Elevação pélvica com elástico', 'Glúteos', 'Elástico', 'Elástico acima dos joelhos força a abertura durante a subida.'],
            ['Elevação pélvica solo', 'Glúteos', 'Peso corporal', 'Deitado com os pés no chão, eleve o quadril contraindo o glúteo.'],
            ['Glúteo coice com caneleira', 'Glúteos', 'Caneleira', 'Em quatro apoios, estenda o quadril para trás com a caneleira.'],
            ['Abdução de quadril na polia', 'Glúteos', 'Polia', 'Tornozeleira na polia baixa, afaste a perna lateralmente.'],
            ['Abdução de quadril deitado de lado', 'Glúteos', 'Peso corporal', 'Deitado de lado, eleve a perna de cima mantendo o quadril fixo.'],
            ['Abdução com elástico deitado (clamshell)', 'Glúteos', 'Elástico', 'Joelhos flexionados, abra a perna de cima contra o elástico.'],
            ['Abdução em pé com elástico', 'Glúteos', 'Elástico', 'Elástico nos tornozelos, afaste uma perna lateralmente.'],
            ['Deslocamento lateral com miniband', 'Glúteos', 'Elástico', 'Elástico acima dos joelhos, caminhe de lado em semiagachamento.'],
            ['Adução de quadril na polia', 'Glúteos', 'Polia', 'Tornozeleira na polia, traga a perna em direção à linha média.'],
            ['Agachamento sumô profundo', 'Glúteos', 'Halter', 'Base ampla e pontas para fora, desça bem o quadril.'],
            ['Passada profunda para glúteo', 'Glúteos', 'Halteres', 'Passos longos e descida profunda enfatizam o glúteo.'],
            ['Agachamento búlgaro com foco em glúteo', 'Glúteos', 'Halteres', 'Tronco inclinado à frente desloca o estímulo para o glúteo.'],
            ['Subida no step alta para glúteo', 'Glúteos', 'Halteres', 'Caixa alta e empurrão pelo calcanhar.'],
            ['Agachamento com elástico nos joelhos', 'Glúteos', 'Elástico', 'Abra os joelhos contra o elástico durante toda a descida.'],

            // ---- Panturrilha ---------------------------------------------------
            ['Panturrilha em pé com halteres', 'Panturrilha', 'Halteres', 'Halteres ao lado do corpo, suba na ponta dos pés.'],
            ['Panturrilha no smith', 'Panturrilha', 'Smith', 'Barra guiada nas costas e pontas dos pés num step.'],
            ['Panturrilha em pé no step', 'Panturrilha', 'Peso corporal', 'Calcanhares livres no degrau, desça bem antes de subir.'],
            ['Panturrilha unilateral no leg press', 'Panturrilha', 'Máquina', 'Uma perna por vez na plataforma, amplitude total.'],

            // ---- Core ----------------------------------------------------------
            ['Prancha alta', 'Core', 'Peso corporal', 'Apoio nas mãos com os braços estendidos, quadril nivelado.'],
            ['Prancha lateral com elevação de quadril', 'Core', 'Peso corporal', 'Na prancha lateral, desça e suba o quadril sem tocar o chão.'],
            ['Prancha lateral com rotação', 'Core', 'Peso corporal', 'Gire o tronco passando o braço livre por baixo do corpo.'],
            ['Prancha com toque no ombro', 'Core', 'Peso corporal', 'Na prancha alta, toque o ombro oposto sem balançar o quadril.'],
            ['Renegade row', 'Core', 'Halteres', 'Na prancha sobre os halteres, puxe um de cada vez.'],
            ['Abdominal declinado', 'Core', 'Peso corporal', 'Banco declinado, suba o tronco sem puxar o pescoço.'],
            ['Abdominal remador', 'Core', 'Peso corporal', 'Sentado, estenda e recolha tronco e pernas simultaneamente.'],
            ['Abdominal canivete', 'Core', 'Peso corporal', 'Deitado, suba tronco e pernas ao mesmo tempo tocando os pés.'],
            ['Abdominal bicicleta', 'Core', 'Peso corporal', 'Cotovelo em direção ao joelho oposto, alternando os lados.'],
            ['Abdominal V-up', 'Core', 'Peso corporal', 'Forme um V tocando as mãos nos pés com pernas estendidas.'],
            ['Abdominal infra suspenso com pernas estendidas', 'Core', 'Peso corporal', 'Pendurado na barra, eleve as pernas estendidas até a linha do quadril.'],
            ['Abdominal infra suspenso flexionando as pernas', 'Core', 'Peso corporal', 'Pendurado, traga os joelhos até a altura do peito.'],
            ['Abdominal infra no banco', 'Core', 'Peso corporal', 'Deitado no banco segurando as bordas, eleve as pernas estendidas.'],
            ['Toes to bar', 'Core', 'Peso corporal', 'Pendurado, leve as pontas dos pés até tocar a barra.'],
            ['Dead bug', 'Core', 'Peso corporal', 'Deitado, estenda braço e perna opostos sem descolar a lombar do chão.'],
            ['Bird dog', 'Core', 'Peso corporal', 'Em quatro apoios, estenda braço e perna opostos e segure 2s.'],
            ['Hollow hold', 'Core', 'Peso corporal', 'Deitado com lombar colada ao chão, sustente braços e pernas elevados.'],
            ['Hollow rock', 'Core', 'Peso corporal', 'Na posição hollow, balance o corpo mantendo a lombar apoiada.'],
            ['Rotação russa com medicine ball', 'Core', 'Medicine ball', 'Gire o tronco levando a bola de um lado ao outro.'],
            ['Pallof press', 'Core', 'Polia', 'De lado para a polia, estenda os braços resistindo à rotação.'],
            ['Flexão lateral com halter', 'Core', 'Halter', 'Em pé, incline o tronco para o lado do halter e retorne.'],
            ['Roda abdominal ajoelhado', 'Core', 'Equipamento', 'Ajoelhado, role a roda à frente sem deixar a lombar arquear.'],
            ['Mountain climber lento', 'Core', 'Peso corporal', 'Na prancha alta, traga um joelho por vez ao peito com controle.'],
            ['Rollout na barra', 'Core', 'Barra', 'Ajoelhado, role a barra à frente mantendo o core firme.'],
            ['Prancha com elevação de perna', 'Core', 'Peso corporal', 'Na prancha, eleve uma perna por vez sem girar o quadril.'],
            ['Prancha com elevação de braço', 'Core', 'Peso corporal', 'Estenda um braço à frente mantendo o quadril nivelado.'],
            ['Abdominal supra máquina', 'Core', 'Máquina', 'Sentado na máquina de abdominal, flexione o tronco contra o pad.'],
            ['Abdominal oblíquo na polia', 'Core', 'Polia', 'Em pé, flexione o tronco lateralmente puxando o cabo.'],
            ['Abdominal supra solo completo', 'Core', 'Peso corporal', 'Suba o tronco até sentar, sem impulso dos braços.'],
            ['Prancha dinâmica (up-down)', 'Core', 'Peso corporal', 'Alterne entre prancha alta e de antebraços, um braço por vez.'],

            // ---- Funcional -----------------------------------------------------
            ['Burpee com flexão', 'Funcional', 'Peso corporal', 'Agache, jogue as pernas para trás, faça a flexão e salte.'],
            ['Burpee sem flexão', 'Funcional', 'Peso corporal', 'Versão simplificada: agache, prancha, volta e salto.'],
            ['Burpee box jump', 'Funcional', 'Step', 'Termine o burpee saltando sobre a caixa.'],
            ['Polichinelo com agachamento', 'Funcional', 'Peso corporal', 'Alterne polichinelos com um agachamento a cada repetição.'],
            ['Pular corda alternando os pés', 'Funcional', 'Corda', 'Salte alternando o apoio entre os pés, ritmo constante.'],
            ['Pular corda duplo', 'Funcional', 'Corda', 'A corda passa duas vezes por salto — exige potência.'],
            ['Corrida estacionária', 'Funcional', 'Peso corporal', 'Corra no lugar elevando bem os joelhos.'],
            ['Corrida com elevação de joelhos', 'Funcional', 'Peso corporal', 'Joelhos até a altura do quadril em ritmo acelerado.'],
            ['Corrida com calcanhar no glúteo', 'Funcional', 'Peso corporal', 'Leve o calcanhar até o glúteo a cada passada.'],
            ['Deslocamento lateral (skater)', 'Funcional', 'Peso corporal', 'Salte lateralmente de uma perna para a outra, aterrissando suave.'],
            ['Box jump', 'Funcional', 'Step', 'Salte sobre a caixa e desça controlado.'],
            ['Salto horizontal', 'Funcional', 'Peso corporal', 'Salte o mais longe possível com os dois pés, aterrissando agachado.'],
            ['Salto vertical', 'Funcional', 'Peso corporal', 'Agache e salte o mais alto possível, aterrissando suave.'],
            ['Corda naval alternada', 'Funcional', 'Corda', 'Alterne os braços fazendo ondas com a corda.'],
            ['Corda naval simultânea', 'Funcional', 'Corda', 'Movimente as duas pontas juntas, criando ondas amplas.'],
            ['Swing com kettlebell americano', 'Funcional', 'Kettlebell', 'O kettlebell sobe até acima da cabeça no fim do movimento.'],
            ['Clean com kettlebell', 'Funcional', 'Kettlebell', 'Puxe o kettlebell do chão até a posição de rack no ombro.'],
            ['Turkish get-up', 'Funcional', 'Kettlebell', 'Do solo até em pé mantendo o kettlebell sustentado acima.'],
            ['Thruster com halteres', 'Funcional', 'Halteres', 'Agache e, ao subir, empurre os halteres acima da cabeça.'],
            ['Thruster com barra', 'Funcional', 'Barra', 'Agachamento frontal seguido de desenvolvimento em um movimento só.'],
            ['Clean and press com barra', 'Funcional', 'Barra', 'Puxe a barra até os ombros e empurre acima da cabeça.'],
            ['Wall ball', 'Funcional', 'Medicine ball', 'Agache e arremesse a bola contra a parede acima de uma marca.'],
            ['Arremesso de medicine ball no solo', 'Funcional', 'Medicine ball', 'Levante a bola acima da cabeça e arremesse com força no chão.'],
            ['Corda naval', 'Funcional', 'Corda', 'Faça ondas com a corda mantendo a posição de semiagachamento.'],
            ['Salto no step alternado', 'Funcional', 'Step', 'Alterne rapidamente os pés sobre o step.'],
            ['Bear crawl', 'Funcional', 'Peso corporal', 'Engatinhe com os joelhos a poucos centímetros do chão.'],
            ['Sprint na esteira', 'Funcional', 'Esteira', 'Tiros curtos em velocidade máxima com intervalos de recuperação.'],
            ['Caminhada inclinada na esteira', 'Funcional', 'Esteira', 'Inclinação alta em ritmo moderado, sem se apoiar nas barras.'],
            ['Bicicleta ergométrica', 'Funcional', 'Bicicleta', 'Pedale mantendo a cadência e a resistência prescritas.'],
            ['Remo ergômetro', 'Funcional', 'Remo', 'Empurre com as pernas, incline o tronco e puxe com os braços.'],
            ['Elíptico', 'Funcional', 'Equipamento', 'Movimento contínuo de baixo impacto, com braços e pernas juntos.'],
            ['Airbike', 'Funcional', 'Bicicleta', 'Pedale usando braços e pernas em intensidade alta.'],
            ['Salto com afundo alternado', 'Funcional', 'Peso corporal', 'Salte trocando a perna da frente no ar.'],
        ];
    }
}
