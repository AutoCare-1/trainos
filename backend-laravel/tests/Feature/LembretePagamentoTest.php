<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LembretePagamentoTest extends TestCase
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

    public function test_liga_e_desliga_o_lembrete(): void
    {
        [$professional, $headers] = $this->autenticar();
        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()])->refresh();
        $this->assertFalse($student->lembrar_pagamento_vencimento);

        $this->patchJson("/alunos/{$student->id}/lembrete-pagamento", ['enabled' => true], $headers)
            ->assertOk()
            ->assertJsonPath('student.lembrar_pagamento_vencimento', true);

        $this->patchJson("/alunos/{$student->id}/lembrete-pagamento", ['enabled' => false], $headers)
            ->assertOk()
            ->assertJsonPath('student.lembrar_pagamento_vencimento', false);
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

        $this->patchJson("/alunos/{$student->id}/lembrete-pagamento", ['enabled' => true], $headers)
            ->assertNotFound();
    }
}
