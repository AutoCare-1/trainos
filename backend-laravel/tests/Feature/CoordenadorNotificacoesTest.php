<?php

namespace Tests\Feature;

use App\Models\NotificationLog;
use App\Models\Professional;
use App\Models\Student;
use App\Notifications\Rules\NotificacaoCandidato;
use App\Support\CoordenadorNotificacoes;
use Database\Seeders\NotificationTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Item 2 da revisão: cenários explícitos de colisão citados na revisão —
 * sem_treinar_hoje + streak_em_risco no mesmo dia; onboarding_boas_vindas +
 * sem_treinar_dias competindo pelo mesmo motivo — mais o limite diário geral.
 */
class CoordenadorNotificacoesTest extends TestCase
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

    private function criarAluno(): Student
    {
        $professional = Professional::create([
            'name' => 'Personal Teste', 'email' => uniqid('personal').'@example.com', 'password_hash' => bcrypt('senha12345'),
        ]);

        return Student::create(['professional_id' => $professional->id, 'name' => 'Aluno Teste', 'invite_token' => uniqid('token')]);
    }

    private function candidato(Student $aluno, string $chave, ?string $dedupSufixo = null): array
    {
        return [
            'chave' => $chave,
            'candidato' => new NotificacaoCandidato(
                recipient: $aluno,
                professionalId: $aluno->professional_id,
                studentId: $aluno->id,
                dedupKey: "{$chave}:{$aluno->id}:".($dedupSufixo ?? uniqid()),
                contexto: null,
                titulo: 'Título',
                corpo: 'Corpo',
                url: null,
            ),
        ];
    }

    public function test_onboarding_boas_vindas_suprime_sem_treinar_dias_no_mesmo_ciclo(): void
    {
        $aluno = $this->criarAluno();

        $itens = [
            $this->candidato($aluno, 'onboarding_boas_vindas'),
            $this->candidato($aluno, 'sem_treinar_dias'),
        ];

        $resultado = (new CoordenadorNotificacoes)->coordenar($itens);

        $this->assertCount(1, $resultado);
        $this->assertSame('onboarding_boas_vindas', $resultado[0]['chave']);
    }

    public function test_streak_em_risco_suprime_sem_treinar_hoje_no_mesmo_ciclo(): void
    {
        $aluno = $this->criarAluno();

        $itens = [
            $this->candidato($aluno, 'sem_treinar_hoje'),
            $this->candidato($aluno, 'streak_em_risco'),
        ];

        $resultado = (new CoordenadorNotificacoes)->coordenar($itens);

        $this->assertCount(1, $resultado);
        $this->assertSame('streak_em_risco', $resultado[0]['chave']);
    }

    public function test_limite_diario_mantem_so_os_2_de_maior_prioridade(): void
    {
        $aluno = $this->criarAluno();

        // 3 tipos sem relação de supressão entre si, em 3 tiers de prioridade
        // diferentes — os dois primeiros (tier 1 e 2) devem sobreviver ao corte,
        // o de tier 4 (cutucão de hábito, o de prioridade mais baixa) não.
        $itens = [
            $this->candidato($aluno, 'sem_treinar_hoje'), // tier 4
            $this->candidato($aluno, 'novo_treino_enviado'), // tier 1
            $this->candidato($aluno, 'novo_recorde_pessoal'), // tier 2
        ];

        $resultado = (new CoordenadorNotificacoes)->coordenar($itens);

        $this->assertCount(2, $resultado);
        $chaves = array_column($resultado, 'chave');
        $this->assertContains('novo_treino_enviado', $chaves);
        $this->assertContains('novo_recorde_pessoal', $chaves);
        $this->assertNotContains('sem_treinar_hoje', $chaves);
    }

    public function test_limite_diario_conta_o_que_ja_foi_enviado_em_ciclo_anterior_do_mesmo_dia(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-27 09:00:00'));
        $aluno = $this->criarAluno();

        // Simula 2 notificações já enviadas num ciclo anterior (às 9h).
        NotificationLog::create([
            'tipo_chave' => 'novo_treino_enviado', 'student_id' => $aluno->id, 'professional_id' => $aluno->professional_id,
            'dedup_key' => 'log-1', 'enviado_em' => now(),
        ]);
        NotificationLog::create([
            'tipo_chave' => 'novo_recorde_pessoal', 'student_id' => $aluno->id, 'professional_id' => $aluno->professional_id,
            'dedup_key' => 'log-2', 'enviado_em' => now(),
        ]);

        // Ciclo seguinte (9h15), mesmo dia: um candidato novo não deveria passar — limite já foi atingido hoje.
        Carbon::setTestNow(Carbon::parse('2026-07-27 09:15:00'));
        $itens = [$this->candidato($aluno, 'medalha_conquistada')];

        $resultado = (new CoordenadorNotificacoes)->coordenar($itens);

        $this->assertCount(0, $resultado, 'o limite diário deveria valer entre execuções do Scheduler, não só dentro do mesmo ciclo');
    }

    public function test_destinatarios_diferentes_tem_limites_independentes(): void
    {
        $aluno1 = $this->criarAluno();
        $aluno2 = $this->criarAluno();

        $itens = [
            $this->candidato($aluno1, 'novo_treino_enviado'),
            $this->candidato($aluno1, 'novo_recorde_pessoal'),
            $this->candidato($aluno1, 'medalha_conquistada'), // 3º do aluno1, deveria ser cortado
            $this->candidato($aluno2, 'novo_treino_enviado'), // aluno2 não é afetado pelo limite do aluno1
        ];

        $resultado = (new CoordenadorNotificacoes)->coordenar($itens);

        $this->assertCount(3, $resultado);
        $chavesAluno2 = array_column(array_filter($resultado, fn ($i) => $i['candidato']->studentId === $aluno2->id), 'chave');
        $this->assertCount(1, $chavesAluno2);
    }
}
