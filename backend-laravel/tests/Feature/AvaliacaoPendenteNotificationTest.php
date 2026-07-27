<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\Professional;
use App\Models\Student;
use Database\Seeders\NotificationTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Item 4 da revisão: mesmo antes desta correção o dedup já não era diário (a
 * chave usava ano-mês, reenviava no máximo 1x/mês) — mas tinha uma
 * irregularidade real no fim/início de mês. Este teste comprova o
 * comportamento corrigido: janela rolante de N dias (config
 * dias_lembrete_avaliacao_pendente) a partir de quando cruzou o limiar, sem
 * repetir todo dia enquanto isso.
 */
class AvaliacaoPendenteNotificationTest extends TestCase
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

    /** created_at usa useCurrent() no banco — sobrescreve direto via DB pra simular "cadastrado há N dias". */
    private function criarAlunoCadastradoHaDias(int $dias): Student
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

        DB::table('students')->where('id', $student->id)->update(['created_at' => now()->subDays($dias)]);

        return $student->fresh();
    }

    public function test_nao_dispara_de_novo_no_dia_seguinte_apos_cruzar_o_limiar(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-08-01 12:00:00'));

        // Cadastrado há 30 dias, nunca fez avaliação — cruzou o limiar hoje.
        $aluno = $this->criarAlunoCadastradoHaDias(30);

        Artisan::call('notifications:process');
        $this->assertSame(1, NotificationLog::where('tipo_chave', 'avaliacao_pendente')->where('student_id', $aluno->id)->count());

        // Avança só 1 dia (ainda dentro da janela de 15 dias do lembrete) — não deve reenviar.
        Carbon::setTestNow(Carbon::parse('2026-08-02 12:00:00'));
        Artisan::call('notifications:process');

        $this->assertSame(1, NotificationLog::where('tipo_chave', 'avaliacao_pendente')->where('student_id', $aluno->id)->count(), 'não deveria reenviar 1 dia depois');
    }

    public function test_dispara_de_novo_apos_a_janela_de_reenvio(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-08-01 12:00:00'));

        $aluno = $this->criarAlunoCadastradoHaDias(30);

        Artisan::call('notifications:process');
        $this->assertSame(1, NotificationLog::where('tipo_chave', 'avaliacao_pendente')->where('student_id', $aluno->id)->count());

        // 16 dias depois (passou da janela de 15 dias) — deve reenviar.
        Carbon::setTestNow(Carbon::parse('2026-08-17 12:00:00'));
        Artisan::call('notifications:process');

        $this->assertSame(2, NotificationLog::where('tipo_chave', 'avaliacao_pendente')->where('student_id', $aluno->id)->count());
    }
}
