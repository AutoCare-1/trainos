<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\NotificationLog;
use App\Models\Professional;
use App\Models\SessionEntry;
use App\Models\Student;
use App\Models\TrainingSession;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Notifications\PushNotification;
use Database\Seeders\NotificationTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Item 5/6 de uma revisão externa: garante que o push estagnacao_detectada
 * usa a MESMA fonte de verdade (App\Support\Estagnacao) que o alerta in-app
 * já existente em GET /alunos/:id — não pode haver veredito divergente entre
 * os dois. Item 5 de uma segunda rodada: a janela de comparação foi ampliada
 * de 2 pra 4 sessões (compara a mais recente com a de 4 sessões atrás, não
 * mais com a imediatamente anterior) — os testes cobrem a concordância
 * push/in-app e o falso positivo que motivou a mudança (pico isolado no meio
 * do caminho não deve mais distorcer o veredito).
 */
class EstagnacaoNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NotificationTypesSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function autenticar(Professional $professional): array
    {
        $token = auth('api')->login($professional);

        return ['Authorization' => "Bearer {$token}"];
    }

    /** @return array{0: Professional, 1: Student, 2: Exercise, 3: WorkoutExercise} */
    private function criarAlunoComExercicio(): array
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
        $exercise = Exercise::create(['name' => 'Supino reto', 'muscle_group' => 'peito']);
        $workout = Workout::create([
            'professional_id' => $professional->id, 'student_id' => $student->id,
            'name' => 'Treino A', 'status' => 'sent', 'sent_at' => now()->subDays(30),
        ]);
        $workoutExercise = WorkoutExercise::create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
            'order_index' => 0, 'sets' => 3, 'reps' => '10',
        ]);

        return [$professional, $student, $exercise, $workoutExercise];
    }

    /** @param  array<int, array{diasAtras: int, carga: float}>  $sessoes */
    private function registrarSessoes(Student $student, string $workoutId, string $workoutExerciseId, array $sessoes): void
    {
        foreach ($sessoes as $s) {
            $finishedAt = now()->subDays($s['diasAtras']);
            $session = TrainingSession::create([
                'workout_id' => $workoutId, 'student_id' => $student->id, 'status' => 'completed',
                'started_at' => $finishedAt->copy()->subMinutes(40), 'finished_at' => $finishedAt,
            ]);
            SessionEntry::create([
                'training_session_id' => $session->id, 'workout_exercise_id' => $workoutExerciseId,
                'set_number' => 1, 'reps_done' => 10, 'load_kg_done' => $s['carga'],
            ]);
        }
    }

    public function test_push_e_alerta_in_app_concordam_sobre_estagnacao(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-27 12:00:00'));

        [$professional, $student, $exercise, $we] = $this->criarAlunoComExercicio();

        // 4 sessões (janela padrão): hoje (60) não supera a de 4 sessões atrás (60) — estagnado.
        $this->registrarSessoes($student, $we->workout_id, $we->id, [
            ['diasAtras' => 21, 'carga' => 60],
            ['diasAtras' => 14, 'carga' => 55],
            ['diasAtras' => 7, 'carga' => 58],
            ['diasAtras' => 0, 'carga' => 60],
        ]);

        // 1) O alerta in-app (perfil do aluno visto pelo personal) detecta a estagnação.
        $headers = $this->autenticar($professional);
        $resposta = $this->getJson("/alunos/{$student->id}", $headers)->assertOk();
        $alertas = $resposta->json('alertasEstagnacao');
        $this->assertCount(1, $alertas, 'o alerta in-app deveria detectar a estagnação em Supino reto');
        $this->assertSame('Supino reto', $alertas[0]['exercise_name']);

        // 2) A notificação push detecta EXATAMENTE o mesmo caso.
        Artisan::call('notifications:process');
        Notification::assertSentTo($student, PushNotification::class);
        $this->assertSame(
            1,
            NotificationLog::where('tipo_chave', 'estagnacao_detectada')
                ->where('student_id', $student->id)
                ->where('contexto', $exercise->id)
                ->count()
        );
    }

    public function test_pico_isolado_no_meio_do_caminho_nao_dispara_mais_falso_positivo(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-27 12:00:00'));

        [, $student, , $we] = $this->criarAlunoComExercicio();

        // Cenário exato descrito na revisão: um pico isolado de desempenho
        // (70, "dia excepcional") fazia a sessão seguinte parecer estagnada
        // com a lógica antiga (última vs penúltima: 60 <= 70 → estagnado),
        // mesmo o aluno tendo evoluído de verdade desde 4 sessões atrás
        // (50 -> 60). Com janela de 4 (compara com 4 sessões atrás, ignora o
        // pico intermediário), 60 > 50 → progrediu, não estagnado.
        $this->registrarSessoes($student, $we->workout_id, $we->id, [
            ['diasAtras' => 21, 'carga' => 50], // referência (4 sessões atrás)
            ['diasAtras' => 14, 'carga' => 70], // pico isolado (dia excepcional)
            ['diasAtras' => 7, 'carga' => 62],
            ['diasAtras' => 0, 'carga' => 60], // hoje: acima da referência de 4 sessões atrás (50)
        ]);

        Artisan::call('notifications:process');

        $this->assertSame(0, NotificationLog::where('tipo_chave', 'estagnacao_detectada')->count());
    }

    public function test_estagnacao_real_ao_longo_da_janela_ainda_dispara(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-27 12:00:00'));

        [, $student, , $we] = $this->criarAlunoComExercicio();

        // Carga igual do início ao fim da janela — estagnação real, não é falso positivo.
        $this->registrarSessoes($student, $we->workout_id, $we->id, [
            ['diasAtras' => 21, 'carga' => 60],
            ['diasAtras' => 14, 'carga' => 58],
            ['diasAtras' => 7, 'carga' => 59],
            ['diasAtras' => 0, 'carga' => 60],
        ]);

        Artisan::call('notifications:process');

        $this->assertSame(1, NotificationLog::where('tipo_chave', 'estagnacao_detectada')->count());
    }

    public function test_menos_de_4_sessoes_nao_avalia_estagnacao(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-27 12:00:00'));

        [, $student, , $we] = $this->criarAlunoComExercicio();

        // Só 2 sessões — histórico curto demais pra janela de 4, não avalia.
        $this->registrarSessoes($student, $we->workout_id, $we->id, [
            ['diasAtras' => 7, 'carga' => 60],
            ['diasAtras' => 0, 'carga' => 60],
        ]);

        Artisan::call('notifications:process');

        $this->assertSame(0, NotificationLog::where('tipo_chave', 'estagnacao_detectada')->count());
    }
}
