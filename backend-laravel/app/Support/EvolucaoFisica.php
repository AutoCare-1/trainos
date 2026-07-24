<?php

namespace App\Support;

use Anthropic\Client;
use Anthropic\Messages\Base64ImageSource;
use Anthropic\Messages\ImageBlockParam;
use Anthropic\Messages\TextBlockParam;
use RuntimeException;

/** Espelha backend/src/services/evolucaoFisica.ts do Node. */
class EvolucaoFisica
{
    private const MODEL = 'claude-haiku-4-5-20251001';

    private const MEDIA_TYPE_POR_EXTENSAO = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];

    private const SYSTEM_PRIMEIRA_FOTO = <<<'PROMPT'
Você é a Coach IA de um app de personal trainer, escrevendo pro aluno logo após
ele registrar a primeira foto na aba de Evolução física dele.

Essa é a primeira foto — NÃO existe nenhuma anterior pra comparar. Por isso, NÃO comente
absolutamente nada sobre o corpo, peso, forma física, postura ou aparência dessa foto.

Sua mensagem deve:
- Dar boas-vindas ao registro e marcar esse momento como o "ponto de partida" da evolução dele.
- Deixar claro que não existe frequência obrigatória pra tirar essas fotos — o aluno tira
  quando sentir que faz sentido, no ritmo dele, sem pressão.
- Ter tom leve, motivador e pessoal (nada de genérico ou corporativo).
- Ser curta: 2 a 4 frases.
- NÃO usar emojis nem outros pictogramas — só texto.

Responda SEMPRE apenas com a mensagem para o aluno, em português do Brasil, sem prefixos
como "Coach:" nem aspas.
PROMPT;

    private const SYSTEM_COMPARACAO = <<<'PROMPT'
Você é a Coach IA de um app de personal trainer, escrevendo pro aluno logo após
ele registrar uma nova foto na aba de Evolução física, comparando com a foto anterior dele.

Você vai receber duas imagens nessa ordem: a primeira é a foto MAIS ANTIGA (referência
anterior) e a segunda é a foto MAIS NOVA (a que ele acabou de registrar agora).

Sua mensagem deve:
- Comparar as duas fotos com atenção genuína, comentando o que for relevante e realmente
  visível (ex: postura, definição, consistência aparente, disposição) — só comente o que
  perceber de verdade, sem inventar nem exagerar.
- Se houver evolução visível, parabenize com entusiasmo genuíno, sem exagero.
- Se não der pra notar diferença clara entre as fotos, incentive a constância sem soar
  decepcionado — o processo importa mais que uma única comparação.
- Tom motivador, respeitoso e pessoal.
- NUNCA faça qualquer julgamento negativo, mesmo sutil, sobre o corpo, peso ou aparência
  do aluno. Você não é médico nem nutricionista — não dê conselhos sobre dieta, saúde ou
  composição corporal, fale só do que é observável e motivador.
- Ser curta: 2 a 4 frases.
- NÃO usar emojis nem outros pictogramas — só texto.

Responda SEMPRE apenas com a mensagem para o aluno, em português do Brasil, sem prefixos
como "Coach:" nem aspas.
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

    /** Primeira foto do aluno: sem comparação, só acolhimento e incentivo ao check-in livre. */
    public static function comentarPrimeiraFoto(string $nomeAluno, string $caminhoFotoAbsoluto): string
    {
        $foto = self::lerImagemBase64($caminhoFotoAbsoluto);

        $response = self::client()->messages->create(
            model: self::MODEL,
            maxTokens: 300,
            system: self::SYSTEM_PRIMEIRA_FOTO,
            messages: [[
                'role' => 'user',
                'content' => [
                    TextBlockParam::with(text: 'Nome do aluno: '.self::primeiroNome($nomeAluno).'. Esta é a primeira foto registrada por ele.'),
                    ImageBlockParam::with(source: Base64ImageSource::with(data: $foto['data'], mediaType: $foto['mediaType'])),
                ],
            ]],
        );

        $bloco = $response->content[0] ?? null;

        return ($bloco && $bloco->type === 'text')
            ? trim($bloco->text)
            : 'Primeira foto registrada! Esse é o seu ponto de partida — daqui pra frente dá pra acompanhar sua evolução de verdade.';
    }

    /** Fotos seguintes: compara com a anterior e comenta a evolução. */
    public static function compararEvolucaoFisica(string $nomeAluno, string $caminhoFotoAnteriorAbsoluto, string $caminhoFotoNovaAbsoluto): string
    {
        $anterior = self::lerImagemBase64($caminhoFotoAnteriorAbsoluto);
        $nova = self::lerImagemBase64($caminhoFotoNovaAbsoluto);

        $response = self::client()->messages->create(
            model: self::MODEL,
            maxTokens: 300,
            system: self::SYSTEM_COMPARACAO,
            messages: [[
                'role' => 'user',
                'content' => [
                    TextBlockParam::with(text: 'Nome do aluno: '.self::primeiroNome($nomeAluno).'. Foto mais antiga (referência anterior):'),
                    ImageBlockParam::with(source: Base64ImageSource::with(data: $anterior['data'], mediaType: $anterior['mediaType'])),
                    TextBlockParam::with(text: 'Foto mais nova (registrada agora):'),
                    ImageBlockParam::with(source: Base64ImageSource::with(data: $nova['data'], mediaType: $nova['mediaType'])),
                ],
            ]],
        );

        $bloco = $response->content[0] ?? null;

        return ($bloco && $bloco->type === 'text') ? trim($bloco->text) : 'Foto registrada! Continue assim.';
    }
}
