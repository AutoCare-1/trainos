<?php

namespace App\Support;

use Anthropic\Client;
use App\Models\ConsultorIaMessage;
use Illuminate\Support\Collection;
use RuntimeException;

/** Espelha backend/src/services/consultorIa.ts do Node. */
class ConsultorIa
{
    private const MODEL = 'claude-haiku-4-5-20251001';

    private const MAX_RODADAS_TOOL_USE = 5;

    /**
     * Método (não const) porque duas ferramentas não têm parâmetros — precisam de um
     * stdClass vazio em "properties", não um array `[]`. A API da Anthropic rejeita
     * `properties: []` com "Input should be an object" (PHP serializa array vazio
     * associativo como `[]` no JSON, não `{}`), e stdClass não é permitido em
     * inicializador de const de array.
     */
    private static function tools(): array
    {
        return [
            [
                'name' => 'buscar_resumo_aluno',
                'description' => 'Busca o resumo de um aluno específico pelo nome (ou id): frequência de check-in da semana atual, recordes pessoais (PR) batidos nos últimos 14 dias, e o último comentário salvo na aba Evolução física dele.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'nome_ou_id' => ['type' => 'string', 'description' => 'Nome (ou parte do nome) do aluno, ou o id dele'],
                    ],
                    'required' => ['nome_ou_id'],
                ],
            ],
            [
                'name' => 'listar_alunos_sem_checkin',
                'description' => 'Lista os alunos que NÃO fizeram nenhum check-in de treino nos últimos N dias.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'dias' => ['type' => 'number', 'description' => 'Quantidade de dias pra trás a considerar (ex: 7)'],
                    ],
                    'required' => ['dias'],
                ],
            ],
            [
                'name' => 'listar_prs_recentes',
                'description' => 'Lista os recordes pessoais (PRs) de carga batidos pelos alunos nos últimos N dias, com nome do aluno, exercício e carga.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'dias' => ['type' => 'number', 'description' => 'Quantidade de dias pra trás a considerar (ex: 7)'],
                    ],
                    'required' => ['dias'],
                ],
            ],
            [
                'name' => 'listar_estagnados',
                'description' => 'Lista os alunos (e o exercício específico) que não aumentaram a carga entre as duas últimas sessões concluídas — sinal de estagnação.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass()],
            ],
            [
                'name' => 'listar_alunos_mais_consistentes',
                'description' => 'Retorna um ranking dos alunos por consistência de check-in (dias com check-in na semana e no mês atuais), do mais consistente pro menos.',
                'input_schema' => ['type' => 'object', 'properties' => new \stdClass()],
            ],
        ];
    }

    private const SYSTEM_PROMPT = <<<'PROMPT'
Você é o Consultor IA de um app de personal trainer — um assistente que responde perguntas do
PERSONAL (não do aluno) sobre a própria base de alunos, em linguagem natural.

Regras fundamentais:
- Você NUNCA acessa o banco de dados diretamente e NUNCA inventa informação. A única forma de
  saber algo sobre os alunos é chamando uma das ferramentas disponíveis.
- Se a pergunta puder ser respondida com uma ou mais ferramentas, chame-as antes de responder.
- Se a pergunta NÃO se encaixar em nenhuma ferramenta disponível (ex: pedir pra alterar um
  treino, dar conselho médico, ou qualquer dado que as ferramentas não cobrem), diga
  claramente que você não tem essa informação ou não consegue fazer isso — nunca invente
  uma resposta genérica pra parecer útil.
