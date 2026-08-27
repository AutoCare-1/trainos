<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Professional;
use App\Models\Student;
use App\Support\IaUsage;
use App\Support\KillSwitchIa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Teto de gasto diário com IA. O kill-switch que já existia é global: a única
 * reação possível a uma conta abusando era tirar a feature do ar pra todos os
 * clientes. O teto corta só quem estourou — o que importa porque o invite_token
 * do aluno não expira nem rotaciona, e circula por WhatsApp.
 */
class TetoGastoIaTest extends TestCase
{
    use RefreshDatabase;

    private function criarPersonal(): Professional
    {
        return Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
    }

    private function gravarGasto(?string $professionalId, float $usd, ?string $quando = null): void
    {
        DB::table('ia_usage_logs')->insert([
            'id' => (string) Str::uuid(),
            'professional_id' => $professionalId,
            'pipeline' => 'chat_autopilot',
            'model' => 'claude-haiku-4-5-20251001',
            'input_tokens' => 1000,
            'output_tokens' => 100,
            'custo_usd' => $usd,
            'created_at' => $quando ?? now(),
        ]);
    }

    public function test_personal_dentro_do_teto_continua_chamando_a_ia(): void
    {
        config(['ia_pipelines.teto_diario_usd_por_personal' => 5.0]);
        $personal = $this->criarPersonal();
        $this->gravarGasto($personal->id, 1.50);

        $this->assertNull(KillSwitchIa::verificar('chat_autopilot', $personal->id));
    }

    public function test_personal_que_estourou_o_teto_recebe_503(): void
    {
        config(['ia_pipelines.teto_diario_usd_por_personal' => 5.0]);
        $personal = $this->criarPersonal();
        $this->gravarGasto($personal->id, 5.01);

        $resposta = KillSwitchIa::verificar('chat_autopilot', $personal->id);

        $this->assertNotNull($resposta);
        $this->assertSame(503, $resposta->getStatusCode());
    }

    public function test_teto_de_um_personal_nao_derruba_a_ia_do_outro(): void
    {
        config(['ia_pipelines.teto_diario_usd_por_personal' => 5.0]);
        $gastador = $this->criarPersonal();
        $vizinho = $this->criarPersonal();
        $this->gravarGasto($gastador->id, 9.99);

        $this->assertNotNull(KillSwitchIa::verificar('chat_autopilot', $gastador->id));
        $this->assertNull(KillSwitchIa::verificar('chat_autopilot', $vizinho->id));
    }

    public function test_gasto_de_ontem_nao_conta_no_teto_de_hoje(): void
    {
        config(['ia_pipelines.teto_diario_usd_por_personal' => 5.0]);
        $personal = $this->criarPersonal();
        $this->gravarGasto($personal->id, 99.0, quando: now()->subDay()->toDateTimeString());

        $this->assertNull(KillSwitchIa::verificar('chat_autopilot', $personal->id));
    }

    public function test_teto_zerado_desliga_a_checagem(): void
    {
        config(['ia_pipelines.teto_diario_usd_por_personal' => 0]);
        $personal = $this->criarPersonal();
        $this->gravarGasto($personal->id, 999.0);

        $this->assertNull(KillSwitchIa::verificar('chat_autopilot', $personal->id));
    }

    public function test_chat_do_portal_para_de_responder_quando_o_personal_estoura_o_teto(): void
    {
        config(['ia_pipelines.teto_diario_usd_por_personal' => 5.0]);
        $personal = $this->criarPersonal();
        $student = Student::create([
            'professional_id' => $personal->id,
            'name' => 'Aluno Teste',
            'invite_token' => uniqid('token'),
        ]);
        $this->gravarGasto($personal->id, 5.01);

        // A mensagem do aluno continua sendo registrada — o que some é só a
        // resposta automática, mesmo efeito de autopilot desligado.
        $this->postJson("/portal/{$student->invite_token}/mensagens", ['content' => 'Oi, tudo bem?'])
            ->assertCreated()
            ->assertJsonPath('aiReply', null);

        $this->assertSame(1, Message::where('student_id', $student->id)->count());
        $this->assertSame(0, Message::where('student_id', $student->id)->where('sender', 'ai')->count());
    }

    public function test_gasto_novo_invalida_o_total_cacheado(): void
    {
        $personal = $this->criarPersonal();
        $this->gravarGasto($personal->id, 1.00);
        $this->assertSame(1.0, IaUsage::gastoDeHojeUsd($personal->id));

        // Sem invalidar, a chamada nova só apareceria no total um minuto
        // depois — e quem está abusando faz muita chamada em um minuto.
        IaUsage::registrar('chat_autopilot', $this->respostaFalsaDaIa(), $personal->id);

        $this->assertGreaterThan(1.0, IaUsage::gastoDeHojeUsd($personal->id));
    }

    public function test_rajada_no_chat_do_portal_e_barrada_pelo_rate_limit(): void
    {
        // Autopilot desligado: o que se testa aqui é o limite de requisições,
        // não a IA (que nem é chamada).
        $personal = $this->criarPersonal();
        $student = Student::create([
            'professional_id' => $personal->id,
            'name' => 'Aluno Teste',
            'invite_token' => uniqid('token'),
            'ai_autopilot' => false,
        ]);

        for ($i = 0; $i < 20; $i++) {
            $this->postJson("/portal/{$student->invite_token}/mensagens", ['content' => "mensagem {$i}"])
                ->assertCreated();
        }

        $this->postJson("/portal/{$student->invite_token}/mensagens", ['content' => 'a de número 21'])
            ->assertStatus(429);
    }

    /** Resposta no formato mínimo que IaUsage::registrar consome. */
    private function respostaFalsaDaIa(): object
    {
        return (object) [
            'model' => 'claude-haiku-4-5-20251001',
            'usage' => (object) [
                'inputTokens' => 500_000,
                'outputTokens' => 100_000,
            ],
        ];
    }
}
