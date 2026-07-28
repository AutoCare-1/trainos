<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\Professional;
use App\Models\ProfessionalNotificationSetting;
use App\Models\Student;
use App\Models\TrainingSession;
use App\Models\Workout;
use App\Notifications\PushNotification;
use Database\Seeders\NotificationTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SemTreinarDiasNotificationTest extends TestCase
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

    /** Aluno com treino enviado e a última sessão concluída há exatamente 7 dias. */
    private function criarAlunoSemTreinarHa7Dias(): Student
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

        $workout = Workout::create([
            'professional_id' => $professional->id,
            'student_id' => $student->id,
            'name' => 'Treino A',
            'status' => 'sent',
            'sent_at' => now()->subDays(20),
        ]);

        TrainingSession::create([
            'workout_id' => $workout->id,
            'student_id' => $student->id,
            'status' => 'completed',
            'started_at' => now()->subDays(7)->subHour(),
            'finished_at' => now()->subDays(7),
        ]);

        return $student;
    }

    public function test_dispara_push_quando_aluno_completa_7_dias_sem_treinar(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-26 12:00:00'));

        $aluno = $this->criarAlunoSemTreinarHa7Dias();

        Artisan::call('notifications:process');

        Notification::assertSentTo($aluno, PushNotification::class);
        $this->assertSame(1, NotificationLog::where('tipo_chave', 'sem_treinar_dias')->where('student_id', $aluno->id)->count());
    }

    public function test_nao_duplica_ao_rodar_o_comando_duas_vezes_no_mesmo_dia(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-26 12:00:00'));

        $aluno = $this->criarAlunoSemTreinarHa7Dias();

        Artisan::call('notifications:process');
        Artisan::call('notifications:process');

        // Não usa assertSentToTimes(PushNotification::class, 1) puro — esse
        // cenário (aluno novo, exatamente 1 sessão concluída) também dispara
        // legitimamente medalha_conquistada (badge "primeiro treino") e
        // aluno_cadastrado; o que este teste garante é que sem_treinar_dias
        // especificamente não duplica ao rodar o comando duas vezes.
        $this->assertSame(1, NotificationLog::where('tipo_chave', 'sem_treinar_dias')->where('student_id', $aluno->id)->count());
    }

    public function test_nao_dispara_quando_o_personal_desliga_o_tipo(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-26 12:00:00'));

        $aluno = $this->criarAlunoSemTreinarHa7Dias();

        ProfessionalNotificationSetting::create([
            'professional_id' => $aluno->professional_id,
            'tipo_chave' => 'sem_treinar_dias',
            'student_id' => null,
            'enabled' => false,
        ]);

        Artisan::call('notifications:process');

        // Não usa assertNothingSent() — aluno_cadastrado dispara normalmente aqui
        // (o aluno foi criado "agora" no teste); o que este teste garante é que
        // sem_treinar_dias especificamente respeita o toggle desligado.
        $this->assertSame(0, NotificationLog::where('tipo_chave', 'sem_treinar_dias')->count());
    }
}
