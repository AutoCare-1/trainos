<?php

namespace Tests\Feature;

use App\Models\HydrationLog;
use App\Models\MealLog;
use App\Models\NutritionSuggestion;
use App\Models\Professional;
use App\Models\Student;
use App\Support\Nutricao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Diário alimentar, água e orientação de pré/pós-treino.
 *
 * A parte que mais importa aqui não é o CRUD: é a fronteira. No Brasil,
 * prescrição dietética é privativa do nutricionista (Lei 8.234/91), e quem
 * assina o TrainOS é um profissional de Educação Física. Os testes de
 * encaminhamento e de escopo do prompt existem pra essa linha não ser cruzada
 * por acidente numa mudança futura.
 */
class NutricaoTest extends TestCase
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

    // ─── diário alimentar ───

    public function test_aluno_registra_refeicao_com_foto(): void
    {
        [, $student] = $this->cenario();

        $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", [
            'momento' => 'almoco',
            'descricao' => 'Arroz, feijão, frango e salada',
            'foto' => UploadedFile::fake()->image('almoco.jpg'),
        ])->assertCreated()->assertJsonPath('refeicao.tem_foto', true);

        $this->assertSame(1, MealLog::where('student_id', $student->id)->count());
    }

    public function test_aluno_registra_refeicao_so_com_texto(): void
    {
        [, $student] = $this->cenario();

        $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", [
            'momento' => 'cafe',
            'descricao' => 'Pão com ovo e café',
        ])->assertCreated()->assertJsonPath('refeicao.tem_foto', false);
    }

    public function test_registro_sem_foto_e_sem_texto_e_rejeitado(): void
    {
        [, $student] = $this->cenario();

        // Não diz nada nem pro aluno que vai reler depois, nem pro personal.
        $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", ['momento' => 'jantar'])
            ->assertStatus(422);
    }

    public function test_momento_invalido_e_rejeitado(): void
    {
        [, $student] = $this->cenario();

        $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", [
            'momento' => 'ceia_da_madrugada',
            'descricao' => 'x',
        ])->assertStatus(422);
    }

    public function test_aluno_so_enxerga_e_apaga_o_proprio_registro(): void
    {
        [, $student] = $this->cenario();
        [, $outro] = $this->cenario();

        $refeicaoId = $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", [
            'momento' => 'almoco', 'descricao' => 'Arroz e feijão',
        ])->assertCreated()->json('refeicao.id');

        $this->deleteJson("/portal/{$outro->invite_token}/nutricao/refeicoes/{$refeicaoId}")
            ->assertNotFound();
        $this->getJson("/portal/{$outro->invite_token}/nutricao")
            ->assertOk()
            ->assertJsonCount(0, 'refeicoes');

        $this->deleteJson("/portal/{$student->invite_token}/nutricao/refeicoes/{$refeicaoId}")->assertOk();
        $this->assertSame(0, MealLog::count());
    }

    // ─── água ───

    public function test_copos_de_agua_somam_e_nao_passam_do_teto(): void
    {
        [, $student] = $this->cenario();
        $url = "/portal/{$student->invite_token}/nutricao/agua";

        $this->postJson($url, ['delta' => 1])->assertOk()->assertJsonPath('copos_agua', 1);
        $this->postJson($url, ['delta' => 1])->assertOk()->assertJsonPath('copos_agua', 2);
        $this->postJson($url, ['delta' => -1])->assertOk()->assertJsonPath('copos_agua', 1);

        // Toque repetido sem querer não vira 500 copos.
        for ($i = 0; $i < HydrationLog::MAX_COPOS + 5; $i++) {
            $this->postJson($url, ['delta' => 1]);
        }
        $this->assertSame(HydrationLog::MAX_COPOS, (int) HydrationLog::first()->copos);
    }

    public function test_agua_nao_fica_negativa(): void
    {
        [, $student] = $this->cenario();

        $this->postJson("/portal/{$student->invite_token}/nutricao/agua", ['delta' => -1])
            ->assertOk()
            ->assertJsonPath('copos_agua', 0);
    }

    // ─── a fronteira: quando a IA não pode responder ───

    public function test_aluno_com_condicao_de_saude_e_encaminhado_ao_nutricionista(): void
    {
        [, $student] = $this->cenario();
        $student->update(['health_notes' => 'Diabético tipo 2, uso insulina']);

        // Sem chave da Anthropic de propósito: se a IA fosse chamada, o teste
        // falharia — o encaminhamento tem que acontecer ANTES da chamada.
        config(['services.anthropic.api_key' => '']);

        $this->postJson("/portal/{$student->invite_token}/nutricao/sugestoes", ['momento' => 'pre_treino'])
            ->assertCreated()
            ->assertJsonPath('sugestao.encaminhou_nutricionista', true);

        $this->assertStringContainsString('nutricionista', NutritionSuggestion::first()->resposta);
    }

    public function test_condicao_declarada_na_anamnese_tambem_encaminha(): void
    {
        [, $student] = $this->cenario();
        $student->update(['anamnese' => ['restricoes' => 'Sou celíaca, não posso glúten']]);
        config(['services.anthropic.api_key' => '']);

        $this->postJson("/portal/{$student->invite_token}/nutricao/sugestoes", ['momento' => 'pos_treino'])
            ->assertCreated()
            ->assertJsonPath('sugestao.encaminhou_nutricionista', true);
    }

    public function test_aluno_sem_condicao_declarada_nao_e_encaminhado_automaticamente(): void
    {
        [, $student] = $this->cenario();

        $this->assertFalse(Nutricao::exigeNutricionista($student));
    }

    public function test_prompt_proibe_quantidade_caloria_e_suplemento(): void
    {
        [, $student] = $this->cenario();

        // O prompt é a única coisa que segura a fronteira entre orientação
        // geral e prescrição. Se alguém afrouxar isso numa refatoração, este
        // teste avisa antes de virar problema do personal que assina o app.
        $metodo = new \ReflectionMethod(Nutricao::class, 'systemPrompt');
        $prompt = mb_strtolower($metodo->invoke(null, 'pre_treino', $student));

        foreach (['gramas', 'calorias', 'suplemento', 'cardápio', 'plano alimentar'] as $proibido) {
            $this->assertStringContainsString($proibido, $prompt, "prompt precisa proibir '{$proibido}'");
        }
        $this->assertStringContainsString('nutricionista', $prompt);
    }

    public function test_kill_switch_desliga_a_sugestao_sem_derrubar_o_resto(): void
    {
        [, $student] = $this->cenario();
        config(['ia_pipelines.nutricao_sugestao' => false]);

        $this->postJson("/portal/{$student->invite_token}/nutricao/sugestoes", ['momento' => 'pre_treino'])
            ->assertStatus(503);

        // Registrar refeição não passa por IA e continua funcionando.
        $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", [
            'momento' => 'almoco', 'descricao' => 'Arroz e feijão',
        ])->assertCreated();
    }

    // ─── lado do personal ───

    public function test_personal_ve_o_diario_e_as_orientacoes_do_proprio_aluno(): void
    {
        [, $student, $headers] = $this->cenario();

        $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", [
            'momento' => 'almoco', 'descricao' => 'Arroz, feijão e frango',
        ])->assertCreated();
        $this->postJson("/portal/{$student->invite_token}/nutricao/agua", ['delta' => 1])->assertOk();
        NutritionSuggestion::create([
            'student_id' => $student->id, 'momento' => 'pre_treino', 'resposta' => 'Uma fruta antes.',
        ]);

        $this->getJson("/alunos/{$student->id}/nutricao", $headers)
            ->assertOk()
            ->assertJsonCount(1, 'refeicoes')
            ->assertJsonCount(1, 'agua')
            // As orientações aparecem pro personal porque é ele quem responde
            // profissionalmente pelo aluno.
            ->assertJsonCount(1, 'sugestoes')
            ->assertJsonPath('refeicoes.0.descricao', 'Arroz, feijão e frango');
    }

    public function test_personal_nao_ve_diario_de_aluno_de_outro_personal(): void
    {
        [, $student] = $this->cenario();
        [, , $headersOutro] = $this->cenario();

        $this->getJson("/alunos/{$student->id}/nutricao", $headersOutro)->assertNotFound();
    }
}
