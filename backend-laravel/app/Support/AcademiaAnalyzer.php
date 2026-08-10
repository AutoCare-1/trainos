<?php

namespace App\Support;

use Anthropic\Client;
use Anthropic\Messages\Base64ImageSource;
use Anthropic\Messages\ImageBlockParam;
use Anthropic\Messages\TextBlockParam;
use RuntimeException;

/** Espelha backend/src/services/academiaAnalyzer.ts do Node. */
class AcademiaAnalyzer
{
    private const MODEL = 'claude-haiku-4-5-20251001';

    private const MEDIA_TYPE_POR_EXTENSAO = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    private const SYSTEM_PROMPT = <<<'PROMPT'
Você é um especialista em avaliação de academias e prescrição de exercícios pra um
app de personal trainer cujo público é majoritariamente idosos (Clube Mais).

Analise TODAS as imagens recebidas (podem ser fotos separadas ou frames extraídos de um vídeo
da mesma academia) e identifique todas as máquinas, equipamentos e superfícies de treino
visíveis, consolidando o que aparece repetido em mais de uma imagem em uma única entrada.

Regras:
- Seja específico ("Leg press 45°" em vez de "máquina de perna").
- Não invente máquinas que não estejam claramente visíveis.
- Para cada máquina, avalie se é segura e adequada pra um público mais velho (evite marcar
  como recomendável equipamentos de alto impacto ou alta complexidade técnica).
- Agrupe as zonas identificadas (ex: "Musculação", "Cardio", "Piso livre").
- Aponte lacunas relevantes de cobertura muscular.
- Nos campos de texto ("notes", "gaps"), NÃO use emojis.

Responda APENAS com um JSON válido, sem texto antes ou depois, neste formato exato:
{
  "machines": [
    {
      "name": "Leg Press 45°",
      "category": "lower_body",
      "primary_muscles": ["quadríceps", "glúteos"],
      "secondary_muscles": ["panturrilha"],
      "confidence": 0.9,
      "notes": "máquina com seletor de pino, segura pra idosos"
    }
  ],
  "zones_identified": ["Musculação", "Cardio"],
  "coverage_estimate": "boa",
  "gaps": ["Pouca opção de cardio de baixo impacto"],
  "notes": "observação geral sobre a academia"
}
PROMPT;

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
     * Analisa um conjunto de imagens da mesma submissão (fotos ou frames de vídeo) num único chamado.
     *
     * @param  string[]  $caminhosImagens  caminhos absolutos no disco
     * @return array{machines: array{machines: array}, zones_identified: array, coverage_estimate: ?string, gaps: array, notes: ?string}
     */
    public static function analisarMidiaAcademia(array $caminhosImagens, ?string $professionalId = null): array
    {
        $blocos = [];
        foreach ($caminhosImagens as $caminho) {
            $extensao = strtolower(pathinfo($caminho, PATHINFO_EXTENSION));
            $mediaType = self::MEDIA_TYPE_POR_EXTENSAO[$extensao] ?? 'image/jpeg';
            $dados = base64_encode(file_get_contents($caminho));

            $blocos[] = ImageBlockParam::with(
                source: Base64ImageSource::with(data: $dados, mediaType: $mediaType),
            );
        }
        $blocos[] = TextBlockParam::with(
            text: 'Total de imagens: '.count($caminhosImagens).'. Analise conforme as instruções.',
        );

        $response = self::client()->messages->create(
            model: self::MODEL,
            maxTokens: 2000,
            system: self::SYSTEM_PROMPT,
            messages: [['role' => 'user', 'content' => $blocos]],
        );

        IaUsage::registrar('academia_analise', $response, $professionalId);

        $bloco = $response->content[0] ?? null;
        $texto = ($bloco && $bloco->type === 'text') ? $bloco->text : '';
        if (! preg_match('/\{[\s\S]*\}/', $texto, $match)) {
            throw new RuntimeException('Resposta da IA não trouxe JSON válido');
        }

        $parsed = json_decode($match[0], true);

        return [
            'machines' => ['machines' => $parsed['machines'] ?? []],
            'zones_identified' => $parsed['zones_identified'] ?? [],
            'coverage_estimate' => $parsed['coverage_estimate'] ?? null,
            'gaps' => $parsed['gaps'] ?? [],
            'notes' => $parsed['notes'] ?? null,
        ];
    }
}
