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
 * Aviso de treino vencendo (aluno + personal) — dispara quando expires_at está
 * dentro da janela configurável (padrão 7 dias), só pra treinos enviados e
 * não-arquivados.
 */
class TreinoVencendoNotificationTest extends TestCase
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

    public function test_avisa_aluno_e_personal_quando_falta_uma_semana(): void
    {
        Notification::fake();
        [$professional, $student] = $this->criarTreinoComValidade(diasParaVencer: 5);

        Artisan::call('notifications:process');

        Notification::assertSentTo($student, PushNotification::class);
        Notification::assertSentTo($professional, PushNotification::class);
        $this->assertSame(1, NotificationLog::where('tipo_chave', 'treino_vencendo_aluno')->count());
        $this->assertSame(1, NotificationLog::where('tipo_chave', 'treino_vencendo_personal')->count());
    }

    public function test_nao_avisa_treino_arquivado(): void
    {
        Notification::fake();
        $this->criarTreinoComValidade(diasParaVencer: 5, arquivado: true);

        Artisan::call('notifications:process');

        // Verifica pelo tipo específico (não por "nenhum push saiu") — a criação
        // direta do fixture (Student/Workout) pode legitimamente disparar outras
        // regras não relacionadas (ex: aluno_cadastrado, novo_treino_enviado).
        $this->assertSame(0, NotificationLog::where('tipo_chave', 'treino_vencendo_aluno')->count());
        $this->assertSame(0, NotificationLog::where('tipo_chave', 'treino_vencendo_personal')->count());
    }

    public function test_nao_avisa_treino_que_vence_muito_no_futuro(): void
    {
        Notification::fake();
        $this->criarTreinoComValidade(diasParaVencer: 30);

        Artisan::call('notifications:process');

        $this->assertSame(0, NotificationLog::where('tipo_chave', 'treino_vencendo_aluno')->count());
        $this->assertSame(0, NotificationLog::where('tipo_chave', 'treino_vencendo_personal')->count());
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

        $this->assertSame(0, NotificationLog::where('tipo_chave', 'treino_vencendo_aluno')->where('student_id', $student->id)->count());
    }

    public function test_nao_duplica_ao_rodar_o_comando_duas_vezes(): void
    {
        Notification::fake();
        $this->criarTreinoComValidade(diasParaVencer: 5);

        Artisan::call('notifications:process');
        Artisan::call('notifications:process');

        $this->assertSame(1, NotificationLog::where('tipo_chave', 'treino_vencendo_aluno')->count());
        $this->assertSame(1, NotificationLog::where('tipo_chave', 'treino_vencendo_personal')->count());
    }
}
