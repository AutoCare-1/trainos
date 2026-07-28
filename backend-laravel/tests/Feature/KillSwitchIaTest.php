<?php

namespace Tests\Feature;

use App\Models\ConsultorIaMessage;
use App\Models\ContentIdea;
use App\Models\FormCorrectionVideo;
use App\Models\GymMediaSubmission;
use App\Models\Message;
use App\Models\Professional;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Kill-switch por pipeline de IA (config/ia_pipelines.php + App\Support\KillSwitchIa)
 * e o novo try/catch do Consultor IA / Ideias de Conteúdo, que antes não existia.
 */
class KillSwitchIaTest extends TestCase
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

    private function criarAluno(?string $professionalId = null): Student
    {
        return Student::create([
            'professional_id' => $professionalId ?? Professional::create([
                'name' => 'Dono do aluno',
                'email' => uniqid('dono').'@example.com',
                'password_hash' => bcrypt('senha12345'),
            ])->id,
            'name' => 'Aluno Teste',
            'invite_token' => uniqid('token'),
        ]);
    }

    public function test_analisar_forma_desligado_retorna_503_sem_criar_nada(): void
    {
        config(['ia_pipelines.analisar_forma' => false]);
        $student = $this->criarAluno();

        $this->postJson("/portal/{$student->invite_token}/forma", [])
            ->assertStatus(503)
            ->assertJsonPath('error', 'Essa funcionalidade está temporariamente indisponível, tente novamente mais tarde.');

        $this->assertSame(0, FormCorrectionVideo::count());
    }

    public function test_academia_analise_desligado_retorna_503_sem_criar_submissao(): void
    {
        config(['ia_pipelines.academia_analise' => false]);
        $student = $this->criarAluno();

        $this->postJson("/portal/{$student->invite_token}/academia", [])
            ->assertStatus(503);

        $this->assertSame(0, GymMediaSubmission::count());
    }

    public function test_consultor_ia_desligado_retorna_503_sem_salvar_mensagem(): void
    {
        config(['ia_pipelines.consultor_ia' => false]);
        [, $headers] = $this->autenticar();

        $this->postJson('/consultor-ia/chat', ['content' => 'quem não treina há mais tempo?'], $headers)
            ->assertStatus(503);

        $this->assertSame(0, ConsultorIaMessage::count());
    }

    public function test_ideias_conteudo_desligado_retorna_503_sem_gerar_nada(): void
    {
        config(['ia_pipelines.ideias_conteudo' => false]);
        [, $headers] = $this->autenticar();

        $this->postJson('/conteudo', [], $headers)->assertStatus(503);

        $this->assertSame(0, ContentIdea::count());
    }

    public function test_evolucao_fisica_desligado_ainda_registra_a_foto(): void
    {
        config(['ia_pipelines.evolucao_fisica' => false]);
        $student = $this->criarAluno();

        $resposta = $this->post("/portal/{$student->invite_token}/body-photos", [
            'foto' => UploadedFile::fake()->image('foto.jpg'),
        ])->assertCreated();

        $resposta->assertJsonPath(
            'photo.ai_feedback',
            'Primeira foto registrada! Esse é o seu ponto de partida — daqui pra frente dá pra acompanhar sua evolução de verdade.'
        );
    }

    public function test_chat_autopilot_desligado_salva_mensagem_do_aluno_sem_resposta_ia(): void
    {
        config(['ia_pipelines.chat_autopilot' => false]);
        $student = $this->criarAluno();
        $this->assertTrue($student->fresh()->ai_autopilot, 'autopilot deveria estar ligado por padrão pra este teste fazer sentido');

        $this->postJson("/portal/{$student->invite_token}/mensagens", ['content' => 'Oi, tudo bem?'])
            ->assertCreated()
            ->assertJsonPath('aiReply', null);

        $this->assertSame(1, Message::where('student_id', $student->id)->count());
        $this->assertSame(0, Message::where('student_id', $student->id)->where('sender', 'ai')->count());
    }

    public function test_consultor_ia_com_chave_ausente_retorna_resposta_graciosa_em_vez_de_500(): void
    {
        config(['services.anthropic.api_key' => '']);
        [, $headers] = $this->autenticar();

        $this->postJson('/consultor-ia/chat', ['content' => 'oi'], $headers)
            ->assertStatus(502)
            ->assertJsonStructure(['error']);

        // A pergunta do personal fica salva mesmo com a IA fora do ar.
        $this->assertSame(1, ConsultorIaMessage::where('role', 'personal')->count());
        $this->assertSame(0, ConsultorIaMessage::where('role', 'ai')->count());
    }

    public function test_ideias_conteudo_com_chave_ausente_retorna_resposta_graciosa_em_vez_de_500(): void
    {
        config(['services.anthropic.api_key' => '']);
        [, $headers] = $this->autenticar();

        $this->postJson('/conteudo', [], $headers)
            ->assertStatus(502)
            ->assertJsonStructure(['error']);

        $this->assertSame(0, ContentIdea::count());
    }
}
