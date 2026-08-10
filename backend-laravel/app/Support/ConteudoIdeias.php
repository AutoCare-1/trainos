<?php

namespace App\Support;

use Anthropic\Client;
use App\Models\TrendCache;
use RuntimeException;

/** Espelha backend/src/services/conteudoIdeias.ts do Node. */
class ConteudoIdeias
{
    private const MODEL = 'claude-haiku-4-5-20251001';

    private const CACHE_VALIDADE_HORAS = 24;

    private const SYSTEM_GERAR_IDEIAS = <<<'PROMPT'
Você é um assistente de marketing de conteúdo pra Instagram de um personal trainer.

Sua tarefa é gerar ideias de conteúdo (post, story ou reels) que FUNDEM duas coisas:
1. Uma tendência de FORMATO em alta (tipo de edição, gancho, áudio, estilo de reels) — é a
   "embalagem" da ideia.
2. Um dado real e agregado da base de alunos desse personal — é o "conteúdo" que preenche
   essa embalagem.

Regras importantes:
- NUNCA gere duas listas separadas. Cada ideia já deve vir pronta mostrando a fusão: como
  aplicar aquele formato em alta usando aquele dado real da base de alunos.
- NUNCA cite nome, foto, ou qualquer detalhe que identifique um aluno específico — os dados
  que você recebe já são agregados e anônimos, use-os só como número/padrão (ex: "3 alunos
  bateram recorde essa semana", sem inventar quem).
- Se o personal der um direcionamento (assunto específico), priorize esse tema nas ideias,
  mas ainda assim funda com o formato em alta e o dado agregado.
- Gere entre 3 e 5 ideias, variando os formatos (post, story, reels) quando fizer sentido.
- Tom: direto, prático, como alguém que entende de marketing fitness — nada de textão.
- NÃO usar emojis em nenhum campo, nem na legenda sugerida — só texto.

Responda SOMENTE com um array JSON válido, sem markdown, sem texto antes ou depois, no formato:
[{"format": "reels", "title": "...", "description": "...", "caption_suggestion": "..."}]

- "format": um de "post", "story", "reels".
- "title": título curto da ideia (até 8 palavras).
- "description": como executar (2 a 4 frases, prático, já citando a fusão formato+dado).
- "caption_suggestion": uma legenda pronta pra usar, curta, sem hashtags genéricas demais.
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

    private static function extrairTexto(array $blocks): string
    {
        $textos = [];
        foreach ($blocks as $bloco) {
            if ($bloco->type === 'text') {
                $textos[] = $bloco->text;
            }
        }

        return trim(implode("\n", $textos));
    }

    /**
     * Quantas buscas a tool web_search realmente executou nesta resposta.
     * A API expõe isso em usage->serverToolUse->webSearchRequests; o fallback
     * conta os blocos server_tool_use pra não zerar o custo caso o campo não
     * venha preenchido.
     */
    private static function contarBuscasWeb(object $response): int
    {
        $doUsage = $response->usage->serverToolUse->webSearchRequests ?? null;
        if (is_int($doUsage)) {
            return $doUsage;
        }

        $buscas = 0;
        foreach ($response->content ?? [] as $bloco) {
            if (($bloco->type ?? null) === 'server_tool_use' && ($bloco->name ?? null) === 'web_search') {
                $buscas++;
            }
        }

        return $buscas;
    }

    /**
     * Chamada CARA (usa busca na web) — só roda quando o cache expira. Foca só em
     * FORMATO/tendência, nunca em dado de aluno (isso entra na chamada barata depois).
     */
    private static function buscarTendenciasNaWeb(): string
    {
        $response = self::client()->messages->create(
            model: self::MODEL,
            maxTokens: 700,
            tools: [['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 3]],
            messages: [[
                'role' => 'user',
                'content' => <<<'TEXT'
                Pesquise na web quais são as tendências ATUAIS de formato de conteúdo pra Instagram
                no nicho de fitness/personal trainer/academia: tipos de reels em alta, estilo de gancho
                (hook) dos primeiros segundos, áudios/sons do momento, formatos de carrossel ou
                antes-depois que estão performando bem.

                Responda com um resumo curto e prático (bullet points), focado só em FORMATO — como o
                conteúdo é feito e editado — não invente números de engajamento nem cite marcas
                específicas. Não fale sobre um aluno ou personal específico, é sobre tendência de
                formato em geral.
                TEXT,
            ]],
        );

        // web_search é cobrada por busca executada, não por token — conta os
        // blocos de uso da tool na resposta em vez de assumir o max_uses (o
        // modelo pode usar menos que o teto, e aí cobrar 3 inflaria o custo).
        IaUsage::registrar('ideias_conteudo', $response, webSearches: self::contarBuscasWeb($response));

        $texto = self::extrairTexto($response->content);

        return $texto !== ''
            ? $texto
            : 'Sem tendências específicas encontradas — use formatos fitness clássicos (bastidor de treino, antes/depois, dica rápida em reels curto).';
    }

    /**
     * Tendência de formato cacheada globalmente (reaproveitada entre todos os
     * personals por até 24h) — só refaz a busca na web quando o cache expira.
     */
    public static function obterTendenciasFormato(): string
    {
        $cache = TrendCache::orderByDesc('cached_at')->first();
        $cacheValido = $cache && $cache->cached_at->diffInHours(now()) < self::CACHE_VALIDADE_HORAS;

        if ($cacheValido) {
            return $cache->content_snapshot;
        }

        $conteudo = self::buscarTendenciasNaWeb();
        TrendCache::create(['content_snapshot' => $conteudo]);

        return $conteudo;
    }

    /**
     * Chamada BARATA (sem tools) — roda toda vez, sempre com o dado do aluno fresco.
     *
     * @return array<int, array{format: string, title: string, description: string, caption_suggestion: string}>
     */
    public static function gerarIdeiasConteudo(string $resumoAgregado, ?string $direcionamento): array
    {
        $tendencias = self::obterTendenciasFormato();

        $direcionamentoTexto = $direcionamento
            ? "Direcionamento do personal: {$direcionamento}"
            : 'Sem direcionamento específico — explore livremente os dados acima.';

        $mensagemUsuario = "Tendência de formato em alta (pesquisada na web):\n{$tendencias}\n\n{$resumoAgregado}\n\n{$direcionamentoTexto}";

        $response = self::client()->messages->create(
            model: self::MODEL,
            maxTokens: 1200,
            system: self::SYSTEM_GERAR_IDEIAS,
            messages: [['role' => 'user', 'content' => $mensagemUsuario]],
        );

        IaUsage::registrar('ideias_conteudo', $response);

        $texto = self::extrairTexto($response->content);
        $jsonLimpo = trim(preg_replace(['/^```json\s*/i', '/```$/'], '', $texto));

        $ideias = json_decode($jsonLimpo, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('A IA não retornou um JSON válido de ideias de conteúdo');
        }
        if (! is_array($ideias) || array_is_list($ideias) === false) {
            throw new RuntimeException('Resposta da IA não é uma lista de ideias');
        }

        return $ideias;
    }
}
