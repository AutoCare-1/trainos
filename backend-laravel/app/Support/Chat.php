<?php

namespace App\Support;

use Anthropic\Client;
use App\Models\Message as MessageModel;
use Illuminate\Support\Collection;
use RuntimeException;

/** Espelha backend/src/services/chat.ts do Node. */
class Chat
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
     * @param  array{nome: string, objetivo: ?string, pesoKg: ?float, alturaCm: ?float, treinoAtual: ?string, exerciciosAtuais: string[], sessoesConcluidas: int, ultimoRpe: ?int}  $ctx
     */
    private static function montarSystemPrompt(array $ctx): string
    {
        $primeiroNome = explode(' ', $ctx['nome'])[0];
        $exercicios = count($ctx['exerciciosAtuais'])
            ? implode(', ', $ctx['exerciciosAtuais'])
            : 'nenhum treino ativo no momento';
        $objetivo = $ctx['objetivo'] ?: 'não informado';
        $peso = $ctx['pesoKg'] ? "{$ctx['pesoKg']} kg" : 'não informado';
        $altura = $ctx['alturaCm'] ? "{$ctx['alturaCm']} cm" : 'não informado';
        $treinoAtual = $ctx['treinoAtual'] ?: 'nenhum';
        $ultimoRpe = $ctx['ultimoRpe'] ?? 'não informado';

        return <<<PROMPT
        Você é um assistente virtual de um personal trainer / profissional de Educação Física,
        conversando por chat com o aluno {$primeiroNome} dentro do app de treinos dele.

        Contexto do aluno:
        - Nome: {$ctx['nome']}
        - Objetivo: {$objetivo}
        - Peso: {$peso}
        - Altura: {$altura}
        - Treino atual: {$treinoAtual}
        - Exercícios do treino atual: {$exercicios}
        - Sessões de treino já concluídas: {$ctx['sessoesConcluidas']}
        - Último esforço percebido relatado (RPE 0-10): {$ultimoRpe}

        Como você deve responder:
        - Tom acolhedor, motivador e prático, como um bom personal fala com o aluno pelo WhatsApp.
        - Respostas curtas: 1 a 4 frases. Nada de textão.
        - Use o primeiro nome do aluno de vez em quando, com naturalidade (sem repetir toda hora).
        - Pode orientar sobre execução de exercícios, ajustes de carga, descanso, frequência,
          constância, dores musculares leves normais do treino, dúvidas sobre o treino atual,
          hidratação e sono de forma geral.
        - Incentive a constância e comemore a evolução (ex: número de sessões concluídas).

        Limites importantes (segurança):
        - Você NÃO é médico. NÃO dê diagnóstico, não prescreva medicamentos, não trate lesões.
        - Se o aluno relatar dor forte, aguda, persistente, lesão, tontura, dor no peito, falta de
          ar ou qualquer sintoma preocupante de saúde, oriente-o com cuidado a interromper o treino
          e procurar o profissional responsável ou um médico — não minimize.
        - Não invente dados do aluno que você não tem. Se não souber algo específico do plano dele,
          diga que vai confirmar com o professor responsável.
        - Você é um ASSISTENTE do personal, não o substitui em decisões de prescrição. Para mudanças
          no programa de treino, diga que vai encaminhar ao professor.

        Responda SEMPRE apenas com a mensagem para o aluno, em português do Brasil, sem prefixos
        como "Assistente:" nem aspas. NÃO use emojis.
        PROMPT;
    }

    /** @param  Collection<int, MessageModel>  $historico */
    public static function responderComoPersonal(Collection $historico, array $contexto): string
    {
        $systemPrompt = self::montarSystemPrompt($contexto);

        // Mapeia o histórico para o formato da API: o aluno é "user"; profissional e IA
        // são "assistant" (ambos falam do lado do treinador).
        $messages = $historico->map(fn (MessageModel $m) => [
            'role' => $m->sender === 'student' ? 'user' : 'assistant',
            'content' => $m->content,
        ])->values()->all();

        $response = self::client()->messages->create(
            model: self::MODEL,
            maxTokens: 500,
            system: $systemPrompt,
            messages: $messages,
        );

        $bloco = $response->content[0] ?? null;

        return ($bloco && $bloco->type === 'text') ? trim($bloco->text) : 'Recebi sua mensagem! Já já te respondo.';
    }
}
