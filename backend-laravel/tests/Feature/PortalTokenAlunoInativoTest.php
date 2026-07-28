<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Não existe endpoint hoje pra desativar um aluno, mas a coluna students.status
 * já existe (default 'active') pra isso — nenhuma rota do portal reconsultava
 * esse campo, então um token de aluno marcado como inativo continuaria dando
 * acesso completo (treino, chat, fotos) assim que essa feature for ligada.
 */
class PortalTokenAlunoInativoTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_de_aluno_inativo_nao_acessa_o_portal(): void
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $token = auth('api')->login($professional);
        $studentId = $this->postJson('/alunos', ['name' => 'Aluno Teste'], ['Authorization' => "Bearer {$token}"])
            ->assertCreated()
            ->json('student.id');

        $student = Student::find($studentId);
        $student->update(['status' => 'inactive']);

        $this->getJson("/portal/{$student->invite_token}")->assertNotFound();
        $this->getJson("/portal/{$student->invite_token}/mensagens")->assertNotFound();
    }

    public function test_token_de_aluno_ativo_continua_acessando_normalmente(): void
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $token = auth('api')->login($professional);
        $studentId = $this->postJson('/alunos', ['name' => 'Aluno Teste'], ['Authorization' => "Bearer {$token}"])
            ->assertCreated()
            ->json('student.id');

        $student = Student::find($studentId);

        $this->getJson("/portal/{$student->invite_token}")->assertOk();
    }
}
