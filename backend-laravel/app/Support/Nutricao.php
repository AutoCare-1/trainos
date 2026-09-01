<?php

namespace App\Support;

use Anthropic\Client;
use App\Models\Student;
use RuntimeException;

/**
 * Orientação GERAL de alimentação pré/pós-treino — nunca prescrição.
 *
 * A fronteira não é preciosismo: no Brasil, prescrição dietética é privativa
 * do nutricionista (Lei 8.234/91). Quem responde pelo aluno dentro do app é um
 * profissional de Educação Física, que pode orientar de forma geral mas não
 * pode montar cardápio. Se este recurso passar dessa linha, quem fica exposto
 * é o personal que assina o TrainOS — não o TrainOS.
 *
 * Por isso o prompt proíbe explicitamente quantidade, caloria, macro e
 * suplemento, e manda encaminhar ao nutricionista quando o pedido exige
 * individualização. E por isso a resposta é gravada (NutritionSuggestion): o
 * personal precisa ver o que foi dito ao aluno dele.
 */
class Nutricao
{
    private const MODEL = 'claude-haiku-4-5-20251001';

    /**
     * Sinais na anamnese que tiram o pedido do território "orientação geral".
     *
     * Aqui a IA não tenta responder com mais cuidado — ela não responde. Uma
     * pessoa com diabetes, doença renal ou histórico de transtorno alimentar
     * precisa de individualização, que é exatamente o que este recurso não
     * pode fazer. Errar pra menos aqui custa uma resposta a menos; errar pra
     * mais custa a saúde de alguém.
     */
    private const SINAIS_QUE_EXIGEM_NUTRICIONISTA = [
        // Cada acento precisa estar previsto: "diabet" sozinho NÃO casa com
        // "diabético" (é ≠ e), e era assim que a trava deixava passar quem
        // escrevia a condição do jeito mais natural em português.
        'diab[eé]t', 'insulin', 'renal', 'rim', 'rins', 'hep[aá]t', 'f[ií]gado', 'gastrite', 'refluxo',
        'bari[aá]tric', 'cirurgia', 'anorex', 'bulimi', 'transtorno alimentar', 'compuls',
        'cel[ií]ac', 'gl[uú]ten', 'lactose', 'al[eé]rgi', 'intoler[aâ]nc',
        // "gestante/gestação" escrito por extenso, e não o prefixo "gest": solto,
        // ele casava dentro de "sugestão" e mandava o aluno pro nutricionista.
        'gestante', 'gesta[çc][ãa]o', 'gr[aá]vid',
        'amament', 'hipertens', 'press[aã]o alta', 'colesterol', 'tireo[ií]d', 'anemia',
    ];

    private static ?Client $client = null;

    private static function client(): Client
    {
        if (! self::$client) {
            $apiKey = config('services.anthropic.api_key');
            if (! $apiKey) {
                throw new RuntimeException('ANTHROPIC_API_KEY não configurada no .env');
            }
            self::$client = new Client(apiKey: $apiKey);
        }

        return self::$client;
    }

    /**
     * O aluno declarou algo que exige nutricionista?
     *
     * Varre o texto livre da anamnese e das observações de saúde. É de
     * propósito grosseiro (substring, sem NLP): a decisão que ele governa é
     * "responde ou encaminha", e encaminhar de mais é aceitável.
     */
    public static function exigeNutricionista(Student $student): bool
    {
        $textos = [(string) $student->health_notes];

        foreach ((array) $student->anamnese as $valor) {
            if (is_string($valor)) {
                $textos[] = $valor;
            }
        }
        foreach ((array) $student->par_q_answers as $chave => $valor) {
            // No PAR-Q o que importa é o "sim": a chave diz qual condição é.
            if ($valor === true || $valor === 'sim') {
                $textos[] = (string) $chave;
            }
        }

        $tudo = mb_strtolower(implode(' ', $textos));

        foreach (self::SINAIS_QUE_EXIGEM_NUTRICIONISTA as $sinal) {
            // \b no início: sem ele, "rim" casava dentro de "primeira" e
            // "gest" dentro de "sugestão" — e o aluno ficava encaminhado ao
            // nutricionista pra sempre por ter escrito "primeira vez na
            // academia" na anamnese. Só o começo é ancorado, porque o que
            // varia é o sufixo (diabet-es, diabét-ico, alergi-a/co).
            if (preg_match("/\b{$sinal}/u", $tudo) === 1) {
                return true;
            }
        }

        return false;
    }

