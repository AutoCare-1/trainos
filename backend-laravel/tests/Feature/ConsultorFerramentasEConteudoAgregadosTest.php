<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Models\Student;
use App\Support\ConsultorFerramentas;
use App\Support\ConteudoAgregados;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Estas duas classes usavam curdate()/now() direto em SQL bruto (sintaxe
 * exclusiva de MySQL) em vários pontos — nunca coberto por teste, e por isso
 * nunca dava erro no CI/local (SQLite), só apareceria rodando de verdade.
 * Corrigido pro mesmo padrão do resto do projeto (data calculada em PHP,
 * bindada como parâmetro). Este teste garante que continua bindado certo.
 */
class ConsultorFerramentasEConteudoAgregadosTest extends TestCase
{
    use RefreshDatabase;

    private function criarProfissionalComAluno(): array
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);

        $student = Student::create([
            'professional_id' => $professional->id,
            'name' => 'Aluno Teste',
            'invite_token' => uniqid('token'),
        ]);

        return [$professional, $student];
    }

    public function test_consultor_ferramentas_nao_quebra_com_base_vazia(): void
    {
        [$professional, $student] = $this->criarProfissionalComAluno();

        $resumo = ConsultorFerramentas::buscarResumoAluno($professional->id, $student->name);
        $this->assertTrue($resumo['encontrado']);
        $this->assertSame([], $resumo['prs_ultimos_14_dias']);

        $semCheckin = ConsultorFerramentas::listarAlunosSemCheckin($professional->id, 7);
        $this->assertSame([$student->name], $semCheckin['alunos_sem_checkin']);

        $prs = ConsultorFerramentas::listarPrsRecentes($professional->id, 30);
        $this->assertSame([], $prs['prs']);
    }

    public function test_conteudo_agregados_nao_quebra_com_base_vazia(): void
    {
        [$professional] = $this->criarProfissionalComAluno();

        $resumo = ConteudoAgregados::montarResumoAgregadoAlunos($professional->id);
        $this->assertIsString($resumo);
    }
}
