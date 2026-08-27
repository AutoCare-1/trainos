<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Professional;
use App\Models\Student;
use App\Notifications\Rules\MensagemNaoLidaRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Lido" é o que decide se a MensagemNaoLidaRule notifica o aluno. Enquanto o
 * GET do histórico marcava read_at, o polling do portal (que roda a cada poucos
 * segundos, em qualquer aba) marcava como lida toda mensagem que chegava — a
 * regra nunca achava candidato e a notificação era código morto na prática.
 *
 * Quem marca agora é POST /mensagens/lidas, chamado pelo portal só com a aba de
 * chat aberta.
 */
class PortalMensagemLidaTest extends TestCase
{
    use RefreshDatabase;

    private function criarAlunoComMensagemDoCoach(): Student
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $jwt = auth('api')->login($professional);
        $studentId = $this->postJson('/alunos', ['name' => 'Aluno Teste'], ['Authorization' => "Bearer {$jwt}"])
            ->assertCreated()
            ->json('student.id');

        $mensagem = Message::create([
            'student_id' => $studentId,
            'professional_id' => $professional->id,
            'sender' => 'professional',
            'content' => 'Vi seu treino de ontem, mandou bem!',
        ]);
        // Antiga o bastante pra entrar na janela da regra de não lida.
        $mensagem->created_at = now()->subHours(48);
        $mensagem->save();

        return Student::find($studentId);
    }

    public function test_ler_o_historico_nao_marca_as_mensagens_como_lidas(): void
    {
        $student = $this->criarAlunoComMensagemDoCoach();

        $this->getJson("/portal/{$student->invite_token}/mensagens")->assertOk();

        $this->assertDatabaseHas('messages', ['student_id' => $student->id, 'read_at' => null]);
    }

    public function test_notificacao_de_mensagem_nao_lida_sobrevive_ao_polling_do_portal(): void
    {
        $student = $this->criarAlunoComMensagemDoCoach();

        // Simula o portal aberto na aba de treino: o polling bate no histórico
        // várias vezes sem o aluno nunca ter olhado o chat.
        $this->getJson("/portal/{$student->invite_token}/mensagens")->assertOk();
        $this->getJson("/portal/{$student->invite_token}/mensagens")->assertOk();

        $this->assertCount(1, (new MensagemNaoLidaRule)->avaliar());
    }

    public function test_abrir_a_aba_de_chat_marca_como_lida_e_encerra_a_notificacao(): void
    {
        $student = $this->criarAlunoComMensagemDoCoach();

        $this->postJson("/portal/{$student->invite_token}/mensagens/lidas")
            ->assertOk()
            ->assertJsonPath('marcadas', 1);

        $this->assertDatabaseMissing('messages', ['student_id' => $student->id, 'read_at' => null]);
        $this->assertSame([], (new MensagemNaoLidaRule)->avaliar());
    }

    public function test_marcar_lidas_com_token_invalido_devolve_404(): void
    {
        $this->postJson('/portal/token-que-nao-existe/mensagens/lidas')->assertNotFound();
    }
}