    public static function textoDeEncaminhamento(): string
    {
        return 'Pelo que você registrou na sua avaliação de saúde, essa parte precisa de um '
            .'nutricionista — é ele quem pode montar orientação alimentar individual pro seu caso. '
            .'Fala com seu professor que ele te encaminha. Enquanto isso, seu treino segue normal.';
    }

    private static function systemPrompt(string $momento, Student $student): string
    {
        $primeiroNome = explode(' ', $student->name)[0];
        $objetivo = $student->objective ?: 'não informado';
        $quando = $momento === 'pre_treino' ? 'ANTES do treino' : 'DEPOIS do treino';

        return <<<PROMPT
        Você responde no app de um profissional de Educação Física, para o aluno {$primeiroNome},
        que perguntou sobre alimentação {$quando}.

        Objetivo declarado do aluno: {$objetivo}.

        O QUE VOCÊ PODE FAZER:
        - Dar orientação geral e educativa sobre alimentação em torno do treino: que tipo de
          alimento costuma cair bem, quanto tempo antes/depois costuma ser confortável, por que.
        - Falar em termos de COMIDA DE VERDADE e exemplos comuns no Brasil (fruta, pão, arroz e
          feijão, ovo, iogurte, tapioca).
        - Lembrar de hidratação.

        O QUE VOCÊ NÃO PODE FAZER, EM HIPÓTESE NENHUMA:
        - NÃO diga quantidades: nada de gramas, mililitros, colheres, "2 fatias", porções medidas.
        - NÃO fale em calorias, macronutrientes, déficit, superávit ou percentuais.
        - NÃO recomende suplemento nenhum (whey, creatina, pré-treino, vitamina, nada).
        - NÃO monte cardápio, plano alimentar, lista de refeições do dia nem rotina fechada.
        - NÃO dê orientação para emagrecer ou ganhar peso como objetivo — isso é dieta, e dieta é
          do nutricionista.
        - NÃO oriente sobre nenhuma condição de saúde, doença, alergia ou restrição. Se o aluno
          mencionar qualquer uma, diga que isso é com o nutricionista e pare por aí.

        Motivo: no Brasil, prescrição dietética é privativa do nutricionista. O que você dá aqui é
        orientação geral, não prescrição — e a diferença é justamente quantidade, individualização
        e tratamento de condição de saúde.

        COMO RESPONDER:
        - Português do Brasil, tom de quem conversa no WhatsApp, direto.
        - No máximo 4 frases curtas. Nada de lista longa nem textão.
        - Termine lembrando, em uma frase, que pra um plano alimentar de verdade quem faz é o
          nutricionista.
        - Sem emojis, sem prefixo, só a mensagem.
        PROMPT;
    }

    /**
     * @return array{resposta: string, encaminhou: bool}
     */
    public static function sugerir(string $momento, Student $student): array
    {
        if (self::exigeNutricionista($student)) {
            return ['resposta' => self::textoDeEncaminhamento(), 'encaminhou' => true];
        }

        $response = self::client()->messages->create(
            model: self::MODEL,
            maxTokens: 400,
            system: self::systemPrompt($momento, $student),
            messages: [[
                'role' => 'user',
                'content' => $momento === 'pre_treino'
                    ? 'O que dá pra comer antes de treinar?'
                    : 'O que dá pra comer depois de treinar?',
            ]],
        );

        IaUsage::registrar('nutricao_sugestao', $response, $student->professional_id);

        $bloco = $response->content[0] ?? null;
        $texto = ($bloco && $bloco->type === 'text') ? trim($bloco->text) : '';

        if ($texto === '') {
            throw new RuntimeException('Resposta vazia da IA para sugestão de nutrição.');
        }

        return ['resposta' => $texto, 'encaminhou' => false];
    }
}
