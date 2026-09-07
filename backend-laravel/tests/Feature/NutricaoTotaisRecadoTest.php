<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\MealLog;
use App\Models\Professional;
use App\Models\Student;
use App\Support\NutricaoTotais;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Os pedaços novos da aba de alimentação (08/09):
 * - total aproximado de kcal/macro do diário (aluno e personal);
 * - sequência de dias registrando;
 * - recado do professor sobre alimentação.
 *
 * O total é aproximado DE PROPÓSITO — item sem quantidade e refeição só de
 * foto não entram — e estes testes travam esse comportamento.
 */
class NutricaoTotaisRecadoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Professional, 1: Student, 2: array} */
    private function cenario(): array
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $headers = ['Authorization' => 'Bearer '.auth('api')->login($professional)];

        $studentId = $this->postJson('/alunos', ['name' => 'Aluno Teste'], $headers)
            ->assertCreated()->json('student.id');

        return [$professional, Student::find($studentId), $headers];
    }

    private function food(string $nome, ?float $kcal, ?float $prot): Food
    {
        return Food::create([
            'codigo_pof' => random_int(1, 999999),
            'codigo_preparo' => 0,
            'nome' => $nome,
            'nome_busca' => Food::normalizarParaBusca($nome),
            'kcal' => $kcal,
            'proteina_g' => $prot,
            'carboidrato_g' => null,
            'lipideos_g' => null,
        ]);
    }

    private function refeicao(Student $s, array $itens): MealLog
    {
        $r = MealLog::create([
            'student_id' => $s->id,
            'data' => now()->toDateString(),
            'momento' => 'almoco',
        ]);
        foreach ($itens as [$food, $g]) {
            $r->itens()->create(['food_id' => $food->id, 'quantidade_g' => $g]);
        }

        return $r->load('itens.food');
    }

    public function test_total_soma_so_o_que_tem_quantidade(): void
    {
        [, $student] = $this->cenario();
        $arroz = $this->food('Arroz cozido', 128, 2.5);
        $frango = $this->food('Frango grelhado', 160, 30);

        // 200 g de arroz (256 kcal, 5 g prot) + frango escolhido sem quantidade.
        $this->refeicao($student, [[$arroz, 200], [$frango, null]]);

        $totais = $this->getJson("/portal/{$student->invite_token}/nutricao")
            ->assertOk()->json('totais');

        $this->assertSame(256, $totais['kcal']);
        $this->assertSame(5, $totais['proteina_g']);
        $this->assertSame(1, $totais['itens_contados']);
        $this->assertSame(1, $totais['itens_sem_quantidade']);
    }

    public function test_macro_nulo_do_alimento_nao_vira_zero(): void
    {
        [, $student] = $this->cenario();
        // kcal conhecida, proteína "não analisada" (NA da POF).
        $doce = $this->food('Doce caseiro', 300, null);
        $this->refeicao($student, [[$doce, 100]]);

        $totais = NutricaoTotais::somar(MealLog::with('itens.food')->get());

        $this->assertSame(300, $totais['kcal']);
        $this->assertSame(0, $totais['proteina_g']); // some 0 porque não havia o que somar
        $this->assertSame(1, $totais['itens_contados']);
    }

    public function test_refeicao_so_de_foto_conta_a_parte_e_nao_soma(): void
    {
        [, $student] = $this->cenario();
        MealLog::create([
            'student_id' => $student->id,
            'data' => now()->toDateString(),
            'momento' => 'jantar',
            'descricao' => 'Pizza',
        ]);

        $totais = $this->getJson("/portal/{$student->invite_token}/nutricao")->json('totais');

        $this->assertSame(0, $totais['kcal']);
        $this->assertSame(1, $totais['refeicoes_sem_itens']);
    }

    public function test_sequencia_conta_dias_seguidos_terminando_hoje(): void
    {
        [, $student] = $this->cenario();
        foreach ([0, 1, 2] as $atras) {
            MealLog::create([
                'student_id' => $student->id,
                'data' => now()->subDays($atras)->toDateString(),
                'momento' => 'almoco',
                'descricao' => 'x',
            ]);
        }

        $this->getJson("/portal/{$student->invite_token}/nutricao")
            ->assertOk()->assertJsonPath('sequencia_dias', 3);
    }

    public function test_sequencia_quebra_com_buraco(): void
    {
        [, $student] = $this->cenario();
        foreach ([0, 1, 3] as $atras) { // falta o dia 2
            MealLog::create([
                'student_id' => $student->id,
                'data' => now()->subDays($atras)->toDateString(),
                'momento' => 'almoco',
                'descricao' => 'x',
            ]);
        }

        $this->getJson("/portal/{$student->invite_token}/nutricao")
            ->assertJsonPath('sequencia_dias', 2);
    }

    public function test_sequencia_zera_se_o_ultimo_registro_e_antigo(): void
    {
        [, $student] = $this->cenario();
        MealLog::create([
            'student_id' => $student->id,
            'data' => now()->subDays(5)->toDateString(),
            'momento' => 'almoco',
            'descricao' => 'x',
        ]);

        $this->getJson("/portal/{$student->invite_token}/nutricao")
            ->assertJsonPath('sequencia_dias', 0);
    }

    public function test_personal_salva_recado_e_o_aluno_ve(): void
    {
        [, $student, $headers] = $this->cenario();

        $this->patchJson("/alunos/{$student->id}/nutricao/recado", [
            'texto' => 'Tenta puxar mais proteína no café da manhã.',
        ], $headers)->assertOk()->assertJsonPath('recado.texto', 'Tenta puxar mais proteína no café da manhã.');

        $this->getJson("/portal/{$student->invite_token}/nutricao")
            ->assertOk()
            ->assertJsonPath('recado_professor.texto', 'Tenta puxar mais proteína no café da manhã.');

        // Limpar manda string vazia.
        $this->patchJson("/alunos/{$student->id}/nutricao/recado", ['texto' => ''], $headers)
            ->assertOk()->assertJsonPath('recado.texto', null);

        $this->getJson("/portal/{$student->invite_token}/nutricao")
            ->assertJsonPath('recado_professor', null);
    }

    public function test_personal_nao_salva_recado_em_aluno_de_outro(): void
    {
        [, $student] = $this->cenario();
        [, , $headersOutro] = $this->cenario();

        $this->patchJson("/alunos/{$student->id}/nutricao/recado", ['texto' => 'oi'], $headersOutro)
            ->assertNotFound();

        $this->assertNull($student->fresh()->nutricao_recado);
    }

    public function test_resumo_do_periodo_media_so_conta_dias_com_registro(): void
    {
        [, $student, $headers] = $this->cenario();
        $arroz = $this->food('Arroz', 100, 2);

        // Hoje: 200 g (200 kcal). Ontem: 100 g (100 kcal). Média = 150.
        $hoje = $this->refeicao($student, [[$arroz, 200]]);
        $ontem = MealLog::create([
            'student_id' => $student->id, 'data' => now()->subDay()->toDateString(), 'momento' => 'almoco',
        ]);
        $ontem->itens()->create(['food_id' => $arroz->id, 'quantidade_g' => 100]);

        $resumo = $this->getJson("/alunos/{$student->id}/nutricao?dias=7", $headers)
            ->assertOk()->json('resumo');

        $this->assertSame(2, $resumo['dias_com_registro']);
        $this->assertSame(150, $resumo['media_kcal_dia']);
    }
}
