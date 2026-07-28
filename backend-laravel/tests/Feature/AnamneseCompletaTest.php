<?php

namespace Tests\Feature;

use App\Models\Professional;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Complemento da anamnese inicial (birth_date + anamnese json) pedido pelo personal
 * a partir do formulário em papel dele — cobre os dois caminhos que gravam esses
 * campos: o aluno preenchendo sozinho no onboarding (PortalController::avaliacao) e o
 * personal editando na ficha do aluno (AlunoController::updateAvaliacao).
 */
class AnamneseCompletaTest extends TestCase
{
    use RefreshDatabase;

    private function anamneseDeExemplo(): array
    {
        return [
            'historico_atividade_fisica' => [
                'ja_praticou' => 'Musculação por 2 anos',
                'pratica_atualmente' => 'Corrida',
                'modalidades_favoritas' => 'Musculação',
                'modalidades_nao_gosta' => 'Crossfit',
                'treinou_com_personal' => true,
            ],
            'objetivos' => [
                'selecionados' => ['emagrecimento', 'condicionamento'],
                'outro' => '',
                'prazo' => '6 meses',
            ],
            'condicoes_saude' => [
                'restricao_medica' => '',
                'doenca_diagnosticada' => 'Nenhuma',
                'lesao' => 'Joelho direito em 2023',
                'medicamentos' => '',
                'suplementos' => 'Whey protein',
                'alergias' => '',
            ],
            'estilo_de_vida' => [
                'profissao' => 'Designer',
                'nivel_estresse' => 'medio',
                'qualidade_sono' => 'boa',
                'horas_sono' => '7',
                'alimentacao' => 'Equilibrada',
                'plano_alimentar' => '',
                'frequencia_alcool' => 'Socialmente',
                'fumante' => false,
                'tempo_fumante' => '',
            ],
            'motivacao' => [
                'motivacao' => 'Saúde',
                'obstaculos' => 'Trabalho',
                'preferencia_intensidade' => 'curtos_intensos',
                'preferencia_companhia' => 'sozinho',
                'horario_disponivel' => 'Manhã',
            ],
            'disponibilidade' => [
                'vezes_por_semana' => '4',
                'tempo_por_treino' => '1h',
                'local_treino' => ['academia'],
            ],
            'historico_familiar' => 'Hipertensão (pai)',
        ];
    }

    public function test_aluno_preenche_anamnese_completa_no_proprio_onboarding(): void
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);

        $response = $this->postJson("/portal/{$student->invite_token}/avaliacao", [
            'par_q_answers' => ['cardiaco' => false, 'tontura' => false, 'articular' => false, 'pressao_medicacao' => false],
            'health_notes' => 'Nenhuma',
            'birth_date' => '1995-03-20',
            'anamnese' => $this->anamneseDeExemplo(),
        ]);

        $response->assertStatus(201);

        $student->refresh();
        $this->assertSame('1995-03-20', $student->birth_date->toDateString());
        $this->assertSame('curtos_intensos', $student->anamnese['motivacao']['preferencia_intensidade']);
        $this->assertSame(['emagrecimento', 'condicionamento'], $student->anamnese['objetivos']['selecionados']);
        $this->assertNotNull($student->onboarding_completed_at);
    }

    public function test_personal_edita_anamnese_do_aluno(): void
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $token = auth('api')->login($professional);
        $headers = ['Authorization' => "Bearer {$token}"];

        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);

        $response = $this->patchJson("/alunos/{$student->id}/avaliacao", [
            'par_q_answers' => ['cardiaco' => false, 'tontura' => false, 'articular' => false, 'pressao_medicacao' => false],
            'health_notes' => null,
            'birth_date' => '1990-01-15',
            'anamnese' => $this->anamneseDeExemplo(),
        ], $headers);

        $response->assertStatus(200);
        $response->assertJsonPath('student.anamnese.disponibilidade.local_treino', ['academia']);

        // GET /alunos/:id (usado pela ficha do aluno) devolve o mesmo dado, já
        // decodificado pelo cast do model — é o caminho que a tela do personal usa.
        $mostrar = $this->getJson("/alunos/{$student->id}", $headers);
        $mostrar->assertStatus(200);
        $mostrar->assertJsonPath('student.birth_date', '1990-01-15');
        $mostrar->assertJsonPath('student.anamnese.historico_familiar', 'Hipertensão (pai)');
    }
}
