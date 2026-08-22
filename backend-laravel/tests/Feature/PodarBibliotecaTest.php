<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\ExerciseMediaOverride;
use App\Models\Professional;
use App\Models\Student;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Poda da biblioteca de exercícios (646 -> 402).
 *
 * Quem já é instalação NOVA não precisa deste comando: o seeder já foi podado,
 * então nasce com 402. O comando existe pros bancos que já rodavam com 646 —
 * por isso os testes montam o cenário legado à mão, criando os exercícios
 * condenados, em vez de esperar que o seeder os produza.
 *
 * O que estes testes protegem: o comando apaga dados, e três tabelas que
 * apontam pra exercises usam CASCADE — sem as travas, remover um exercício
 * levaria junto, sem erro nenhum, o vídeo que um personal gravou e o histórico
 * de análise de forma de um aluno.
 */
class PodarBibliotecaTest extends TestCase
{
    use RefreshDatabase;

    /** @return string[] */
    private function listaDeCorte(): array
    {
        return require database_path('biblioteca_podada.php');
    }

    /** Recria um exercício que existia antes da poda (cenário de banco legado). */
    private function exercicioLegado(string $nome, array $attrs = []): Exercise
    {
        return Exercise::create(array_merge([
            'name' => $nome,
            'muscle_group' => 'Peito',
            'equipment' => 'Barra',
        ], $attrs));
    }

    private function personal(): Professional
    {
        return Professional::create([
            'name' => 'Personal',
            'email' => uniqid('p').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
    }

    // ---- A lista ---------------------------------------------------------

    public function test_lista_de_corte_nao_tem_nome_repetido(): void
    {
        $nomes = $this->listaDeCorte();

        $this->assertNotEmpty($nomes);
        $this->assertSame(count($nomes), count(array_unique($nomes)), 'nome repetido na lista de corte');
    }

    public function test_o_seeder_nao_recria_o_que_foi_podado(): void
    {
        // A trava que importa pra instalação nova: se um nome cortado voltasse
        // pro seeder, a biblioteca voltaria a crescer sozinha e a curadoria
        // teria sido em vão.
        $this->seed(\Database\Seeders\ExerciseSeeder::class);
        $this->seed(\Database\Seeders\ExercicioBibliotecaAmpliadaSeeder::class);

        $recriados = Exercise::whereIn('name', $this->listaDeCorte())->pluck('name')->all();

        $this->assertSame([], $recriados);
    }

    public function test_instalacao_nova_ja_nasce_com_a_biblioteca_podada(): void
    {
        $this->seed(\Database\Seeders\ExerciseSeeder::class);
        $this->seed(\Database\Seeders\ExercicioBibliotecaAmpliadaSeeder::class);

        $this->assertSame(402, Exercise::count());
        // As 75 fotos reais de acervo (wger, CC-BY-SA) têm que sobreviver.
        $this->assertSame(75, Exercise::whereNotNull('image_url')->count());
    }

    // ---- O comando -------------------------------------------------------

    public function test_nao_apaga_sem_dry_run_nem_force(): void
    {
        $this->artisan('exercicios:podar-biblioteca')->assertFailed();
    }

    public function test_dry_run_nao_apaga_nada(): void
    {
        $this->exercicioLegado($this->listaDeCorte()[0]);

        $this->artisan('exercicios:podar-biblioteca', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(1, Exercise::count());
    }

    public function test_remove_o_que_esta_na_lista(): void
    {
        $condenado = $this->exercicioLegado($this->listaDeCorte()[0]);
        $sobrevivente = $this->exercicioLegado('Exercício que não está na lista');

        $this->artisan('exercicios:podar-biblioteca', ['--force' => true])->assertSuccessful();

        $this->assertNull($condenado->fresh());
        $this->assertNotNull($sobrevivente->fresh());
    }

    // ---- As travas -------------------------------------------------------

    public function test_preserva_exercicio_usado_num_treino(): void
    {
        $condenado = $this->exercicioLegado($this->listaDeCorte()[0]);
        $professional = $this->personal();
        $student = Student::create([
            'professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid('t'),
        ]);
        $workout = Workout::create([
            'student_id' => $student->id, 'professional_id' => $professional->id, 'name' => 'Treino A',
        ]);
        WorkoutExercise::create([
            'workout_id' => $workout->id, 'exercise_id' => $condenado->id,
            'sets' => 3, 'reps' => '10', 'position' => 1,
        ]);

        $this->artisan('exercicios:podar-biblioteca', ['--force' => true])->assertSuccessful();

        $this->assertNotNull($condenado->fresh(), 'exercício prescrito num treino não pode sumir');
    }

    public function test_preserva_exercicio_com_video_proprio_do_personal(): void
    {
        // exercise_media_overrides é CASCADE: sem a trava, apagar o exercício
        // apagaria junto o vídeo que o personal gravou, em silêncio.
        $condenado = $this->exercicioLegado($this->listaDeCorte()[1]);
        ExerciseMediaOverride::create([
            'professional_id' => $this->personal()->id,
            'exercise_id' => $condenado->id,
            'video_url' => '/uploads/exercise-videos/meu.mp4',
        ]);

        $this->artisan('exercicios:podar-biblioteca', ['--force' => true])->assertSuccessful();

        $this->assertNotNull($condenado->fresh(), 'exercício com vídeo do personal não pode sumir');
        $this->assertSame(1, DB::table('exercise_media_overrides')->count(), 'o vídeo do personal não pode ser apagado');
    }

    public function test_preserva_exercicio_que_ja_tem_imagem_ou_video(): void
    {
        // Se a curadoria errar e listar algo que já ganhou mídia, a mídia manda:
        // alguém investiu naquele exercício (foto de acervo ou demonstração
        // gerada), então ele não é candidato a corte.
        $comFoto = $this->exercicioLegado($this->listaDeCorte()[2], ['image_url' => '/exercise-photos/x.png']);
        $comVideo = $this->exercicioLegado($this->listaDeCorte()[3], ['video_url' => '/uploads/exercise-demos/y.mp4']);

        $this->artisan('exercicios:podar-biblioteca', ['--force' => true])->assertSuccessful();

        $this->assertNotNull($comFoto->fresh());
        $this->assertNotNull($comVideo->fresh());
    }
}
