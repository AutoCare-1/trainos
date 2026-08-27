<?php

namespace Tests\Unit;

use App\Models\Message;
use App\Support\Chat;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Chat::montarMensagens mapeia sender -> role da API da Anthropic. professional
 * e ai viram "assistant" — se dois desses aparecerem em sequência (ex: personal
 * responde manualmente logo após a IA), a API rejeita por quebrar a alternância
 * estrita user/assistant. Mensagens consecutivas do mesmo role são mescladas.
 */
class ChatTest extends TestCase
{
    public function test_mescla_mensagens_consecutivas_do_mesmo_role(): void
    {
        $historico = new Collection([
            self::mensagem('student', 'oi, tudo bem?'),
            self::mensagem('ai', 'Oi! Tudo ótimo, e você?'),
            self::mensagem('professional', 'Vi seu treino de ontem, mandou bem!'),
            self::mensagem('student', 'valeu!'),
        ]);

        $mensagens = Chat::montarMensagens($historico);

        $this->assertCount(3, $mensagens);
        $this->assertSame('user', $mensagens[0]['role']);
        $this->assertSame('assistant', $mensagens[1]['role']);
        $this->assertSame(
            "Oi! Tudo ótimo, e você?\n\nVi seu treino de ontem, mandou bem!",
            $mensagens[1]['content']
        );
        $this->assertSame('user', $mensagens[2]['role']);
    }

    public function test_sem_mensagens_consecutivas_do_mesmo_role_nao_mescla_nada(): void
    {
        $historico = new Collection([
            self::mensagem('student', 'oi'),
            self::mensagem('ai', 'oi, tudo bem?'),
            self::mensagem('student', 'tudo!'),
        ]);

        $mensagens = Chat::montarMensagens($historico);

        $this->assertCount(3, $mensagens);
        $this->assertSame(['user', 'assistant', 'user'], array_column($mensagens, 'role'));
    }

    public function test_descarta_falas_do_treinador_antes_da_primeira_do_aluno(): void
    {
        // O personal puxa conversa antes do aluno responder: sem descartar, o
        // array começaria em "assistant" e a API devolveria 400.
        $historico = new Collection([
            self::mensagem('professional', 'Oi! Montei seu treino novo, dá uma olhada.'),
            self::mensagem('ai', 'Qualquer dúvida é só chamar.'),
            self::mensagem('student', 'vi sim, valeu!'),
        ]);

        $mensagens = Chat::montarMensagens($historico);

        $this->assertSame(['user'], array_column($mensagens, 'role'));
        $this->assertSame('vi sim, valeu!', $mensagens[0]['content']);
    }

    public function test_janela_que_corta_no_meio_da_resposta_da_ia_ainda_comeca_em_user(): void
    {
        // Conversa longa cortada pelo limit(30) do controller: o corte cai numa
        // mensagem da IA. Acontece justamente com o aluno mais engajado.
        $historico = new Collection([
            self::mensagem('ai', 'resposta cortada pela janela'),
            self::mensagem('student', 'e sobre a carga?'),
            self::mensagem('ai', 'pode subir 2kg'),
            self::mensagem('student', 'fechou'),
        ]);

        $mensagens = Chat::montarMensagens($historico);

        $this->assertSame(['user', 'assistant', 'user'], array_column($mensagens, 'role'));
    }

    public function test_historico_so_com_falas_do_treinador_nao_sobra_mensagem(): void
    {
        $historico = new Collection([
            self::mensagem('professional', 'Bom treino hoje!'),
            self::mensagem('ai', 'Estou por aqui se precisar.'),
        ]);

        $this->assertSame([], Chat::montarMensagens($historico));
    }

    private static function mensagem(string $sender, string $content): Message
    {
        $m = new Message;
        $m->sender = $sender;
        $m->content = $content;

        return $m;
    }
}
