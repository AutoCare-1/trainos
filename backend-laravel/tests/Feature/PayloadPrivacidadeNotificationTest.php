<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Models\Student;
use App\Models\Workout;
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
}
