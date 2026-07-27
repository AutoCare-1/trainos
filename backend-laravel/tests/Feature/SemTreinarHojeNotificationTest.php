<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\Professional;
use App\Models\Student;
use App\Models\Workout;
use App\Notifications\PushNotification;
use Database\Seeders\NotificationTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Item 3 da revisão: o app não tem conceito de "dia de descanso programado"
 * (sem agenda semanal no schema, só um treino ativo). O que dá pra garantir —
 * e é o que este teste cobre — é não cobrar no mesmo dia em que o treino foi
 * prescrito, o que era uma cobrança indevida real (observada manualmente antes
 * dessa correção).
 */
class SemTreinarHojeNotificationTest extends TestCase
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

    private function criarProfissionalEAluno(): array
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

        return [$professional, $student];
    }

    public function test_nao_dispara_no_mesmo_dia_em_que_o_treino_foi_enviado(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-27 19:00:00'));

        [, $student] = $this->criarProfissionalEAluno();

        Workout::create([
            'professional_id' => $student->professional_id,
            'student_id' => $student->id,
            'name' => 'Treino recém-enviado',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        Artisan::call('notifications:process');

        // novo_treino_enviado dispara normalmente aqui (é o esperado — o treino
        // acabou de ser enviado); o que este teste garante é que sem_treinar_hoje
        // especificamente não dispara no mesmo dia da prescrição.
        $this->assertSame(0, NotificationLog::where('tipo_chave', 'sem_treinar_hoje')->where('student_id', $student->id)->count());
    }

    public function test_dispara_quando_treino_foi_enviado_em_dia_anterior_e_nao_treinou_hoje(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-27 19:00:00'));

        [, $student] = $this->criarProfissionalEAluno();

        Workout::create([
            'professional_id' => $student->professional_id,
            'student_id' => $student->id,
            'name' => 'Treino de ontem',
            'status' => 'sent',
            'sent_at' => now()->subDay(),
        ]);

        Artisan::call('notifications:process');

        Notification::assertSentTo($student, PushNotification::class);
        $this->assertSame(1, NotificationLog::where('tipo_chave', 'sem_treinar_hoje')->where('student_id', $student->id)->count());
    }
}
