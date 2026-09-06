<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\MealLog;
use App\Models\MealLogItem;
use App\Models\Professional;
use App\Models\Student;
use Database\Seeders\AlimentoTacoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tabela de alimentos (TACO/NEPA) e o registro por alimento no diário.
 *
 * O ponto da tabela é o aluno ESCOLHER em vez de digitar: digitar é o gargalo
 * que faz ele abandonar o diário, e texto livre não dá pra comparar entre
 * dias. Os testes de integridade dos dados existem porque valor nutricional
 * errado é pior que ausente — o personal decide em cima dele.
 */
class AlimentoTacoTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Student, 1: array} */
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

        return [Student::find($studentId), $headers];
    }

    // ─── integridade da tabela ───

    public function test_seeder_carrega_a_taco_inteira(): void
    {
        $this->seed(AlimentoTacoSeeder::class);

        // 597 é a contagem da 4a edição revisada e ampliada. Se esse número
        // mudar sem alguém ter trocado a fonte de propósito, a importação
        // perdeu (ou duplicou) alimento.
        $this->assertSame(597, Food::count());
    }

    public function test_reexecutar_o_seeder_nao_duplica(): void
    {
        $this->seed(AlimentoTacoSeeder::class);
        $idArroz = Food::where('codigo_taco', 1)->value('id');

        $this->seed(AlimentoTacoSeeder::class);

        $this->assertSame(597, Food::count());
        // O id precisa sobreviver: é o que liga a refeição que o aluno já
        // registrou ao alimento.
        $this->assertSame($idArroz, Food::where('codigo_taco', 1)->value('id'));
    }

    public function test_valores_conferem_com_a_tabela_publicada(): void
    {
        $this->seed(AlimentoTacoSeeder::class);

        // Conferido contra a TACO 4a ed. — se a extração da planilha
        // desalinhar as colunas um dia, é aqui que aparece.
        $arroz = Food::where('codigo_taco', 1)->first();
        $this->assertSame('Arroz, integral, cozido', $arroz->nome);
        $this->assertSame('Cereais e derivados', $arroz->categoria);
        $this->assertEqualsWithDelta(123.53, $arroz->kcal, 0.01);
        $this->assertEqualsWithDelta(2.59, $arroz->proteina_g, 0.01);
        $this->assertEqualsWithDelta(25.81, $arroz->carboidrato_g, 0.01);
    }

    public function test_nao_analisado_fica_nulo_e_nao_vira_zero(): void
    {
        $this->seed(AlimentoTacoSeeder::class);

        // A TACO marca NA (não analisado) em vários campos. Virar 0 seria
        // afirmar que o alimento não tem aquele nutriente, que é falso.
        $this->assertGreaterThan(0, Food::whereNull('fibra_g')->count());
        // E nenhum alimento pode ter ficado sem nome ou categoria.
        $this->assertSame(0, Food::whereNull('nome')->orWhereNull('categoria')->count());
    }

    // ─── busca ───

    public function test_busca_encontra_por_palavras_soltas(): void
    {
        $this->seed(AlimentoTacoSeeder::class);
        [$student] = $this->cenario();

        // "frango grelhado" não aparece nessa ordem em nenhum nome da TACO
        // ("Frango, peito, sem pele, grelhado") — buscar a frase inteira não
        // acharia nada, que é o erro óbvio de implementação aqui.
        $nomes = collect($this->getJson("/portal/{$student->invite_token}/nutricao/alimentos?busca=frango grelhado")
            ->assertOk()
            ->json('alimentos'))->pluck('nome');

        $this->assertNotEmpty($nomes);
        foreach ($nomes as $nome) {
            $this->assertStringContainsStringIgnoringCase('frango', $nome);
            $this->assertStringContainsStringIgnoringCase('grelhado', $nome);
        }
    }

    public function test_busca_sem_termo_devolve_alimentos(): void
    {
        $this->seed(AlimentoTacoSeeder::class);
        [$student] = $this->cenario();

        // A tela precisa ter o que mostrar antes de o aluno digitar.
        $this->getJson("/portal/{$student->invite_token}/nutricao/alimentos")
            ->assertOk()
            ->assertJsonCount(40, 'alimentos');
    }

    public function test_busca_exige_token_valido(): void
    {
        $this->seed(AlimentoTacoSeeder::class);

        $this->getJson('/portal/token-invalido/nutricao/alimentos')->assertNotFound();
    }

    // ─── registro por alimento ───

    public function test_aluno_registra_refeicao_escolhendo_alimentos(): void
    {
        $this->seed(AlimentoTacoSeeder::class);
        [$student] = $this->cenario();
        $arroz = Food::where('codigo_taco', 1)->first();
        $feijao = Food::where('nome', 'like', 'Feijão, carioca, cozido%')->first();

        $resposta = $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", [
            'momento' => 'almoco',
            'alimentos' => [
                ['food_id' => $arroz->id, 'quantidade_g' => 150],
                ['food_id' => $feijao->id],
            ],
        ])->assertCreated();

        $resposta->assertJsonCount(2, 'refeicao.alimentos')
            ->assertJsonPath('refeicao.alimentos.0.nome', 'Arroz, integral, cozido')
            ->assertJsonPath('refeicao.alimentos.0.quantidade_g', 150)
            // Quantidade é opcional de propósito: ninguém acerta grama no olho,
            // e o valor do diário está em O QUE foi comido.
            ->assertJsonPath('refeicao.alimentos.1.quantidade_g', null);

        $this->assertSame(2, MealLogItem::count());
    }

    public function test_refeicao_so_com_alimentos_e_valida(): void
    {
        $this->seed(AlimentoTacoSeeder::class);
        [$student] = $this->cenario();

        // Sem foto e sem texto, mas com alimento escolhido: é registro
        // completo, tem que passar.
        $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", [
            'momento' => 'cafe',
            'alimentos' => [['food_id' => Food::first()->id]],
        ])->assertCreated();
    }

    public function test_refeicao_totalmente_vazia_continua_rejeitada(): void
    {
        [$student] = $this->cenario();

        $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", ['momento' => 'jantar'])
            ->assertStatus(422);
    }

    public function test_alimento_inexistente_e_rejeitado(): void
    {
        [$student] = $this->cenario();

        $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", [
            'momento' => 'almoco',
            'alimentos' => [['food_id' => '019fb8d4-0000-0000-0000-000000000000']],
        ])->assertStatus(422);
    }

    public function test_quantidade_absurda_e_rejeitada(): void
    {
        $this->seed(AlimentoTacoSeeder::class);
        [$student] = $this->cenario();

        // 5 kg de arroz num almoço é engano de digitação, não porção.
        $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", [
            'momento' => 'almoco',
            'alimentos' => [['food_id' => Food::first()->id, 'quantidade_g' => 5000]],
        ])->assertStatus(422);
    }

    public function test_apagar_a_refeicao_leva_os_alimentos_junto(): void
    {
        $this->seed(AlimentoTacoSeeder::class);
        [$student] = $this->cenario();

        $id = $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", [
            'momento' => 'almoco',
            'alimentos' => [['food_id' => Food::first()->id]],
        ])->assertCreated()->json('refeicao.id');

        $this->deleteJson("/portal/{$student->invite_token}/nutricao/refeicoes/{$id}")->assertOk();

        $this->assertSame(0, MealLog::count());
        $this->assertSame(0, MealLogItem::count());
    }

    public function test_personal_ve_os_alimentos_que_o_aluno_escolheu(): void
    {
        $this->seed(AlimentoTacoSeeder::class);
        [$student, $headers] = $this->cenario();

        $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", [
            'momento' => 'almoco',
            'alimentos' => [['food_id' => Food::where('codigo_taco', 1)->value('id'), 'quantidade_g' => 150]],
        ])->assertCreated();

        // É isso que troca "arroz feijão frango" por dado comparável entre
        // dias — o motivo da tabela existir.
        $this->getJson("/alunos/{$student->id}/nutricao", $headers)
            ->assertOk()
            ->assertJsonPath('refeicoes.0.alimentos.0.nome', 'Arroz, integral, cozido')
            ->assertJsonPath('refeicoes.0.alimentos.0.quantidade_g', 150);
    }
}
