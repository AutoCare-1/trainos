<?php

namespace Tests\Feature;

use App\Models\BodyPhoto;
use App\Models\Professional;
use App\Models\SessionEntry;
use App\Models\Student;
use App\Models\TrainingSession;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Notifications\PushNotification;
use App\Notifications\Rules\NotificacaoCandidato;
use Database\Seeders\NotificationTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Item 9 da revisão: tipos que revelam frequência de treino, saúde ou nome de
 * aluno usam texto genérico no payload de push (a tela de bloqueio de um
 * aparelho pode ser vista por terceiros) — o detalhe completo só aparece
 * depois de abrir o app pelo link da notificação.
 */
class PayloadPrivacidadeNotificationTest extends TestCase
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

    public function test_avaliacao_recebida_nao_expoe_nome_do_aluno_no_payload(): void
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
            'name' => 'Nome Que Não Pode Vazar',
            'invite_token' => uniqid('token'),
            'onboarding_completed_at' => now(),
        ]);

        Artisan::call('notifications:process');

        Notification::assertSentTo(
            $professional,
            function (PushNotification $notification) {
                $this->assertStringNotContainsString('Não Pode Vazar', $notification->corpo);
                $this->assertSame(NotificacaoCandidato::CORPO_PERSONAL_GENERICO, $notification->corpo);

                return true;
            }
        );
    }

    public function test_sem_treinar_dias_nao_expoe_frequencia_no_payload(): void
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
        Workout::create([
            'professional_id' => $professional->id, 'student_id' => $student->id,
            'name' => 'Treino A', 'status' => 'sent', 'sent_at' => now()->subDays(7),
        ]);
        DB::table('students')->where('id', $student->id)->update(['created_at' => now()->subDays(20)]);

        Artisan::call('notifications:process');

        Notification::assertSentTo(
            $student,
            function (PushNotification $notification) {
                $this->assertStringNotContainsString('sem treino', mb_strtolower($notification->corpo));
                $this->assertSame(NotificacaoCandidato::CORPO_ALUNO_GENERICO, $notification->corpo);

                return true;
            }
        );
    }

    public function test_novo_treino_enviado_continua_com_texto_rico(): void
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
        Workout::create([
            'professional_id' => $professional->id, 'student_id' => $student->id,
            'name' => 'Treino de Pernas', 'status' => 'sent', 'sent_at' => now(),
        ]);

        Artisan::call('notifications:process');

        // Tipo sem informação sensível — não precisa (e não deveria) ficar genérico.
        Notification::assertSentTo(
            $student,
            function (PushNotification $notification) {
                $this->assertStringContainsString('Treino de Pernas', $notification->corpo);

                return true;
            }
        );
    }

    public function test_streak_em_risco_nao_expoe_a_contagem_de_dias_no_payload(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-27 19:00:00')); // depois de hora_sem_treinar_hoje (18h)

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
        $workout = Workout::create([
            'professional_id' => $professional->id, 'student_id' => $student->id,
            'name' => 'Treino A', 'status' => 'sent', 'sent_at' => now()->subDays(10),
        ]);
        $exercise = \App\Models\Exercise::firstOrCreate(['name' => 'Agachamento'], ['muscle_group' => 'pernas']);
        $we = WorkoutExercise::create(['workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'order_index' => 0, 'sets' => 3, 'reps' => '10']);

        // 3 dias consecutivos terminando ontem — streak de 3 dias, ainda não treinou hoje.
        for ($diasAtras = 1; $diasAtras <= 3; $diasAtras++) {
            $session = TrainingSession::create([
                'workout_id' => $workout->id, 'student_id' => $student->id, 'status' => 'completed',
                'started_at' => now()->subDays($diasAtras)->setTime(10, 0),
                'finished_at' => now()->subDays($diasAtras)->setTime(10, 30),
            ]);
            SessionEntry::create(['training_session_id' => $session->id, 'workout_exercise_id' => $we->id, 'set_number' => 1, 'load_kg_done' => 20]);
        }

        Artisan::call('notifications:process');

        Notification::assertSentTo(
            $student,
            function (PushNotification $notification) {
                $this->assertStringNotContainsString('sequência', mb_strtolower($notification->titulo));
                $this->assertSame(NotificacaoCandidato::CORPO_ALUNO_GENERICO, $notification->corpo);

                return true;
            }
        );
    }

    public function test_parabens_fim_de_semana_nao_expoe_a_contagem_de_treinos_no_payload(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-27 09:00:00')); // segunda-feira, depois das 8h

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
        $workout = Workout::create([
            'professional_id' => $professional->id, 'student_id' => $student->id,
            'name' => 'Treino A', 'status' => 'sent', 'sent_at' => now()->subDays(10),
        ]);
        $exercise = \App\Models\Exercise::firstOrCreate(['name' => 'Supino'], ['muscle_group' => 'peito']);
        $we = WorkoutExercise::create(['workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'order_index' => 0, 'sets' => 3, 'reps' => '10']);

        // 3 sessões concluídas nos últimos 7 dias (limiar padrão) — todas antes de hoje.
        for ($diasAtras = 1; $diasAtras <= 3; $diasAtras++) {
            $session = TrainingSession::create([
                'workout_id' => $workout->id, 'student_id' => $student->id, 'status' => 'completed',
                'started_at' => now()->subDays($diasAtras)->setTime(10, 0),
                'finished_at' => now()->subDays($diasAtras)->setTime(10, 30),
            ]);
            SessionEntry::create(['training_session_id' => $session->id, 'workout_exercise_id' => $we->id, 'set_number' => 1, 'load_kg_done' => 20]);
        }

        Artisan::call('notifications:process');

        Notification::assertSentTo(
            $student,
            function (PushNotification $notification) {
                $this->assertStringNotContainsString('3 vezes', $notification->corpo);
                $this->assertSame(NotificacaoCandidato::CORPO_ALUNO_GENERICO, $notification->corpo);

                return true;
            }
        );
    }

    /**
     * Item 2b de uma segunda revisão: comentário sobre foto de evolução
     * corporal é plausivelmente o conteúdo mais sensível do catálogo — já
     * nasceu usando o payload genérico (criado depois da correção do item 9),
     * este teste só trava isso.
     */
    public function test_comentario_foto_evolucao_nao_expoe_o_comentario_no_payload(): void
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
        BodyPhoto::create([
            'student_id' => $student->id,
            'file_path' => 'body-photos/teste.jpg',
            'ai_feedback' => 'Comentário sensível sobre o corpo do aluno que não pode vazar.',
        ]);

        Artisan::call('notifications:process');

        Notification::assertSentTo(
            $student,
            function (PushNotification $notification) {
                $this->assertStringNotContainsString('corpo do aluno', $notification->corpo);
                $this->assertSame(NotificacaoCandidato::CORPO_ALUNO_GENERICO, $notification->corpo);

                return true;
            }
        );
    }
}
