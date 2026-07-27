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

    /** Candidato do lado do personal (recipient=Professional), sobre um aluno específico. */
    private function candidatoPersonal(Professional $personal, string $chave, Student $sobreAluno, ?string $dedupSufixo = null): array
    {
        return [
            'chave' => $chave,
            'candidato' => new NotificacaoCandidato(
                recipient: $personal,
                professionalId: $personal->id,
                studentId: $sobreAluno->id,
                dedupKey: "{$chave}:{$sobreAluno->id}:".($dedupSufixo ?? uniqid()),
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

    // --- Segunda rodada de revisão ---

    public function test_avaliacao_recebida_suprime_aluno_cadastrado_do_mesmo_aluno(): void
    {
        $aluno = $this->criarAluno();
        $personal = $aluno->professional;

        $itens = [
            $this->candidatoPersonal($personal, 'aluno_cadastrado', $aluno),
            $this->candidatoPersonal($personal, 'avaliacao_recebida', $aluno),
        ];

        $resultado = (new CoordenadorNotificacoes)->coordenar($itens);

        $this->assertCount(1, $resultado);
        $this->assertSame('avaliacao_recebida', $resultado[0]['chave']);
    }

    public function test_supressao_nao_afeta_aluno_diferente_sem_o_evento_dominante(): void
    {
        $alunoA = $this->criarAluno();
        $personal = $alunoA->professional;
        $alunoB = Student::create(['professional_id' => $personal->id, 'name' => 'Aluno B', 'invite_token' => uniqid('token')]);

        // avaliacao_recebida só existe pro Aluno A — aluno_cadastrado do Aluno B não tem por que ser suprimido.
        $itens = [
            $this->candidatoPersonal($personal, 'avaliacao_recebida', $alunoA),
            $this->candidatoPersonal($personal, 'aluno_cadastrado', $alunoA),
            $this->candidatoPersonal($personal, 'aluno_cadastrado', $alunoB),
        ];

        $resultado = (new CoordenadorNotificacoes)->coordenar($itens);

        $chaves = array_column($resultado, 'chave');
        $alunosDoResultado = array_column(array_column($resultado, 'candidato'), 'studentId');
        $this->assertContains('avaliacao_recebida', $chaves);
        $this->assertContains($alunoB->id, $alunosDoResultado, 'aluno_cadastrado do Aluno B não deveria ser suprimido');
    }

    public function test_consolida_multiplas_instancias_do_mesmo_tipo_pro_personal(): void
    {
        $aluno1 = $this->criarAluno();
        $personal = $aluno1->professional;
        $aluno2 = Student::create(['professional_id' => $personal->id, 'name' => 'Aluno 2', 'invite_token' => uniqid('token')]);
        $aluno3 = Student::create(['professional_id' => $personal->id, 'name' => 'Aluno 3', 'invite_token' => uniqid('token')]);

        $itens = [
            $this->candidatoPersonal($personal, 'revisao_pendente', $aluno1),
            $this->candidatoPersonal($personal, 'revisao_pendente', $aluno2),
            $this->candidatoPersonal($personal, 'revisao_pendente', $aluno3),
        ];

        $resultado = (new CoordenadorNotificacoes)->coordenar($itens);

        $this->assertCount(1, $resultado, '3 instâncias do mesmo tipo deveriam virar 1 notificação consolidada');
        $this->assertSame('revisao_pendente', $resultado[0]['chave']);
        $this->assertStringContainsString('3 alunos', $resultado[0]['candidato']->corpo);
        // Consolidada não usa mais o limite diário de 2 pra esconder os outros 2 alunos —
        // conta como 1 notificação só, não 3 instâncias brutas.
        $this->assertNull($resultado[0]['candidato']->studentId);
    }

    public function test_nao_consolida_quando_so_tem_uma_instancia(): void
    {
        $aluno = $this->criarAluno();
        $personal = $aluno->professional;

        $itens = [$this->candidatoPersonal($personal, 'revisao_pendente', $aluno)];

        $resultado = (new CoordenadorNotificacoes)->coordenar($itens);

        $this->assertCount(1, $resultado);
        $this->assertSame($aluno->id, $resultado[0]['candidato']->studentId, 'instância única não deveria virar "consolidada"');
    }

    public function test_mensagem_sem_resposta_nao_e_suprimida_por_celebracao_no_limite_diario(): void
    {
        $aluno = $this->criarAluno();
        $personal = $aluno->professional;
        $aluno2 = Student::create(['professional_id' => $personal->id, 'name' => 'Aluno 2', 'invite_token' => uniqid('token')]);

        // 3 candidatos do personal, limite é 2: mensagem_sem_resposta (tier 0) e
        // avaliacao_recebida (tier 1) devem ganhar de aluno_cadastrado (tier 5).
        $itens = [
            $this->candidatoPersonal($personal, 'aluno_cadastrado', $aluno2),
            $this->candidatoPersonal($personal, 'mensagem_sem_resposta', $aluno),
            $this->candidatoPersonal($personal, 'avaliacao_recebida', $aluno2),
        ];

        $resultado = (new CoordenadorNotificacoes)->coordenar($itens);

        $this->assertCount(2, $resultado);
        $chaves = array_column($resultado, 'chave');
        $this->assertContains('mensagem_sem_resposta', $chaves);
        $this->assertContains('avaliacao_recebida', $chaves);
    }
}
