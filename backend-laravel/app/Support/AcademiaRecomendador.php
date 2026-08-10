<?php

namespace App\Support;

use Anthropic\Client;
use RuntimeException;

/** Espelha backend/src/services/academiaRecomendador.ts do Node. */
class AcademiaRecomendador
{
    private const MODEL = 'claude-haiku-4-5-20251001';

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
     * @param  array{machines: array}  $machines
     * @param  array<int, array{id: string, name: string, muscle_group: string, equipment: ?string}>  $exercicios
     * @param  array{nome: string, objective: ?string, healthNotes: ?string, limitacoesParQ: string[], daysPerWeek: int}  $contexto
     */
    private static function buildSystemPrompt(array $machines, array $exercicios, array $contexto): string
    {
        $listaExercicios = implode("\n", array_map(
            fn ($e) => "- id: {$e['id']} | {$e['name']} ({$e['muscle_group']}".
                ($e['equipment'] ? ", equipamento: {$e['equipment']}" : '').')',
            $exercicios
        ));

        $listaMaquinas = implode("\n", array_map(
            fn ($m) => "- {$m['name']} ({$m['category']})",
            $machines['machines']
        )) ?: 'nenhuma detectada';

        $objetivo = $contexto['objective'] ?? 'não informado';
        $healthNotes = $contexto['healthNotes'] ?? 'nenhuma';
        $limitacoes = $contexto['limitacoesParQ'] ? implode(', ', $contexto['limitacoesParQ']) : 'nenhuma sinalizada';

        return <<<PROMPT
Você é um personal trainer experiente, prescrevendo treino pra um aluno do Clube Mais
(público majoritariamente idoso — priorize sempre segurança, execução controlada e RPE moderado).

## MÁQUINAS/EQUIPAMENTOS DETECTADOS NA ACADEMIA DO ALUNO
{$listaMaquinas}

## PERFIL DO ALUNO
- Nome: {$contexto['nome']}
- Objetivo: {$objetivo}
- Observações de saúde: {$healthNotes}
- Limitações de segurança (PAR-Q): {$limitacoes}
- Frequência: {$contexto['daysPerWeek']} dias/semana

## BIBLIOTECA DE EXERCÍCIOS DISPONÍVEL (use SOMENTE exercícios desta lista, referenciando o "id" exato)
{$listaExercicios}

## TAREFA
Este app mantém APENAS UM treino ativo por aluno (uma lista única de exercícios, sem divisão
por dia da semana) — o aluno repete essa mesma lista a cada sessão. Monte UM treino completo
(full-body, cobrindo os principais grupos musculares de forma equilibrada) com 6 a 10 exercícios,
usando SOMENTE exercícios da biblioteca acima cujo equipamento seja compatível com as máquinas
detectadas (ou que não exijam equipamento, tipo exercícios de peso corporal). Considere que o
aluno treina {$contexto['daysPerWeek']}x/semana ao dosar volume total. Se limitações de saúde foram
sinalizadas, evite exercícios que as agravem. A ordem dos itens no array "items" é a ordem de
execução do treino. Nos campos de texto ("reasoning", "notes"), NÃO use emojis.

Responda APENAS com um JSON válido, sem texto antes ou depois, neste formato exato:
{
  "name": "Treino Full Body — Clube Mais",
  "split_type": "full_body",
  "reasoning": "justificativa curta da escolha dos exercícios e do volume",
  "items": [
    {
      "exercise_id": "uuid-exato-da-lista",
      "sets": 3,
      "reps": "10-12",
      "rest_seconds": 60,
      "notes": "execução controlada, RPE 6-7"
    }
  ]
}
PROMPT;
    }

    /**
     * Gera recomendação de treino a partir das máquinas detectadas, usando só exercícios da
     * biblioteca real (evita texto livre não-acionável).
     *
     * @param  array{machines: array}  $machines
     * @param  array<int, array{id: string, name: string, muscle_group: string, equipment: ?string}>  $exercicios
     * @param  array{nome: string, objective: ?string, healthNotes: ?string, limitacoesParQ: string[], daysPerWeek: int}  $contexto
     * @return array{name: string, split_type: string, reasoning: string, items: array}
     */
    public static function recomendarTreino(array $machines, array $exercicios, array $contexto): array
    {
        $response = self::client()->messages->create(
            model: self::MODEL,
            maxTokens: 2500,
            system: self::buildSystemPrompt($machines, $exercicios, $contexto),
            messages: [['role' => 'user', 'content' => 'Gere a recomendação conforme as instruções.']],
        );

        IaUsage::registrar('academia_recomendacao', $response);

        $bloco = $response->content[0] ?? null;
        $texto = ($bloco && $bloco->type === 'text') ? $bloco->text : '';
        if (! preg_match('/\{[\s\S]*\}/', $texto, $match)) {
            throw new RuntimeException('Resposta da IA não trouxe JSON válido');
        }

        $parsed = json_decode($match[0], true);

        $porId = [];
        foreach ($exercicios as $e) {
            $porId[$e['id']] = $e;
        }

        $items = [];
        foreach ($parsed['items'] ?? [] as $item) {
            if (! isset($porId[$item['exercise_id']])) {
                continue;
            }
            $items[] = [
                'exercise_id' => $item['exercise_id'],
                'exercise_name' => $porId[$item['exercise_id']]['name'],
                'sets' => $item['sets'],
                'reps' => $item['reps'],
                'rest_seconds' => $item['rest_seconds'] ?? null,
                'notes' => $item['notes'] ?? '',
            ];
        }

        if (count($items) === 0) {
            throw new RuntimeException('IA não recomendou nenhum exercício válido da biblioteca');
        }

        return [
            'name' => $parsed['name'],
            'split_type' => $parsed['split_type'],
            'reasoning' => $parsed['reasoning'],
            'items' => $items,
        ];
    }
}
