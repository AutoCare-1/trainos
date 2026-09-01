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

    public function test_registro_e_sempre_de_hoje_mesmo_se_o_cliente_mandar_outra_data(): void
    {
        [, $student] = $this->cenario();

        // Deixar escolher a data permitia preencher a semana inteira no
        // domingo, de memória — histórico inventado, que é pior que histórico
        // nenhum porque o personal decide coisa em cima dele.
        $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", [
            'momento' => 'almoco', 'descricao' => 'x', 'data' => '2020-01-01',
        ])->assertCreated();

        $this->assertSame(now()->toDateString(), MealLog::first()->data->toDateString());
    }

    public function test_agua_tambem_ignora_data_enviada_pelo_cliente(): void
    {
        [, $student] = $this->cenario();

        $this->postJson("/portal/{$student->invite_token}/nutricao/agua", [
            'recipiente' => 'copo', 'sinal' => 1, 'data' => '2020-01-01',
        ])->assertOk();

        $this->assertSame(now()->toDateString(), HydrationLog::first()->data->toDateString());
    }

    public function test_aluno_ve_so_o_dia_de_hoje(): void
    {
        [, $student] = $this->cenario();

        // Registro de ontem existe no banco (o personal vê), mas some da tela
        // do aluno: ela é ritual diário, não arquivo.
        MealLog::create([
            'student_id' => $student->id,
            'data' => now()->subDay()->toDateString(),
            'momento' => 'jantar',
            'descricao' => 'jantar de ontem',
        ]);
        MealLog::create([
            'student_id' => $student->id,
            'data' => now()->toDateString(),
            'momento' => 'almoco',
            'descricao' => 'almoço de hoje',
        ]);

        $this->getJson("/portal/{$student->invite_token}/nutricao")
            ->assertOk()
            ->assertJsonCount(1, 'refeicoes')
            ->assertJsonPath('refeicoes.0.descricao', 'almoço de hoje');
    }

    public function test_rajada_de_upload_de_refeicao_e_barrada(): void
    {
        [, $student] = $this->cenario();
        $url = "/portal/{$student->invite_token}/nutricao/refeicoes";

        // Sem limite, um invite_token vazado enchia o disco com imagens de 8 MB.
        for ($i = 0; $i < 40; $i++) {
            $this->postJson($url, ['momento' => 'lanche', 'descricao' => "registro {$i}"])->assertCreated();
        }

        $this->postJson($url, ['momento' => 'lanche', 'descricao' => 'o de numero 41'])
            ->assertStatus(429);
    }

    // ─── água ───

    public function test_agua_soma_por_recipiente_e_nao_passa_do_teto(): void
    {
        [, $student] = $this->cenario();
        $url = "/portal/{$student->invite_token}/nutricao/agua";

        $this->postJson($url, ['recipiente' => 'copo', 'sinal' => 1])->assertOk()->assertJsonPath('agua_ml', 200);
        $this->postJson($url, ['recipiente' => 'garrafa', 'sinal' => 1])->assertOk()->assertJsonPath('agua_ml', 700);
        $this->postJson($url, ['recipiente' => 'copo', 'sinal' => -1])->assertOk()->assertJsonPath('agua_ml', 500);

        // Toque repetido sem querer não vira 40 litros.
        for ($i = 0; $i < 30; $i++) {
            $this->postJson($url, ['recipiente' => 'garrafa', 'sinal' => 1]);
        }
        $this->assertSame(HydrationLog::MAX_ML, (int) HydrationLog::first()->ml);
    }

    public function test_agua_nao_fica_negativa(): void
    {
        [, $student] = $this->cenario();

        $this->postJson("/portal/{$student->invite_token}/nutricao/agua", ['recipiente' => 'copo', 'sinal' => -1])
            ->assertOk()
            ->assertJsonPath('agua_ml', 0);
    }

    public function test_cliente_nao_escolhe_o_volume(): void
    {
        [, $student] = $this->cenario();

        // Quem define quantos ml vale cada recipiente é o servidor — senão o
        // aluno registraria qualquer número no próprio histórico.
        $this->postJson("/portal/{$student->invite_token}/nutricao/agua", ['recipiente' => 'balde', 'sinal' => 1])
            ->assertStatus(422);
        $this->postJson("/portal/{$student->invite_token}/nutricao/agua", ['ml' => 5000, 'sinal' => 1])
            ->assertStatus(422);
    }

    public function test_meta_de_agua_sai_do_peso_do_aluno(): void
    {
        [, $student] = $this->cenario();

        // Sem peso informado: o que a fórmula daria pra um adulto médio, em vez
        // de um número escolhido no olho.
        $this->getJson("/portal/{$student->invite_token}/nutricao")
            ->assertOk()
            ->assertJsonPath('agua_meta_ml', HydrationLog::META_PADRAO_ML);

        // Com peso: ~35 ml por kg, que é o ponto — quem é maior bebe mais, e
        // não fica com uma meta chutada igual pra todo mundo.
        $student->update(['weight_kg' => 90]);
        $this->getJson("/portal/{$student->invite_token}/nutricao")
            ->assertOk()
            ->assertJsonPath('agua_meta_ml', 3200);
    }

    public function test_meta_acompanha_a_pesagem_mais_recente(): void
    {
        [, $student, $headers] = $this->cenario();

        // A pesagem do personal vai pra body_measurements e não mexe em
        // students.weight_kg — sem olhar a medição, a meta congelaria no dia
        // do cadastro (quando quase ninguém preenche peso).
        $this->postJson("/alunos/{$student->id}/medicoes", ['weight_kg' => 90], $headers)->assertCreated();

        $this->getJson("/portal/{$student->invite_token}/nutricao")
            ->assertOk()
            ->assertJsonPath('agua_meta_ml', 3200)
            ->assertJsonPath('agua_meta_do_peso', true);

        // Emagreceu: a meta acompanha.
        $this->postJson("/alunos/{$student->id}/medicoes", [
            'weight_kg' => 70, 'recorded_at' => now()->addDay()->toDateString(),
        ], $headers)->assertCreated();

        $this->getJson("/portal/{$student->invite_token}/nutricao")
            ->assertOk()
            ->assertJsonPath('agua_meta_ml', 2500);
    }

    public function test_aluno_sem_peso_nenhum_recebe_a_meta_padrao_marcada_como_generica(): void
    {
        [, $student] = $this->cenario();

        $this->getJson("/portal/{$student->invite_token}/nutricao")
            ->assertOk()
            ->assertJsonPath('agua_meta_ml', HydrationLog::META_PADRAO_ML)
            // Falso: a tela não pode dizer "referência pro seu peso" a quem
            // nunca foi pesado.
            ->assertJsonPath('agua_meta_do_peso', false);
    }

    public function test_peso_absurdo_nao_gera_meta_absurda(): void
    {
        [, $student] = $this->cenario();

        // "7" no lugar de "70" na hora de cadastrar não pode virar meta de 245 ml.
        $student->update(['weight_kg' => 7]);
        $this->assertSame(1500, HydrationLog::metaDiariaMl(7.0));

        $student->update(['weight_kg' => 300]);
        $this->assertSame(4500, HydrationLog::metaDiariaMl(300.0));
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

    /**
     * A varredura da anamnese decide se a IA responde ou encaminha. Errar pra
     * qualquer lado é ruim: deixar passar uma condição de saúde é o problema
     * grave, e encaminhar quem escreveu "primeira vez na academia" faz o aluno
     * nunca mais conseguir usar o recurso.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('casosDeAnamnese')]
    public function test_varredura_da_anamnese_decide_certo(string $texto, bool $deveEncaminhar): void
    {
        [, $student] = $this->cenario();
        $student->update(['health_notes' => $texto]);

        $this->assertSame(
            $deveEncaminhar,
            Nutricao::exigeNutricionista($student->fresh()),
            "\"{$texto}\" deveria ".($deveEncaminhar ? 'encaminhar' : 'ser respondido')
        );
    }

    public static function casosDeAnamnese(): array
    {
        return [
            // Precisa de nutricionista — escrito como a pessoa escreve mesmo,
            // com acento (era aqui que a trava falhava: "diabet" não casa com
            // "diabético").
            'diabético com acento' => ['Diabético tipo 2', true],
            'diabetes sem acento' => ['Tenho diabetes', true],
            'alérgico com acento' => ['Alérgico a amendoim', true],
            'celíaca' => ['Sou celíaca', true],
            'gestante' => ['Estou gestante', true],
            'renal' => ['Problema renal', true],
            'hepático com acento' => ['Problema hepático', true],
            'tireoide' => ['Tireoide alterada', true],
            'intolerância a lactose' => ['Intolerância a lactose', true],

            // Não pode encaminhar: sem isso o aluno perde o recurso pra sempre
            // por ter escrito uma frase comum.
            'primeira vez' => ['Primeira vez na academia', false],
            'sugestão' => ['Aceito sugestão de treino', false],
            'primo' => ['Tenho um primo que treina aqui', false],
            'sem observação' => ['', false],
            'quer emagrecer' => ['Quero emagrecer', false],
        ];
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
        $this->postJson("/portal/{$student->invite_token}/nutricao/agua", ['recipiente' => 'copo', 'sinal' => 1])->assertOk();
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
