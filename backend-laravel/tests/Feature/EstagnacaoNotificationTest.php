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
 * Item 5/6 da revisão: garante que o push estagnacao_detectada usa a MESMA
 * fonte de verdade (App\Support\Estagnacao) que o alerta in-app já existente
 * em GET /alunos/:id — não pode haver veredito divergente entre os dois.
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

    public function test_push_e_alerta_in_app_concordam_sobre_estagnacao(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-27 12:00:00'));

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
            'name' => 'Treino A', 'status' => 'sent', 'sent_at' => now()->subDays(10),
        ]);
        $workoutExercise = WorkoutExercise::create([
            'workout_id' => $workout->id, 'exercise_id' => $exercise->id,
            'order_index' => 0, 'sets' => 3, 'reps' => '10',
        ]);

        // Duas sessões concluídas, carga igual nas duas — estagnação (ultima <= anterior).
        foreach ([now()->subDays(5), now()->subHours(2)] as $finishedAt) {
            $session = TrainingSession::create([
                'workout_id' => $workout->id, 'student_id' => $student->id, 'status' => 'completed',
                'started_at' => $finishedAt->copy()->subMinutes(40), 'finished_at' => $finishedAt,
            ]);
            SessionEntry::create([
                'training_session_id' => $session->id, 'workout_exercise_id' => $workoutExercise->id,
                'set_number' => 1, 'reps_done' => 10, 'load_kg_done' => 60,
            ]);
        }

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
}
