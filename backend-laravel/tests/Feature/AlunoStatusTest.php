<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Models\Student;
use App\Support\Assinatura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Desvincular/reativar aluno (PATCH /alunos/:id/status) — o personal usa isso
 * pra parar de treinar um aluno sem apagar o histórico. Aluno desvinculado
 * perde acesso ao portal e para de contar no limite de alunos do plano.
 */
class AlunoStatusTest extends TestCase
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

    public function test_desvincula_aluno(): void
    {
        [$professional, $headers] = $this->autenticar();
        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);

        $this->patchJson("/alunos/{$student->id}/status", ['ativo' => false], $headers)
            ->assertOk()
            ->assertJsonPath('student.status', 'inactive');
    }

    public function test_reativa_aluno(): void
    {
        [$professional, $headers] = $this->autenticar();
        $student = Student::create([
            'professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid(), 'status' => 'inactive',
        ]);

        $this->patchJson("/alunos/{$student->id}/status", ['ativo' => true], $headers)
            ->assertOk()
            ->assertJsonPath('student.status', 'active');
    }

    public function test_aluno_desvinculado_perde_acesso_ao_portal(): void
    {
        [$professional, $headers] = $this->autenticar();
        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);

        $this->patchJson("/alunos/{$student->id}/status", ['ativo' => false], $headers)->assertOk();

        $this->getJson("/portal/{$student->invite_token}")->assertNotFound();
    }

    public function test_aluno_desvinculado_nao_conta_no_limite_do_plano(): void
    {
        [$professional] = $this->autenticar();
        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);

        $this->assertSame(1, Assinatura::alunosAtivos($professional));

        $student->update(['status' => 'inactive']);

        $this->assertSame(0, Assinatura::alunosAtivos($professional->fresh()));
    }

    public function test_nao_deixa_mexer_em_aluno_de_outro_personal(): void
    {
        [, $headers] = $this->autenticar();
        $outroProfissional = Professional::create([
            'name' => 'Outro Personal',
            'email' => uniqid('outro').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $student = Student::create(['professional_id' => $outroProfissional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);

        $this->patchJson("/alunos/{$student->id}/status", ['ativo' => false], $headers)->assertNotFound();
    }
}