- Sempre que tiver o dado, cite números e nomes concretos (ex: "o João não faz check-in há
  5 dias" em vez de "alguns alunos estão inativos").
- Se buscar_resumo_aluno retornar encontrado: false, informe que não achou esse aluno — não
  tente adivinhar quem seria.
- Se uma ferramenta devolver uma lista vazia, um ranking vazio, ou qualquer resultado sem
  dados, sua resposta DEVE dizer isso literalmente (ex: "nenhum aluno nessa situação" ou
  "a lista está vazia"). NUNCA preencha esse vazio com nomes, números ou um ranking
  inventado — isso é uma violação grave da regra de nunca inventar informação, mesmo que
  pareça mais "útil" ou completo responder assim.
- Todo nome de aluno, número ou dado que aparecer na sua resposta tem que vir literalmente
  do conteúdo retornado por uma ferramenta chamada NESTA mensagem. Não reaproveite nomes ou
  números de uma resposta sua anterior nesta conversa sem chamar a ferramenta de novo.
- Tom direto e prático, como um colega analisando os dados junto com o personal. Respostas
  curtas quando possível, mas completas.
- Não usar emojis.

Responda sempre em português do Brasil.
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

    private static function executarFerramenta(string $professionalId, string $nome, array $input): array
    {
        return match ($nome) {
            'buscar_resumo_aluno' => ConsultorFerramentas::buscarResumoAluno($professionalId, (string) ($input['nome_ou_id'] ?? '')),
            'listar_alunos_sem_checkin' => ConsultorFerramentas::listarAlunosSemCheckin($professionalId, (int) ($input['dias'] ?? 7) ?: 7),
            'listar_prs_recentes' => ConsultorFerramentas::listarPrsRecentes($professionalId, (int) ($input['dias'] ?? 7) ?: 7),
            'listar_estagnados' => ConsultorFerramentas::listarEstagnados($professionalId),
            'listar_alunos_mais_consistentes' => ConsultorFerramentas::listarAlunosMaisConsistentes($professionalId),
            default => ['erro' => "Ferramenta desconhecida: {$nome}"],
        };
    }

    /** Converte os content blocks de uma resposta da API de volta pro formato aceito em messages[]. */
    private static function blocosParaParam(array $blocks): array
    {
        return array_map(function ($bloco) {
            return match ($bloco->type) {
                'text' => ['type' => 'text', 'text' => $bloco->text],
                'tool_use' => ['type' => 'tool_use', 'id' => $bloco->id, 'name' => $bloco->name, 'input' => $bloco->input],
                default => ['type' => $bloco->type],
            };
        }, $blocks);
    }

    /** @param  Collection<int, ConsultorIaMessage>  $historico */
    public static function responderConsultor(string $professionalId, Collection $historico): string
    {
        $messages = $historico->map(fn (ConsultorIaMessage $m) => [
            'role' => $m->role === 'personal' ? 'user' : 'assistant',
            'content' => $m->content,
        ])->values()->all();

        for ($rodada = 0; $rodada < self::MAX_RODADAS_TOOL_USE; $rodada++) {
            $response = self::client()->messages->create(
                model: self::MODEL,
                maxTokens: 1000,
                // temperature baixa de propósito: é um assistente factual sobre dados reais
                // de aluno, não uma tarefa criativa — reduz o risco de inventar dados quando
                // uma ferramenta devolve resultado vazio.
                temperature: 0.0,
                system: self::SYSTEM_PROMPT,
                tools: self::tools(),
                messages: $messages,
            );

            // Uma linha por rodada: o loop de tool use faz várias chamadas
            // cobradas por uma pergunta só, e o CRM precisa ver o custo cheio.
            IaUsage::registrar('consultor_ia', $response, $professionalId);

            if ($response->stopReason !== 'tool_use') {
                $textos = [];
                foreach ($response->content as $bloco) {
                    if ($bloco->type === 'text') {
                        $textos[] = $bloco->text;
                    }
                }
                $texto = trim(implode("\n", $textos));

                return $texto !== '' ? $texto : 'Não consegui gerar uma resposta agora.';
            }

            $messages[] = ['role' => 'assistant', 'content' => self::blocosParaParam($response->content)];

            $toolResults = [];
            foreach ($response->content as $bloco) {
                if ($bloco->type !== 'tool_use') {
                    continue;
                }
                $resultado = self::executarFerramenta($professionalId, $bloco->name, $bloco->input ?? []);
                $toolResults[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $bloco->id,
                    'content' => json_encode($resultado),
                ];
            }
            $messages[] = ['role' => 'user', 'content' => $toolResults];
        }

        return 'Não consegui concluir essa análise agora — tenta reformular a pergunta ou perguntar de novo em instantes.';
    }
}
