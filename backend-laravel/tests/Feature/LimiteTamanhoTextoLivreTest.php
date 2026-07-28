<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * content (mensagens de chat) e direcionamento (ideias de conteúdo) eram lidos
 * direto de $request->input() sem nenhuma validação de tamanho — um payload de
 * vários MB virava uma linha na tabela (text, sem limite no MySQL) e, no caso
 * do chat com autopilot, ia inteiro pro prompt da Anthropic.
 */
class LimiteTamanhoTextoLivreTest extends TestCase
{
    use RefreshDatabase;

    private function autenticar(): array
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $token = auth('api')->login($professional);

        return [$professional, ['Authorization' => "Bearer {$token}"]];
    }

    public function test_mensagem_do_personal_para_o_aluno_com_texto_gigante_devolve_422(): void
    {
        [$professional, $headers] = $this->autenticar();
        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);

        $this->postJson("/alunos/{$student->id}/mensagens", [
            'content' => str_repeat('a', 4001),
        ], $headers)->assertStatus(422);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_mensagem_do_aluno_no_portal_com_texto_gigante_devolve_422(): void
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);

        $this->postJson("/portal/{$student->invite_token}/mensagens", [
            'content' => str_repeat('a', 4001),
        ])->assertStatus(422);

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_gerar_ideias_com_direcionamento_gigante_devolve_422(): void
    {
        [, $headers] = $this->autenticar();
        // A validação de tamanho precisa rodar antes de qualquer chamada à IA —
        // zera a api_key pra garantir que, se a validação for removida, o teste
        // falhe rápido (503, via RuntimeException síncrona) em vez de acertar a
        // API real da Anthropic.
        config(['services.anthropic.api_key' => null]);

        $this->postJson('/conteudo', [
            'direcionamento' => str_repeat('a', 2001),
        ], $headers)->assertStatus(422);

        $this->assertDatabaseCount('content_ideas', 0);
    }
}
