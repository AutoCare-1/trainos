<?php

namespace Tests\Unit;

use App\Models\Exercise;
use App\Models\Professional;
use App\Models\SessionEntry;
use App\Models\Student;
use App\Models\TrainingSession;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Support\Estagnacao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recortes de Estagnacao::compararUltimasSessoes.
 *
 * A CTE roda em GET /alunos (dashboard, a cada carregamento) e de novo em
 * GET /alunos/{id} — onde antes calculava TODOS os alunos do personal pra
 * filtrar um só em PHP, e sem nenhum recorte de tempo varria session_entries
 * inteiro.
 */
class EstagnacaoEscopoTest extends TestCase
{
    use RefreshDatabase;

    private function criarAlunoComSessoes(Professional $professional, array $sessoes): Student
    {
        $student = Student::create([
            'professional_id' => $professional->id,
            'name' => 'Aluno '.uniqid(),
            'invite_token' => uniqid('token'),
        ]);
        $exercise = Exercise::create(['name' => 'Supino '.uniqid(), 'muscle_group' => 'peito']);
        $workout = Workout::create([
            'professional_id' => $professional->id, 'student_id' => $student->id,
            'name' => 'Treino A', 'status' => 'sent', 'sent_at' => now()->subDays(30),
        ]);
        $we = WorkoutExercise::create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
            'order_index' => 0, 'sets' => 3, 'reps' => '10',
        ]);

        foreach ($sessoes as $s) {
            $finishedAt = now()->subDays($s['diasAtras']);
            $session = TrainingSession::create([
                'workout_id' => $workout->id, 'student_id' => $student->id, 'status' => 'completed',
                'started_at' => $finishedAt->copy()->subMinutes(40), 'finished_at' => $finishedAt,
            ]);
            SessionEntry::create([
                'training_session_id' => $session->id, 'workout_exercise_id' => $we->id,
                'set_number' => 1, 'reps_done' => 10, 'load_kg_done' => $s['carga'],
            ]);
        }

        return $student;
    }

    private function personal(): Professional
    {
        return Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
    }

    /** 4 sessões recentes sem progresso — o caso que gera alerta. */
    private const SESSOES_ESTAGNADAS = [
        ['diasAtras' => 21, 'carga' => 60],
        ['diasAtras' => 14, 'carga' => 55],
        ['diasAtras' => 7, 'carga' => 58],
        ['diasAtras' => 0, 'carga' => 60],
    ];

    public function test_filtra_por_aluno_direto_na_query(): void
    {
        $professional = $this->personal();
        $alvo = $this->criarAlunoComSessoes($professional, self::SESSOES_ESTAGNADAS);
        $this->criarAlunoComSessoes($professional, self::SESSOES_ESTAGNADAS);

        $todos = Estagnacao::compararUltimasSessoes($professional->id);
        $soDoAlvo = Estagnacao::compararUltimasSessoes($professional->id, studentId: $alvo->id);

        $this->assertCount(2, $todos, 'sem studentId continua trazendo os dois alunos');
        $this->assertCount(1, $soDoAlvo);
        $this->assertSame($alvo->id, $soDoAlvo[0]['student_id']);
    }

    public function test_historico_antigo_fica_fora_da_janela(): void
    {
        $professional = $this->personal();
        // Mesmas 4 sessões, mas de mais de um ano atrás: "não superou a carga"
        // comparado com treino tão antigo não diz nada sobre estagnação hoje.
        $this->criarAlunoComSessoes($professional, [
            ['diasAtras' => 420, 'carga' => 60],
            ['diasAtras' => 410, 'carga' => 55],
            ['diasAtras' => 400, 'carga' => 58],
            ['diasAtras' => 390, 'carga' => 60],
        ]);

        $this->assertSame([], Estagnacao::compararUltimasSessoes($professional->id));
    }

    public function test_sessoes_dentro_da_janela_continuam_valendo(): void
    {
        $professional = $this->personal();
        $this->criarAlunoComSessoes($professional, self::SESSOES_ESTAGNADAS);

        $this->assertCount(1, Estagnacao::compararUltimasSessoes($professional->id));
    }
}
