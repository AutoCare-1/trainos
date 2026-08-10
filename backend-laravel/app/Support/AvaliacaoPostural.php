<?php

namespace App\Support;

use Anthropic\Client;
use Anthropic\Messages\Base64ImageSource;
use Anthropic\Messages\ImageBlockParam;
use Anthropic\Messages\TextBlockParam;
use RuntimeException;

/**
 * Avaliação postural a partir de 3 fotos (frente/lado/costas) — separada da
 * "Evolução física" (1 foto, comparação livre) porque aqui o que importa é
 * comparar ângulo com ângulo (frente da vez passada vs frente de agora, etc.)
 * pra observar alinhamento de ombros, curvatura da coluna e simetria.
 */
class AvaliacaoPostural
{
    private const MODEL = 'claude-haiku-4-5-20251001';

    private const MEDIA_TYPE_POR_EXTENSAO = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];

    // Regras de segurança compartilhadas pelos dois prompts — nunca diagnóstico,
    // sempre observação descritiva, sempre encaminhar pra avaliação profissional
    // presencial quando notar algo que pareça relevante.
    private const REGRAS_SEGURANCA = <<<'PROMPT'
Regras importantes:
- Você NÃO é médico, fisioterapeuta nem ortopedista. NÃO diagnostique nenhuma condição
  (ex: nunca diga "escoliose", "hérnia" ou qualquer nome de patologia) — descreva só o
  que é visualmente observável (ex: "o ombro direito parece um pouco mais elevado que
  o esquerdo"), sem nomear causas ou consequências médicas.
- Se notar algo que pareça relevante (assimetria visível, desalinhamento notável),
  oriente com naturalidade a comentar com o professor responsável ou buscar avaliação
  profissional presencial — não minimize, mas também não alarme.
- Tom acolhedor e direto, sem jargão técnico. Nunca faça qualquer julgamento sobre peso,
  composição corporal ou estética — fale só de postura/alinhamento.
- Ser curta: 3 a 5 frases.
- NÃO usar emojis nem outros pictogramas — só texto.

Responda SEMPRE apenas com a mensagem para o aluno, em português do Brasil, sem prefixos
como "Coach:" nem aspas.
PROMPT;

    private const SYSTEM_PRIMEIRA = <<<'PROMPT'
Você é a Coach IA de um app de personal trainer, escrevendo pro aluno logo após ele
registrar sua primeira avaliação postural (3 fotos: frente, lado e costas).

Essa é a primeira avaliação — não existe nenhuma anterior pra comparar. Descreva o que
observar de alinhamento postural nas 3 fotos (ombros, coluna, simetria geral) como um
"ponto de partida", sem comparar com nada.

PROMPT.self::REGRAS_SEGURANCA;

    private const SYSTEM_COMPARACAO = <<<'PROMPT'
Você é a Coach IA de um app de personal trainer, escrevendo pro aluno logo após ele
registrar uma nova avaliação postural, comparando com a anterior.

Você vai receber 6 imagens: primeiro as 3 fotos ANTERIORES (frente, lado, costas, nessa
ordem) e depois as 3 fotos NOVAS (frente, lado, costas, na mesma ordem) — compare cada
ângulo com o mesmo ângulo da vez passada (frente com frente, lado com lado, costas com
costas). Comente o que for relevante e realmente visível; se notar melhora, parabenize
com entusiasmo genuíno; se não der pra notar diferença clara, incentive a constância sem
soar decepcionado.

PROMPT.self::REGRAS_SEGURANCA;

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

    /** @return array{data: string, mediaType: string} */
    private static function lerImagemBase64(string $caminhoAbsoluto): array
    {
        $extensao = strtolower(pathinfo($caminhoAbsoluto, PATHINFO_EXTENSION));
        $mediaType = self::MEDIA_TYPE_POR_EXTENSAO[$extensao] ?? 'image/jpeg';

        return ['data' => base64_encode(file_get_contents($caminhoAbsoluto)), 'mediaType' => $mediaType];
    }

    private static function primeiroNome(string $nomeAluno): string
    {
        return explode(' ', $nomeAluno)[0];
    }

    private static function blocoImagem(string $rotulo, string $caminhoAbsoluto): array
    {
        $img = self::lerImagemBase64($caminhoAbsoluto);

        return [
            TextBlockParam::with(text: $rotulo),
            ImageBlockParam::with(source: Base64ImageSource::with(data: $img['data'], mediaType: $img['mediaType'])),
        ];
    }

    /** Primeira avaliação postural do aluno: sem comparação, só as 3 fotos. */
    public static function avaliarPrimeira(string $nomeAluno, string $frentePath, string $ladoPath, string $costasPath): string
    {
        $conteudo = [
            TextBlockParam::with(text: 'Nome do aluno: '.self::primeiroNome($nomeAluno).'. Primeira avaliação postural.'),
            ...self::blocoImagem('Foto de frente:', $frentePath),
            ...self::blocoImagem('Foto de lado:', $ladoPath),
            ...self::blocoImagem('Foto de costas:', $costasPath),
        ];

        $response = self::client()->messages->create(
            model: self::MODEL,
            maxTokens: 350,
            system: self::SYSTEM_PRIMEIRA,
            messages: [['role' => 'user', 'content' => $conteudo]],
        );

        IaUsage::registrar('avaliacao_postural', $response);

        $bloco = $response->content[0] ?? null;

        return ($bloco && $bloco->type === 'text')
            ? trim($bloco->text)
            : 'Avaliação postural registrada! Esse é o seu ponto de partida.';
    }

    /**
     * Compara com a avaliação postural anterior, ângulo a ângulo.
     *
     * @param  array{frente: string, lado: string, costas: string}  $anterior
     * @param  array{frente: string, lado: string, costas: string}  $novo
     */
    public static function avaliarComparacao(string $nomeAluno, array $anterior, array $novo): string
    {
        $conteudo = [
            TextBlockParam::with(text: 'Nome do aluno: '.self::primeiroNome($nomeAluno).'. Avaliação postural anterior:'),
            ...self::blocoImagem('Frente (anterior):', $anterior['frente']),
            ...self::blocoImagem('Lado (anterior):', $anterior['lado']),
            ...self::blocoImagem('Costas (anterior):', $anterior['costas']),
            TextBlockParam::with(text: 'Avaliação postural nova (agora):'),
            ...self::blocoImagem('Frente (nova):', $novo['frente']),
            ...self::blocoImagem('Lado (nova):', $novo['lado']),
            ...self::blocoImagem('Costas (nova):', $novo['costas']),
        ];

        $response = self::client()->messages->create(
            model: self::MODEL,
            maxTokens: 350,
            system: self::SYSTEM_COMPARACAO,
            messages: [['role' => 'user', 'content' => $conteudo]],
        );

        IaUsage::registrar('avaliacao_postural', $response);

        $bloco = $response->content[0] ?? null;

        return ($bloco && $bloco->type === 'text') ? trim($bloco->text) : 'Avaliação postural registrada! Continue assim.';
    }
}
