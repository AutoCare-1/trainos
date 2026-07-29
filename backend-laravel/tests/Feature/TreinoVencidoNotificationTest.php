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
use Tests\TestCase;

/**
 * Aviso de treino vencido (aluno + personal) — dispara quando expires_at já
 * passou, distinto do aviso "vencendo" (janela de dias antes de vencer).
 */
class TreinoVencidoNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NotificationTypesSeeder::class);
    }

    private function criarTreinoComValidade(int $diasParaVencer, bool $arquivado = false): array
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);

        $workout = Workout::create([
            'professional_id' => $professional->id,
            'student_id' => $student->id,
            'name' => 'Treino de Peito',
            'status' => 'sent',
            'sent_at' => now(),
            'duration_weeks' => 6,
            'expires_at' => now()->addDays($diasParaVencer)->toDateString(),
            'archived_at' => $arquivado ? now() : null,
        ]);

        return [$professional, $student, $workout];
    }

    public function test_avisa_aluno_e_personal_quando_ja_venceu(): void
    {
        Notification::fake();
        [$professional, $student] = $this->criarTreinoComValidade(diasParaVencer: -2);

        Artisan::call('notifications:process');

        Notification::assertSentTo($student, PushNotification::class);
        Notification::assertSentTo($professional, PushNotification::class);
        $this->assertSame(1, NotificationLog::where('tipo_chave', 'treino_vencido_aluno')->count());
        $this->assertSame(1, NotificationLog::where('tipo_chave', 'treino_vencido_personal')->count());
    }

    public function test_nao_avisa_treino_arquivado(): void
    {
        Notification::fake();
        $this->criarTreinoComValidade(diasParaVencer: -2, arquivado: true);

        Artisan::call('notifications:process');

        $this->assertSame(0, NotificationLog::where('tipo_chave', 'treino_vencido_aluno')->count());
        $this->assertSame(0, NotificationLog::where('tipo_chave', 'treino_vencido_personal')->count());
    }

    public function test_nao_avisa_treino_que_ainda_nao_venceu(): void
    {
        Notification::fake();
        $this->criarTreinoComValidade(diasParaVencer: 5);

        Artisan::call('notifications:process');

        $this->assertSame(0, NotificationLog::where('tipo_chave', 'treino_vencido_aluno')->count());
        $this->assertSame(0, NotificationLog::where('tipo_chave', 'treino_vencido_personal')->count());
    }

    public function test_nao_avisa_treino_sem_validade_definida(): void
    {
        Notification::fake();
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);
        Workout::create([
            'professional_id' => $professional->id,
            'student_id' => $student->id,
            'name' => 'Treino sem validade',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        Artisan::call('notifications:process');

        $this->assertSame(0, NotificationLog::where('tipo_chave', 'treino_vencido_aluno')->where('student_id', $student->id)->count());
    }

    public function test_nao_duplica_ao_rodar_o_comando_duas_vezes(): void
    {
        Notification::fake();
        $this->criarTreinoComValidade(diasParaVencer: -2);

        Artisan::call('notifications:process');
        Artisan::call('notifications:process');

        $this->assertSame(1, NotificationLog::where('tipo_chave', 'treino_vencido_aluno')->count());
        $this->assertSame(1, NotificationLog::where('tipo_chave', 'treino_vencido_personal')->count());
    }
}
