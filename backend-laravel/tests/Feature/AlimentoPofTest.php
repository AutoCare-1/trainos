<?php

namespace Tests\Feature;

use App\Models\Food;
use App\Models\FoodMeasure;
use App\Models\MealLog;
use App\Models\MealLogItem;
use App\Models\Professional;
use App\Models\Student;
use Database\Seeders\AlimentoPofSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Catálogo de alimentos (POF/IBGE) e o registro por alimento no diário.
 *
 * O ponto do catálogo é o aluno ESCOLHER em vez de digitar: digitar é o gargalo
 * que faz ele abandonar o diário, e texto livre não dá pra comparar entre
 * dias. Os testes de integridade dos dados existem porque valor nutricional
 * errado é pior que ausente — o personal decide em cima dele.
 *
 * A escolha da fonte importa e está testada aqui: a TACO, que veio antes, não
 * tem "macarrão cozido" nem pizza, e alimento básico que não aparece na busca
 * faz o aluno concluir (com razão) que o app é mal feito.
 */
class AlimentoPofTest extends TestCase
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

    /** @return list<string> nomes que a busca do portal devolve pro termo */
    private function buscarComoAluno(string $termo): array
    {
        [$student] = $this->cenario();

        return collect($this->getJson("/portal/{$student->invite_token}/nutricao/alimentos?busca=".urlencode($termo))
            ->assertOk()->json('alimentos'))->pluck('nome')->all();
    }

    // ─── integridade da tabela ───

    public function test_seeder_carrega_o_catalogo_inteiro(): void
    {
        $this->seed(AlimentoPofSeeder::class);

        // 1.971 é a contagem da planilha da POF (alimento x preparo). Se esse
        // número mudar sem alguém ter trocado a fonte de propósito, a
        // importação perdeu (ou duplicou) alimento.
        $this->assertSame(1971, Food::count());
    }

    public function test_reexecutar_o_seeder_nao_duplica(): void
    {
        $this->seed(AlimentoPofSeeder::class);
        $idArroz = Food::where('codigo_pof', 6300101)->value('id');

        $this->seed(AlimentoPofSeeder::class);

        $this->assertSame(1971, Food::count());
        // O id precisa sobreviver: é o que liga a refeição que o aluno já
        // registrou ao alimento.
        $this->assertSame($idArroz, Food::where('codigo_pof', 6300101)->value('id'));
    }

    public function test_valores_conferem_com_a_tabela_publicada(): void
    {
        $this->seed(AlimentoPofSeeder::class);

        // Conferido contra a planilha publicada. A ordem das colunas na POF
        // NÃO é a da TACO (lipídeos vem antes de carboidrato), e ler na ordem
        // errada produz uma tabela que parece certa e está toda trocada.
        $arroz = Food::where('codigo_pof', 6300101)->first();
        $this->assertSame('Arroz (polido, parboilizado, agulha, agulhinha, etc.)', $arroz->nome);
        $this->assertEqualsWithDelta(135.62, $arroz->kcal, 0.01);
        $this->assertEqualsWithDelta(2.50, $arroz->proteina_g, 0.01);
        $this->assertEqualsWithDelta(27.78, $arroz->carboidrato_g, 0.01);
        $this->assertEqualsWithDelta(1.20, $arroz->lipideos_g, 0.01);
    }

    public function test_relacao_de_atwater_confirma_o_mapeamento_das_colunas(): void
    {
        $this->seed(AlimentoPofSeeder::class);

        // kcal ≈ 4·proteína + 4·carboidrato + 9·lipídeos. Coluna trocada faz
        // essa conta desabar em bloco; particularidade de alimento faz ela
        // errar em alguns. É a checagem que pega o erro que ninguém enxerga
        // lendo o arquivo gerado.
        $dentro = $fora = 0;
        foreach (Food::whereNotNull(['kcal', 'proteina_g', 'carboidrato_g', 'lipideos_g'])->where('kcal', '>', 0)->get() as $a) {
            $estimado = 4 * $a->proteina_g + 4 * $a->carboidrato_g + 9 * $a->lipideos_g;
            abs($estimado - $a->kcal) <= 0.15 * $a->kcal ? $dentro++ : $fora++;
        }

        // Bebida alcoólica (álcool tem 7 kcal/g e não entra nos macros) e
        // vegetal muito fibroso divergem por natureza. Abaixo de 85% é sinal
        // de coluna trocada, não de particularidade.
        $this->assertGreaterThan(0.85, $dentro / ($dentro + $fora));
    }

    public function test_nao_analisado_fica_nulo_e_nao_vira_zero(): void
    {
        $this->seed(AlimentoPofSeeder::class);

        // A fonte deixa campo em branco em vários alimentos. Virar 0 seria
        // afirmar que o alimento não tem aquele nutriente, que é falso.
        $this->assertGreaterThan(0, Food::whereNull('fibra_g')->count());
        // Mas o nome nunca pode faltar: é o que a pessoa lê.
        $this->assertSame(0, Food::whereNull('nome')->orWhereNull('nome_busca')->count());
    }

    // ─── busca ───

    public function test_busca_encontra_por_palavras_soltas(): void
    {
        $this->seed(AlimentoPofSeeder::class);
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

    public function test_busca_acha_com_e_sem_acento(): void
    {
        $this->seed(AlimentoPofSeeder::class);
        [$student] = $this->cenario();

        // Metade do teclado brasileiro no celular sai sem acento, e a TACO
        // escreve tudo com. Se "macarrao" não achasse "Macarrão", o aluno
        // concluiria que o alimento não está no app.
        foreach (['macarrão', 'macarrao', 'MACARRAO'] as $termo) {
            $nomes = collect($this->getJson("/portal/{$student->invite_token}/nutricao/alimentos?busca={$termo}")
                ->assertOk()->json('alimentos'))->pluck('nome');

            $this->assertNotEmpty($nomes, "busca por '{$termo}' não achou nada");
            $this->assertTrue($nomes->contains(fn (string $n) => str_starts_with($n, 'Macarrão')), $termo);
        }
    }

    public function test_alimento_obvio_vem_primeiro_na_busca(): void
    {
        $this->seed(AlimentoPofSeeder::class);

        // Em ordem alfabética "Amido de arroz" e "Arroz de leite" passam na
        // frente de "Arroz". Quem digita "arroz" comeu arroz — e alimento
        // básico no fim de uma lista de 40 faz o aluno achar que não tem.
        $this->assertSame(
            'Arroz (polido, parboilizado, agulha, agulhinha, etc.)',
            $this->buscarComoAluno('arroz')[0]
        );
        $this->assertSame('Feijão (preto, mulatinho, roxo, rosinha, etc.)', $this->buscarComoAluno('feijão')[0]);
        $this->assertStringStartsWith('Macarrão', $this->buscarComoAluno('macarrao')[0]);
    }

    public function test_busca_sem_termo_devolve_alimentos(): void
    {
        $this->seed(AlimentoPofSeeder::class);
        [$student] = $this->cenario();

        // A tela precisa ter o que mostrar antes de o aluno digitar.
        $this->getJson("/portal/{$student->invite_token}/nutricao/alimentos")
            ->assertOk()
            ->assertJsonCount(40, 'alimentos');
    }

    public function test_busca_exige_token_valido(): void
    {
        $this->seed(AlimentoPofSeeder::class);

        $this->getJson('/portal/token-invalido/nutricao/alimentos')->assertNotFound();
    }

    // ─── registro por alimento ───

    public function test_aluno_registra_refeicao_escolhendo_alimentos(): void
    {
        $this->seed(AlimentoPofSeeder::class);
        [$student] = $this->cenario();
        $arroz = Food::where('codigo_pof', 6300101)->first();
        $feijao = Food::where('codigo_pof', 6303102)->first();

        $resposta = $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", [
            'momento' => 'almoco',
            'alimentos' => [
                ['food_id' => $arroz->id, 'quantidade_g' => 150],
                ['food_id' => $feijao->id],
            ],
        ])->assertCreated();

        $resposta->assertJsonCount(2, 'refeicao.alimentos')
            ->assertJsonPath('refeicao.alimentos.0.nome', 'Arroz (polido, parboilizado, agulha, agulhinha, etc.)')
            ->assertJsonPath('refeicao.alimentos.0.quantidade_g', 150)
            // Quantidade é opcional de propósito: ninguém acerta grama no olho,
            // e o valor do diário está em O QUE foi comido.
            ->assertJsonPath('refeicao.alimentos.1.quantidade_g', null);

        $this->assertSame(2, MealLogItem::count());
    }

    public function test_refeicao_so_com_alimentos_e_valida(): void
    {
        $this->seed(AlimentoPofSeeder::class);
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
        $this->seed(AlimentoPofSeeder::class);
        [$student] = $this->cenario();

        // 5 kg de arroz num almoço é engano de digitação, não porção.
        $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", [
            'momento' => 'almoco',
            'alimentos' => [['food_id' => Food::first()->id, 'quantidade_g' => 5000]],
        ])->assertStatus(422);
    }

    public function test_apagar_a_refeicao_leva_os_alimentos_junto(): void
    {
        $this->seed(AlimentoPofSeeder::class);
        [$student] = $this->cenario();

        $id = $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", [
            'momento' => 'almoco',
            'alimentos' => [['food_id' => Food::first()->id]],
        ])->assertCreated()->json('refeicao.id');

        $this->deleteJson("/portal/{$student->invite_token}/nutricao/refeicoes/{$id}")->assertOk();

        $this->assertSame(0, MealLog::count());
        $this->assertSame(0, MealLogItem::count());
    }

    // ─── medidas caseiras ───

    public function test_alimentos_comuns_tem_medida_caseira(): void
    {
        $this->seed(AlimentoPofSeeder::class);

        // Os campeões do diário brasileiro. Se algum sumir numa reimportação,
        // é aqui que aparece — e é o tipo de falta que o aluno percebe na
        // primeira vez que usa a tela.
        $comuns = [
            'Feijão (preto, mulatinho, roxo, rosinha, etc.)',
            'Arroz (polido, parboilizado, agulha, agulhinha, etc.)',
            'Ovo de galinha, frito',
            'Macarrão, cozido',
            'Pão de sal',
        ];

        foreach ($comuns as $nome) {
            $alimento = Food::where('nome', $nome)->with('medidas')->first();
            $this->assertNotNull($alimento, "{$nome} sumiu da tabela");
            $this->assertNotEmpty($alimento->medidas, "{$nome} ficou sem medida caseira");
        }

        // Pão francês é "Pão de sal" na POF, e quase ninguém chama assim —
        // sem o sinônimo, a busca por "pão francês" volta vazia.
        $paoFrances = $this->buscarComoAluno('pão francês');
        $this->assertContains('Pão de sal', $paoFrances);

        // Conferido contra o IBGE: 1 concha de feijão = 140 g.
        $concha = Food::where('codigo_pof', 6303102)->first()
            ->medidas()->where('nome', 'Concha')->value('gramas');
        $this->assertEqualsWithDelta(140, $concha, 0.01);
    }

    public function test_busca_devolve_as_medidas_junto(): void
    {
        $this->seed(AlimentoPofSeeder::class);
        [$student] = $this->cenario();

        $resposta = $this->getJson("/portal/{$student->invite_token}/nutricao/alimentos?busca=feijão")
            ->assertOk();

        $medidas = collect($resposta->json('alimentos'))->firstWhere('nome', 'Feijão (preto, mulatinho, roxo, rosinha, etc.)')['medidas'] ?? [];
        $this->assertNotEmpty($medidas);
    }

    public function test_medida_caseira_vira_grama_no_servidor(): void
    {
        $this->seed(AlimentoPofSeeder::class);
        [$student] = $this->cenario();
        $feijao = Food::where('codigo_pof', 6303102)->first();
        $concha = $feijao->medidas()->where('nome', 'Concha')->first();

        $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", [
            'momento' => 'almoco',
            'alimentos' => [['food_id' => $feijao->id, 'medida_id' => $concha->id, 'medida_qtd' => 2]],
        ])->assertCreated()
            // 2 conchas = 280 g. Quem converte é o servidor: quem sabe quanto
            // pesa uma concha é ele, não o cliente.
            ->assertJsonPath('refeicao.alimentos.0.quantidade_g', 280)
            // E o rótulo fica guardado, porque "280 g" não diz nada pro aluno.
            ->assertJsonPath('refeicao.alimentos.0.medida', '2 conchas');
    }

    public function test_rotulo_da_medida_vai_pro_plural_certo(): void
    {
        $this->seed(AlimentoPofSeeder::class);
        [$student] = $this->cenario();
        $feijao = Food::where('codigo_pof', 6303102)->first();
        $colher = $feijao->medidas()->where('nome', 'Colher de sopa')->first();

        $registrar = fn (float $qtd) => $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", [
            'momento' => 'almoco',
            'alimentos' => [['food_id' => $feijao->id, 'medida_id' => $colher->id, 'medida_qtd' => $qtd]],
        ])->assertCreated()->json('refeicao.alimentos.0.medida');

        // Só a primeira palavra vai pro plural: "colheres de sopa", não
        // "colheres de sopas".
        $this->assertSame('3 colheres de sopa', $registrar(3));
        $this->assertSame('1 colher de sopa', $registrar(1));
        // Meia porção fica no singular, como se fala.
        $this->assertSame('0,5 colher de sopa', $registrar(0.5));
    }

    public function test_medida_de_outro_alimento_e_ignorada(): void
    {
        $this->seed(AlimentoPofSeeder::class);
        [$student] = $this->cenario();
        $feijao = Food::where('codigo_pof', 6303102)->first();
        $medidaDeOutro = FoodMeasure::where('food_id', '!=', $feijao->id)->first();

        // Mandar a concha do arroz junto com o feijão não pode virar grama do
        // arroz — seria número inventado no registro do aluno.
        $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", [
            'momento' => 'almoco',
            'alimentos' => [['food_id' => $feijao->id, 'medida_id' => $medidaDeOutro->id, 'medida_qtd' => 1]],
        ])->assertCreated()
            ->assertJsonPath('refeicao.alimentos.0.quantidade_g', null)
            ->assertJsonPath('refeicao.alimentos.0.medida', null);
    }

    public function test_personal_ve_os_alimentos_que_o_aluno_escolheu(): void
    {
        $this->seed(AlimentoPofSeeder::class);
        [$student, $headers] = $this->cenario();

        $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", [
            'momento' => 'almoco',
            'alimentos' => [['food_id' => Food::where('codigo_pof', 6300101)->value('id'), 'quantidade_g' => 150]],
        ])->assertCreated();

        // É isso que troca "arroz feijão frango" por dado comparável entre
        // dias — o motivo da tabela existir.
        $this->getJson("/alunos/{$student->id}/nutricao", $headers)
            ->assertOk()
            ->assertJsonPath('refeicoes.0.alimentos.0.nome', 'Arroz (polido, parboilizado, agulha, agulhinha, etc.)')
            ->assertJsonPath('refeicoes.0.alimentos.0.quantidade_g', 150);
    }

    public function test_nomes_de_medida_sao_exatamente_os_conferidos(): void
    {
        // A planilha do IBGE escreve tudo em caixa alta e sem acento ("COPO
        // MEDIO", "PEDACO", "FILE"), e esse nome é lido pelo aluno na hora de
        // escolher e pelo personal no diário. Texto torto é o tipo de coisa que
        // ninguém reporta e todo mundo vê.
        //
        // O teste trava a lista inteira em vez de procurar acento faltando:
        // numa reimportação, nome novo é justamente o que precisa de olho
        // humano. E lê o arquivo, não o banco — o MySQL daqui compara com
        // collation que ignora acento, então 'Copo medio' casaria com
        // 'Copo médio' e o teste passaria sem querer.
        $esperados = [
            'Asa', 'Bago', 'Banda', 'Barra', 'Bife', 'Bisnaga', 'Bola', 'Cacho',
            'Caneca', 'Caneco', 'Casquinha', 'Colher de café', 'Colher de chá',
            'Colher de servir', 'Colher de sobremesa', 'Colher de sopa', 'Concha',
            'Copo americano', 'Copo de cafezinho', 'Copo de requeijão', 'Copo grande',
            'Copo médio', 'Copo tulipa', 'Costela', 'Coxa', 'Cumbuca', 'Dose',
            'Escumadeira', 'Espetinho', 'Espeto', 'Espiga', 'Fatia', 'Filé', 'Folha',
            'Garfada', 'Garrafa', 'Gomo', 'Lata', 'Maço', 'Metade', 'Pacote', 'Pedaço',
            'Pegador', 'Peito', 'Pescoço', 'Pires', 'Ponta de faca', 'Porção', 'Posta',
            'Pote', 'Prato de sobremesa', 'Prato fundo', 'Prato raso', 'Punhado', 'Ramo',
            'Rodela', 'Sachê', 'Saco', 'Sobrecoxa', 'Tablete', 'Taça', 'Tigela',
            'Unidade', 'Unidade pequena', 'Xícara de café', 'Xícara de chá',
        ];

        $nomes = [];
        foreach (require database_path('medidas_pof.php') as $medidas) {
            foreach ($medidas as [$nome, $gramas]) {
                // Garrafa e lata carregam o volume no nome ("Garrafa de 500 ml"):
                // o volume sai daqui, senão a lista viraria uma enumeração de
                // tamanhos. "Colher de sopa" e "Prato de sobremesa" ficam
                // inteiros — só o que vem seguido de número é volume.
                $nomes[preg_replace('/ de \d.*$/u', '', $nome)] = true;
            }
        }

        $nomes = array_keys($nomes);
        sort($nomes);

        $this->assertSame($esperados, $nomes);
    }

    public function test_volume_de_garrafa_e_lata_aparece_no_nome(): void
    {
        // "1 lata" de refrigerante não diz nada: lata de 350 ml e de 473 ml são
        // coisas diferentes, e a fonte distingue as duas.
        $nomes = [];
        foreach (require database_path('medidas_pof.php') as $medidas) {
            foreach ($medidas as [$nome, $gramas]) {
                $nomes[$nome] = true;
            }
        }

        $this->assertArrayHasKey('Lata de 350 ml', $nomes);
        $this->assertArrayHasKey('Garrafa de 600 ml', $nomes);
        $this->assertArrayHasKey('Garrafa de 2 L', $nomes);
        // Grama e quilo não são medida caseira: o app já tem campo de grama, e
        // "2 gramas de arroz" no meio da lista é ruído.
        $this->assertArrayNotHasKey('Grama', $nomes);
        $this->assertArrayNotHasKey('Quilo', $nomes);
    }

    public function test_resemear_apaga_medida_que_saiu_do_arquivo(): void
    {
        $this->seed(AlimentoPofSeeder::class);
        $feijao = Food::where('codigo_pof', 6303102)->first();

        // Simula uma medida errada que já foi pro banco numa versão anterior
        // do arquivo. O deploy roda os seeders a cada subida, então é assim que
        // a correção precisa chegar na produção — upsert sozinho só sabe criar.
        $intrusa = FoodMeasure::create(['food_id' => $feijao->id, 'nome' => 'Balde', 'gramas' => 9000]);

        $this->seed(AlimentoPofSeeder::class);

        $this->assertNull(FoodMeasure::find($intrusa->id));
        // E o que está no arquivo continua lá, com o peso certo.
        $this->assertSame(140.0, (float) $feijao->medidas()->where('nome', 'Concha')->value('gramas'));
    }

    public function test_fonte_nao_da_dois_pesos_pra_mesma_medida(): void
    {
        // Isso NÃO dá pra testar no banco: (food_id, nome) é único, então duas
        // linhas conflitantes viram uma só e a última cala a primeira em
        // silêncio. Foi assim que, na versão anterior desta tabela, "1 unidade
        // de maçã" virou 320 g (o peso do prato, não da fruta) sem ninguém ver.
        // Por isso o teste olha o arquivo de origem, que é onde dá pra enxergar.
        $conflitos = [];

        foreach (require database_path('medidas_pof.php') as $chave => $medidas) {
            $nomes = array_column($medidas, 0);
            foreach (array_unique(array_diff_assoc($nomes, array_unique($nomes))) as $repetido) {
                $conflitos[] = "{$chave}: {$repetido}";
            }
        }

        $this->assertSame([], $conflitos, 'medida repetida: '.implode(', ', $conflitos));
    }

    public function test_personal_ve_a_medida_caseira_e_nao_so_a_grama(): void
    {
        $this->seed(AlimentoPofSeeder::class);
        [$student, $headers] = $this->cenario();
        $feijao = Food::where('codigo_pof', 6303102)->first();
        $concha = $feijao->medidas()->where('nome', 'Concha')->first();

        $this->postJson("/portal/{$student->invite_token}/nutricao/refeicoes", [
            'momento' => 'almoco',
            'alimentos' => [['food_id' => $feijao->id, 'medida_id' => $concha->id, 'medida_qtd' => 2]],
        ])->assertCreated();

        // É o personal quem compara os dias, e "2 conchas ontem, 4 hoje" é uma
        // leitura que ele faz de cabeça; "280 g" e "560 g", não.
        $this->getJson("/alunos/{$student->id}/nutricao", $headers)
            ->assertOk()
            ->assertJsonPath('refeicoes.0.alimentos.0.medida', '2 conchas')
            ->assertJsonPath('refeicoes.0.alimentos.0.quantidade_g', 280);
    }
}
