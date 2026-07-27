<?php

namespace Tests\Feature;

use App\Models\BodyPhoto;
use App\Models\NotificationLog;
use App\Models\Professional;
use App\Models\Student;
use App\Notifications\PushNotification;
use Database\Seeders\NotificationTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Item 13 da revisão: dois tipos novos, reaproveitando a infra já existente. */
class NovosTiposNotificationTest extends TestCase
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

    public function test_dispara_quando_coach_ia_comenta_foto_de_evolucao(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-27 12:00:00'));

        [, $student] = $this->criarProfissionalEAluno();

        BodyPhoto::create([
            'student_id' => $student->id,
            'file_path' => 'body-photos/teste.jpg',
            'ai_feedback' => 'Comentário da Coach IA sobre a foto.',
        ]);

        Artisan::call('notifications:process');

        Notification::assertSentTo($student, PushNotification::class);
        $this->assertSame(1, NotificationLog::where('tipo_chave', 'comentario_foto_evolucao')->where('student_id', $student->id)->count());
    }

    public function test_nao_dispara_sem_comentario_da_ia_ainda(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-27 12:00:00'));

        [, $student] = $this->criarProfissionalEAluno();

        BodyPhoto::create([
            'student_id' => $student->id,
            'file_path' => 'body-photos/teste.jpg',
            'ai_feedback' => null,
        ]);

        Artisan::call('notifications:process');

        $this->assertSame(0, NotificationLog::where('tipo_chave', 'comentario_foto_evolucao')->count());
    }

    public function test_dispara_pro_personal_quando_aluno_novo_e_cadastrado(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-27 12:00:00'));

        [$professional, $student] = $this->criarProfissionalEAluno();

        Artisan::call('notifications:process');

        Notification::assertSentTo($professional, PushNotification::class);
        $this->assertSame(1, NotificationLog::where('tipo_chave', 'aluno_cadastrado')->where('student_id', $student->id)->count());
    }
}
