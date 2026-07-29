<?php

namespace Tests\Feature;

use App\Models\PosturalAssessment;
use App\Models\Professional;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Avaliação postural (3 fotos: frente/lado/costas) — opcional, separada da
 * Evolução física (1 foto). Segue o mesmo padrão de kill-switch/fallback
 * gracioso já usado nos outros 7 pipelines de IA.
 */
class PortalPosturalTest extends TestCase
{
    use RefreshDatabase;

    private function criarAluno(): Student
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);

        return Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);
    }

    private function fotoValida(string $nome): UploadedFile
    {
        // Bytes mínimos de um PNG real (magic bytes), pra passar da validação
        // mimetypes:image/* (que checa o conteúdo, não só a extensão).
        $caminhoTmp = tempnam(sys_get_temp_dir(), 'foto').'.png';
        file_put_contents($caminhoTmp, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));

        return new UploadedFile($caminhoTmp, "{$nome}.png", 'image/png', null, true);
    }

    public function test_primeira_avaliacao_postural_sem_api_key_devolve_fallback_gracioso(): void
    {
        config(['services.anthropic.api_key' => null]);
        $aluno = $this->criarAluno();

        $response = $this->postJson("/portal/{$aluno->invite_token}/postural", [
            'foto_frente' => $this->fotoValida('frente'),
            'foto_lado' => $this->fotoValida('lado'),
            'foto_costas' => $this->fotoValida('costas'),
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('assessment.ai_feedback', 'Primeira avaliação postural registrada! Esse é o seu ponto de partida.');
        $this->assertDatabaseCount('postural_assessments', 1);

        // Caminhos dos arquivos nunca voltam na resposta (armazenamento privado).
        $response->assertJsonMissingPath('assessment.front_photo_path');
    }

    public function test_segunda_avaliacao_referencia_a_anterior(): void
    {
        config(['services.anthropic.api_key' => null]);
        $aluno = $this->criarAluno();

        $this->postJson("/portal/{$aluno->invite_token}/postural", [
            'foto_frente' => $this->fotoValida('frente1'),
            'foto_lado' => $this->fotoValida('lado1'),
            'foto_costas' => $this->fotoValida('costas1'),
        ])->assertStatus(201);

        $primeira = PosturalAssessment::first();

        $response = $this->postJson("/portal/{$aluno->invite_token}/postural", [
            'foto_frente' => $this->fotoValida('frente2'),
            'foto_lado' => $this->fotoValida('lado2'),
            'foto_costas' => $this->fotoValida('costas2'),
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('assessment.ai_feedback', 'Avaliação registrada! Em breve o comentário da Coach IA aparece por aqui.');
        // Ordena por id (UUID ordenado por tempo) em vez de created_at — as duas
        // avaliações podem cair no mesmo segundo (useCurrent() tem precisão de 1s).
        $this->assertSame($primeira->id, PosturalAssessment::orderByDesc('id')->first()->compared_to_assessment_id);
    }

    public function test_faltando_uma_das_3_fotos_devolve_422(): void
    {
        $aluno = $this->criarAluno();

        $this->postJson("/portal/{$aluno->invite_token}/postural", [
            'foto_frente' => $this->fotoValida('frente'),
            'foto_lado' => $this->fotoValida('lado'),
        ])->assertStatus(422);

        $this->assertDatabaseCount('postural_assessments', 0);
    }

    public function test_kill_switch_desligado_devolve_503(): void
    {
        config(['ia_pipelines.avaliacao_postural' => false]);
        $aluno = $this->criarAluno();

        $this->postJson("/portal/{$aluno->invite_token}/postural", [
            'foto_frente' => $this->fotoValida('frente'),
            'foto_lado' => $this->fotoValida('lado'),
            'foto_costas' => $this->fotoValida('costas'),
        ])->assertStatus(503);

        $this->assertDatabaseCount('postural_assessments', 0);
    }

    public function test_token_invalido_devolve_404(): void
    {
        $this->postJson('/portal/token-que-nao-existe/postural', [
            'foto_frente' => $this->fotoValida('frente'),
            'foto_lado' => $this->fotoValida('lado'),
            'foto_costas' => $this->fotoValida('costas'),
        ])->assertStatus(404);
    }

    public function test_personal_ve_o_historico_do_aluno(): void
    {
        config(['services.anthropic.api_key' => null]);
        $aluno = $this->criarAluno();
        $professional = $aluno->professional;
        $token = auth('api')->login($professional);

        $this->postJson("/portal/{$aluno->invite_token}/postural", [
            'foto_frente' => $this->fotoValida('frente'),
            'foto_lado' => $this->fotoValida('lado'),
            'foto_costas' => $this->fotoValida('costas'),
        ])->assertStatus(201);

        $response = $this->getJson("/alunos/{$aluno->id}/postural", ['Authorization' => "Bearer {$token}"]);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'assessments');
    }
}
