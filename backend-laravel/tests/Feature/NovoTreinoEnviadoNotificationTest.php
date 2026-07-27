<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\Professional;
use App\Models\Student;
use App\Models\Workout;
use Database\Seeders\NotificationTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Item 7 da revisão: confirma (não foi preciso corrigir nada — já era assim)
 * que novo_treino_enviado só dispara quando sent_at está preenchido
 * (TreinoController::enviar, a única linha do código que escreve essa coluna),
 * nunca por causa de um rascunho criado/editado sem ser enviado.
 */
class NovoTreinoEnviadoNotificationTest extends TestCase
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

    public function test_rascunho_nao_enviado_nao_dispara_notificacao(): void
    {
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

        // Rascunho: status default 'draft', sent_at nunca preenchido.
        Workout::create([
            'professional_id' => $professional->id,
            'student_id' => $student->id,
            'name' => 'Rascunho de treino',
        ]);

        Artisan::call('notifications:process');

        $this->assertSame(0, NotificationLog::where('tipo_chave', 'novo_treino_enviado')->count());
    }
}
