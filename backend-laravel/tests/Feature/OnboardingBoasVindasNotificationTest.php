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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OnboardingBoasVindasNotificationTest extends TestCase
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

    /**
     * created_at usa useCurrent() no banco (não é mass-assignable), então pra
     * simular "cadastrado há N dias" com o relógio congelado por Carbon::setTestNow()
     * é preciso sobrescrever direto via DB depois de criar.
     */
    private function criarAlunoCadastradoHaDias(int $dias, int $sessoesConcluidas = 0): Student
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);

        $student = Student::create([
            'professional_id' => $professional->id,
            'name' => 'Aluno Novo',
            'invite_token' => uniqid('token'),
        ]);

        DB::table('students')->where('id', $student->id)->update(['created_at' => now()->subDays($dias)]);

        if ($sessoesConcluidas > 0) {
            $workout = Workout::create([
                'professional_id' => $professional->id, 'student_id' => $student->id,
                'name' => 'Treino A', 'status' => 'sent', 'sent_at' => now()->subDays($dias),
            ]);
            for ($i = 0; $i < $sessoesConcluidas; $i++) {
                TrainingSession::create([
                    'workout_id' => $workout->id, 'student_id' => $student->id, 'status' => 'completed',
                    'started_at' => now()->subDays($dias - $i)->subHour(), 'finished_at' => now()->subDays($dias - $i),
                ]);
            }
        }

        return $student->fresh();
    }

    public function test_dispara_boas_vindas_no_dia_1_pra_todo_aluno_novo(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-26 12:00:00'));

        $aluno = $this->criarAlunoCadastradoHaDias(1);

        Artisan::call('notifications:process');

        Notification::assertSentTo($aluno, PushNotification::class);
        $this->assertSame(1, NotificationLog::where('tipo_chave', 'onboarding_boas_vindas')->where('contexto', '1')->count());
    }

    public function test_nao_dispara_dia_3_se_aluno_ja_engajou(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-26 12:00:00'));

        // 3+ sessões concluídas = já engajou, não precisa do empurrão do dia 3.
        $this->criarAlunoCadastradoHaDias(3, sessoesConcluidas: 3);

        Artisan::call('notifications:process');

        Notification::assertNothingSent();
        $this->assertSame(0, NotificationLog::where('tipo_chave', 'onboarding_boas_vindas')->count());
    }

    public function test_nao_duplica_ao_rodar_o_comando_duas_vezes(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-26 12:00:00'));

        $aluno = $this->criarAlunoCadastradoHaDias(7);

        Artisan::call('notifications:process');
        Artisan::call('notifications:process');

        Notification::assertSentToTimes($aluno, PushNotification::class, 1);
    }

    public function test_nao_dispara_quando_o_personal_desliga_o_tipo(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-26 12:00:00'));

        $aluno = $this->criarAlunoCadastradoHaDias(1);

        ProfessionalNotificationSetting::create([
            'professional_id' => $aluno->professional_id,
            'tipo_chave' => 'onboarding_boas_vindas',
            'student_id' => null,
            'enabled' => false,
        ]);

        Artisan::call('notifications:process');

        // Não usa assertNothingSent() — aluno_cadastrado dispara normalmente aqui
        // (o aluno foi criado há 1 dia); o que este teste garante é que
        // onboarding_boas_vindas especificamente respeita o toggle desligado.
        $this->assertSame(0, NotificationLog::where('tipo_chave', 'onboarding_boas_vindas')->count());
    }
}
