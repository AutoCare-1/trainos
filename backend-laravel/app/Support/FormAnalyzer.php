<?php

namespace App\Support;

use Anthropic\Client;
use Anthropic\Messages\Base64ImageSource;
use Anthropic\Messages\ImageBlockParam;
use Anthropic\Messages\TextBlockParam;
use RuntimeException;

/** Espelha backend/src/services/formAnalyzer.ts do Node. */
class FormAnalyzer
{
    private const MODEL = 'claude-haiku-4-5-20251001';

    private const MEDIA_TYPE_POR_EXTENSAO = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
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

    private static function buildSystemPrompt(string $exerciseName, string $nivelAluno): string
    {
        return <<<PROMPT
        Você é um personal trainer experiente analisando a FORMA de execução de um exercício,
        pra um app cujo público é majoritariamente idoso (Clube Mais).

        ## EXERCÍCIO
        {$exerciseName}

        ## NÍVEL DO ALUNO
        {$nivelAluno}

        ## MÍDIA RECEBIDA
        3 frames de um vídeo curto de uma única série: início, meio e fim do movimento.

        ## SUA TAREFA

        Avalie:
        1. **Amplitude**: usa a amplitude completa e segura pro nível dele?
        2. **Postura/Alinhamento**: coluna, articulações, posição inicial corretas?
        3. **Tempo/Velocidade**: desce/sobe controlado ou usa impulso?
        4. **Compensações**: tá "trapaceando" ou desviando do padrão seguro?
        5. **Segurança**: há risco de lesão visível?

        ## RESPONDA APENAS COM JSON, sem texto antes ou depois, neste formato exato:

        {
          "amplitude": "descrição breve (1-2 linhas)",
          "posture": "descrição breve",
          "tempo": "descrição breve",
          "compensations": "descrição ou 'nenhuma compensação detectada'",
          "safety_notes": "risco detectado ou 'seguro'",
          "three_key_feedback": [
            { "title": "Amplitude", "feedback": "descrição específica e acionável", "priority": "good" },
            { "title": "Postura", "feedback": "...", "priority": "warning" },
            { "title": "Velocidade", "feedback": "...", "priority": "good" }
          ]
        }

        ## CRÍTICO

        - Seja ESPECÍFICO e ACIONÁVEL: "coluna boa" é ruim, "coluna inclinando ~20°, tenta manter neutra" é bom.
        - Respeite o nível informado — um iniciante ou idoso não tem a mesma amplitude/mobilidade de um avançado.
        - Priorize segurança antes de performance.
        - Escolha os 3 feedbacks que mais importam, nunca mais que isso.
        - "priority": "good" = algo funcionando bem (motivar); "warning" = ajuste importante sem risco imediato;
          "critical" = parar e corrigir antes da próxima série.
        - NÃO use emojis em nenhum campo de texto.
        PROMPT;
    }

    /**
     * Analisa 3 frames-chave (início/meio/fim) de uma série e retorna feedback estruturado de forma.
     *
     * @param  string[]  $framesPaths
     * @return array{amplitude_assessment: string, posture_assessment: string, tempo_assessment: string, compensations: string, safety_notes: string, three_key_feedback: array}
     */
    public static function analisarForma(array $framesPaths, string $exerciseName, string $nivelAluno, ?string $professionalId = null): array
    {
        $blocos = [];
        foreach ($framesPaths as $caminho) {
            $extensao = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
            $mediaType = self::MEDIA_TYPE_POR_EXTENSAO[$extensao] ?? 'image/jpeg';
            $dados = base64_encode(file_get_contents($caminho));

            $blocos[] = ImageBlockParam::with(source: Base64ImageSource::with(data: $dados, mediaType: $mediaType));
        }
        $blocos[] = TextBlockParam::with(text: 'Analise a forma conforme as instruções.');

        $response = self::client()->messages->create(
            model: self::MODEL,
            maxTokens: 800,
            system: self::buildSystemPrompt($exerciseName, $nivelAluno),
            messages: [['role' => 'user', 'content' => $blocos]],
        );

        IaUsage::registrar('analisar_forma', $response, $professionalId);

        $bloco = $response->content[0] ?? null;
        $texto = ($bloco && $bloco->type === 'text') ? $bloco->text : '';
        if (! preg_match('/\{[\s\S]*\}/', $texto, $match)) {
            throw new RuntimeException('Resposta da IA não trouxe JSON válido');
        }

        $parsed = json_decode($match[0], true);

        return [
            'amplitude_assessment' => $parsed['amplitude'] ?? '',
            'posture_assessment' => $parsed['posture'] ?? '',
            'tempo_assessment' => $parsed['tempo'] ?? '',
            'compensations' => $parsed['compensations'] ?? 'Nenhuma',
            'safety_notes' => $parsed['safety_notes'] ?? 'Seguro',
            'three_key_feedback' => $parsed['three_key_feedback'] ?? [],
        ];
    }
}
