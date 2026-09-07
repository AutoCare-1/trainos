<?php

namespace App\Support;

use Anthropic\Client;
use App\Models\TrendCache;
use RuntimeException;

class ConteudoIdeias
{
    private const MODEL = 'claude-haiku-4-5-20251001';

    private const CACHE_VALIDADE_HORAS = 24;

    // Objetivos aceitos (o mesmo enum que o ContentController valida e a tela
    // manda pelos dois botões).
    public const OBJETIVO_ENGAJAR = 'engajar';

    public const OBJETIVO_ATRAIR_ALUNOS = 'atrair_alunos';

    // Trecho comum aos dois prompts: formato da resposta e regras que não mudam
    // com o objetivo (anonimato, sem emoji, JSON puro).
    private const REGRAS_COMUNS = <<<'PROMPT'
Regras que valem sempre:
- NUNCA gere duas listas separadas. Cada ideia já vem pronta, mostrando a fusão do formato
  em alta com o dado real da base de alunos.
- NUNCA cite nome, foto ou qualquer detalhe que identifique um aluno específico — os dados
  são agregados e anônimos, use só como número/padrão (ex: "3 alunos bateram recorde essa
  semana", sem inventar quem).
- Se o personal der um direcionamento (assunto específico), priorize esse tema, mas ainda
  fundindo com o formato em alta e o dado agregado.
- Gere entre 3 e 5 ideias, variando os formatos (post, story, reels) quando fizer sentido.
- Tom: direto e prático, como quem entende de marketing fitness — nada de textão.
- NÃO usar emojis em nenhum campo, nem na legenda — só texto.

Responda SOMENTE com um array JSON válido, sem markdown, sem texto antes ou depois:
[{"format": "reels", "title": "...", "description": "...", "caption_suggestion": "..."}]

- "format": um de "post", "story", "reels".
- "title": título curto da ideia (até 8 palavras).
- "description": como executar (2 a 4 frases, prático, já citando a fusão formato+dado).
- "caption_suggestion": uma legenda pronta pra usar, curta, sem hashtags genéricas demais.
PROMPT;

    // Modo "engajar" — o comportamento que a ferramenta sempre teve: conteúdo
    // pra quem JÁ segue o personal (aluno atual, seguidor), mantendo ele na
    // cabeça de quem já está por perto.
    private const SYSTEM_ENGAJAR = <<<'PROMPT'
Você é um assistente de marketing de conteúdo pra Instagram de um personal trainer.

Sua tarefa é gerar ideias de conteúdo (post, story ou reels) que FUNDEM duas coisas:
1. Uma tendência de FORMATO em alta (tipo de edição, gancho, áudio, estilo de reels) — a
   "embalagem" da ideia.
2. Um dado real e agregado da base de alunos desse personal — o "conteúdo" que preenche
   essa embalagem.

O público-alvo é quem JÁ acompanha o personal: alunos atuais e seguidores. O objetivo é
manter esse público engajado e lembrando do trabalho dele — bastidor de treino, evolução
da turma, dica rápida, prova de que o método funciona.
PROMPT;

    // Modo "atrair_alunos" — conteúdo de CAPTAÇÃO: fala com quem ainda NÃO é
    // aluno e move essa pessoa pra um primeiro contato.
    private const SYSTEM_ATRAIR_ALUNOS = <<<'PROMPT'
Você é um assistente de marketing de captação pra Instagram de um personal trainer.

Sua tarefa é gerar ideias de conteúdo (post, story ou reels) pensadas pra ATRAIR ALUNO NOVO
— falar com quem ainda NÃO treina com esse personal (seguidor frio, indicação, quem caiu no
perfil) e levar essa pessoa a dar o primeiro passo.

Cada ideia FUNDE duas coisas:
1. Uma tendência de FORMATO em alta (tipo de edição, gancho, áudio, estilo de reels) — a
   "embalagem".
2. O dado real e agregado da base de alunos — usado como PROVA SOCIAL ("meus alunos em
   média...", "X pessoas bateram a meta esse mês"), não como o assunto em si.

Cada ideia deve mirar uma dor ou objeção comum de quem pensa em contratar personal e não
contrata: falta de tempo, falta de constância sozinho, "acho caro", "será que funciona pra
mim", vergonha de começar, já tentou por conta e desistiu.

A "caption_suggestion" SEMPRE termina com uma chamada pra ação clara e de baixo atrito:
chamar no direct, comentar uma palavra, agendar uma aula experimental, link na bio. Sem
pressão agressiva — um convite.
PROMPT;

    /**
     * O system prompt do objetivo pedido. Público (função pura) pra dar pra
     * testar que os dois modos geram instruções diferentes sem chamar a IA.
     */
    public static function sistemaPara(string $objetivo): string
    {
        $base = $objetivo === self::OBJETIVO_ATRAIR_ALUNOS
            ? self::SYSTEM_ATRAIR_ALUNOS
            : self::SYSTEM_ENGAJAR;

        return $base."\n\n".self::REGRAS_COMUNS;
    }

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
    public static function gerarIdeiasConteudo(string $resumoAgregado, ?string $direcionamento, string $objetivo = self::OBJETIVO_ENGAJAR): array
    {
        $tendencias = self::obterTendenciasFormato();

        $direcionamentoTexto = $direcionamento
            ? "Direcionamento do personal: {$direcionamento}"
            : 'Sem direcionamento específico — explore livremente os dados acima.';

        $mensagemUsuario = "Tendência de formato em alta (pesquisada na web):\n{$tendencias}\n\n{$resumoAgregado}\n\n{$direcionamentoTexto}";

        $response = self::client()->messages->create(
            model: self::MODEL,
            maxTokens: 1200,
            system: self::sistemaPara($objetivo),
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
