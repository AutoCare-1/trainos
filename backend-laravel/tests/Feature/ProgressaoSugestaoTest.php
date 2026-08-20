<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Professional;
use App\Models\SessionEntry;
use App\Models\Student;
use App\Models\TrainingSession;
use App\Support\Progressao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cobre App\Support\Progressao (via GET /alunos/:id/progressao) nos três
 * cenários da regra de disparo: bateu o topo da faixa em todas as séries,
 * completou parcialmente, e ficou abaixo do mínimo em mais de uma série.
 *
 * Não cobre estagnação — aquilo é do App\Support\Estagnacao e continua
 * intocado; aqui só se confirma que um exercício já estagnado nunca recebe
 * sugestão de aumento.
 */
class ProgressaoSugestaoTest extends TestCase
{
    use RefreshDatabase;

    private array $headers;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $token = auth('api')->login($professional);
        $this->headers = ['Authorization' => "Bearer {$token}"];

        $studentId = $this->postJson('/alunos', ['name' => 'Aluno Teste'], $this->headers)
            ->assertCreated()
            ->json('student.id');
        $this->student = Student::find($studentId);
    }

    /**
     * Cria um treino com um exercício e registra uma sessão concluída com as
     * séries informadas (cada item = [reps_done, load_kg_done]).
     */
    private function sessaoConcluida(
        string $equipment,
        string $repsPrescritas,
        int $seriesPrescritas,
        array $series,
        ?Exercise $exercise = null,
        int $diasAtras = 0
    ): Exercise {
        $exercise ??= Exercise::create([
            'name' => 'Supino reto '.uniqid(),
            'muscle_group' => 'Peito',
            'equipment' => $equipment,
        ]);

        $workoutId = $this->postJson('/treinos', [
            'student_id' => $this->student->id,
            'name' => 'Treino A',
            'items' => [
                ['exercise_id' => $exercise->id, 'sets' => $seriesPrescritas, 'reps' => $repsPrescritas],
            ],
        ], $this->headers)->assertCreated()->json('workout.id');

        $workoutExerciseId = $this->getJson("/treinos/{$workoutId}", $this->headers)->json('exercises.0.id');

        $session = TrainingSession::create([
            'workout_id' => $workoutId,
            'student_id' => $this->student->id,
            'status' => 'completed',
            'finished_at' => now()->subDays($diasAtras),
        ]);

        foreach ($series as $i => [$reps, $carga]) {
            SessionEntry::create([
                'training_session_id' => $session->id,
                'workout_exercise_id' => $workoutExerciseId,
                'set_number' => $i + 1,
                'reps_done' => $reps,
                'load_kg_done' => $carga,
            ]);
        }

        return $exercise;
    }

    private function sugestaoDe(Exercise $exercise): ?array
    {
        return $this->getJson("/alunos/{$this->student->id}/progressao", $this->headers)
            ->assertOk()
            ->json("sugestoes.{$exercise->id}");
    }

    public function test_bateu_o_topo_da_faixa_em_todas_as_series_sugere_aumentar_carga(): void
    {
        $exercise = $this->sessaoConcluida('Barra', '10-12', 3, [[12, 40], [12, 40], [12, 40]]);

        $sugestao = $this->sugestaoDe($exercise);

        $this->assertSame('aumentar_carga', $sugestao['acao']);
        // Barra: menor mudança praticável é 2,5kg (par de anilhas de 1,25).
        // 2,5% de 40kg = 1kg, que não dá um passo inteiro -> sobe 1 passo.
        $this->assertSame(2.5, (float) $sugestao['delta_kg']);
        $this->assertSame(42.5, (float) $sugestao['carga_sugerida']);
        $this->assertSame(40.0, (float) $sugestao['carga_anterior']);
        $this->assertFalse($sugestao['estagnado']);
    }

    public function test_incremento_respeita_o_equipamento_e_o_alvo_percentual(): void
    {
        // Máquina sobe de placa em placa (5kg), mesmo que 2,5% de 60kg seja 1,5kg.
        $maquina = $this->sessaoConcluida('Máquina', '8-10', 2, [[10, 60], [10, 60]]);
        $this->assertSame(5.0, (float) $this->sugestaoDe($maquina)['delta_kg']);

        // Carga alta na barra: 2,5% de 200kg = 5kg = 2 passos de 2,5kg.
        $barra = $this->sessaoConcluida('Barra', '5', 2, [[5, 200], [5, 200]]);
        $this->assertSame(5.0, (float) $this->sugestaoDe($barra)['delta_kg']);
    }

    public function test_elastico_sugere_repeticao_em_vez_de_carga(): void
    {
        // Elástico não tem kg pra somar: sugerir "+2,5 kg num elástico" seria um
        // número que o aluno não tem como executar. Vale pro resto do material
        // sem carga somável (TRX, bola, corda, cardio) — ver Progressao::INCREMENTOS.
        $exercise = $this->sessaoConcluida('Elástico', '12-15', 2, [[15, null], [15, null]]);

        $sugestao = $this->sugestaoDe($exercise);

        $this->assertSame('aumentar_reps', $sugestao['acao']);
        $this->assertSame(0.0, (float) $sugestao['delta_kg']);
    }

    public function test_todo_equipamento_da_biblioteca_tem_incremento_intencional(): void
    {
        // Trava contra regressão silenciosa: um equipamento novo no seeder que
        // ninguém mapeou em INCREMENTOS cai no padrão de 2,5 kg sem erro nenhum,
        // e o personal só descobre vendo uma sugestão absurda na tela.
        // "Equipamento" é o balde genérico legítimo e fica de fora da checagem.
        $this->seed(\Database\Seeders\ExerciseSeeder::class);
        $this->seed(\Database\Seeders\ExercicioBibliotecaAmpliadaSeeder::class);

        $mapeados = (new \ReflectionClass(Progressao::class))->getConstant('INCREMENTOS');

        $semMapeamento = Exercise::query()
            ->whereNotNull('equipment')
            ->where('equipment', '!=', 'Equipamento')
            ->pluck('equipment')
            ->unique()
            ->reject(fn ($e) => array_key_exists(Str::ascii(mb_strtolower(trim($e))), $mapeados))
            ->values()
            ->all();

        $this->assertSame([], $semMapeamento, 'Equipamento sem incremento definido em Progressao::INCREMENTOS');
    }

    public function test_peso_corporal_sugere_mais_uma_repeticao_em_vez_de_carga(): void
    {
        $exercise = $this->sessaoConcluida('Peso corporal', '10-12', 3, [[12, null], [12, null], [12, null]]);

        $sugestao = $this->sugestaoDe($exercise);

        $this->assertSame('aumentar_reps', $sugestao['acao']);
        $this->assertSame('11-13', $sugestao['reps_sugeridas']);
        $this->assertSame(0.0, (float) $sugestao['delta_kg']);
    }

    public function test_completou_parcialmente_sugere_manter_a_carga(): void
    {
        // Terceira série abaixo do mínimo (8 < 10) — uma só.
        $exercise = $this->sessaoConcluida('Barra', '10-12', 3, [[12, 40], [11, 40], [8, 40]]);

        $sugestao = $this->sugestaoDe($exercise);

        $this->assertSame('manter', $sugestao['acao']);
        $this->assertSame(0.0, (float) $sugestao['delta_kg']);
        $this->assertSame(40.0, (float) $sugestao['carga_sugerida']);
        // A justificativa precisa dizer o que aconteceu de verdade — ficar
        // abaixo do mínimo não é "completou dentro da faixa".
        $this->assertSame('Ficou abaixo da faixa prescrita em uma série', $sugestao['motivo']);
    }

    public function test_ficou_dentro_da_faixa_sem_bater_o_topo_sugere_manter(): void
    {
        $exercise = $this->sessaoConcluida('Barra', '10-12', 3, [[11, 40], [10, 40], [10, 40]]);

        $sugestao = $this->sugestaoDe($exercise);

        $this->assertSame('manter', $sugestao['acao']);
        $this->assertSame('Completou dentro da faixa, mas ainda não no topo', $sugestao['motivo']);
    }

    public function test_nao_completou_todas_as_series_sugere_manter(): void
    {
        // Prescritas 3 séries, registrou só 2 (parou no meio) — mesmo batendo o
        // topo nas duas, não dá pra dizer que a carga está dominada.
        $exercise = $this->sessaoConcluida('Barra', '10-12', 3, [[12, 40], [12, 40]]);

        $sugestao = $this->sugestaoDe($exercise);

        $this->assertSame('manter', $sugestao['acao']);
        $this->assertSame('Não completou todas as séries prescritas', $sugestao['motivo']);
    }

    public function test_abaixo_do_minimo_em_mais_de_uma_serie_sugere_reduzir_levemente(): void
    {
        $exercise = $this->sessaoConcluida('Barra', '10-12', 3, [[9, 40], [7, 40], [6, 40]]);

        $sugestao = $this->sugestaoDe($exercise);

        $this->assertSame('reduzir', $sugestao['acao']);
        $this->assertSame(-2.5, (float) $sugestao['delta_kg']);
        $this->assertSame(37.5, (float) $sugestao['carga_sugerida']);
    }

    public function test_reduzir_nunca_zera_a_carga_quando_ja_esta_no_incremento_minimo(): void
    {
        $exercise = $this->sessaoConcluida('Barra', '10-12', 2, [[4, 2.5], [3, 2.5]]);

        $sugestao = $this->sugestaoDe($exercise);

        $this->assertSame('manter', $sugestao['acao']);
        $this->assertSame(2.5, (float) $sugestao['carga_sugerida']);
    }

    public function test_exercicio_ja_estagnado_nao_recebe_sugestao_de_aumento(): void
    {
        // 4 sessões (janela do Estagnacao) fechando na mesma carga: o alerta de
        // estagnação dispara, então mesmo com a última sessão batendo o topo da
        // faixa a sugestão não pode ser de subir.
        $exercise = null;
        foreach ([3, 2, 1, 0] as $diasAtras) {
            $exercise = $this->sessaoConcluida(
                'Barra',
                '10-12',
                2,
                [[12, 50], [12, 50]],
                $exercise,
                $diasAtras
            );
        }

        $sugestao = $this->sugestaoDe($exercise);

        $this->assertTrue($sugestao['estagnado']);
        $this->assertNotSame('aumentar_carga', $sugestao['acao']);
        $this->assertSame('reduzir', $sugestao['acao']);
    }

    public function test_reps_prescritas_sem_numero_nao_geram_sugestao(): void
    {
        $exercise = $this->sessaoConcluida('Barra', 'até a falha', 2, [[15, 30], [14, 30]]);

        $this->assertNull($this->sugestaoDe($exercise));
    }

    public function test_aluno_de_outro_profissional_nao_e_acessivel(): void
    {
        $outro = Professional::create([
            'name' => 'Outro Personal',
            'email' => uniqid('outro').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $headersOutro = ['Authorization' => 'Bearer '.auth('api')->login($outro)];

        $this->getJson("/alunos/{$this->student->id}/progressao", $headersOutro)->assertNotFound();
    }
}
