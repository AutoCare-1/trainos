<?php

namespace Tests\Feature;

use App\Models\AgendaOcorrencia;
use App\Models\AgendaSlot;
use App\Models\Professional;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Agenda semanal do personal — horário fixo (aluno opcional, pode ser só um
 * título/bloqueio) + troca pontual numa data específica sem afetar o padrão.
 */
class AgendaTest extends TestCase
{
    use RefreshDatabase;

    private function autenticar(): array
    {
        $professional = Professional::create([
            'name' => 'Personal Teste',
            'email' => uniqid('personal').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $token = auth('api')->login($professional);

        return [$professional, ['Authorization' => "Bearer {$token}"]];
    }

    public function test_cria_horario_com_aluno(): void
    {
        [$professional, $headers] = $this->autenticar();
        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);

        $this->postJson('/agenda/horarios', [
            'student_id' => $student->id,
            'dia_semana' => 3,
            'hora' => '18:00',
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('slot.student_id', $student->id)
            ->assertJsonPath('slot.duracao_minutos', 60);
    }

    public function test_cria_horario_so_com_titulo_sem_aluno(): void
    {
        [, $headers] = $this->autenticar();

        $this->postJson('/agenda/horarios', [
            'titulo' => 'Bloqueio pessoal',
            'dia_semana' => 1,
            'hora' => '07:00',
        ], $headers)
            ->assertCreated()
            ->assertJsonPath('slot.student_id', null)
            ->assertJsonPath('slot.titulo', 'Bloqueio pessoal');
    }

    public function test_rejeita_horario_sem_aluno_e_sem_titulo(): void
    {
        [, $headers] = $this->autenticar();

        $this->postJson('/agenda/horarios', [
            'dia_semana' => 2,
            'hora' => '09:00',
        ], $headers)->assertStatus(422);
    }

    public function test_nao_deixa_vincular_aluno_de_outro_personal(): void
    {
        [, $headers] = $this->autenticar();
        $outro = Professional::create([
            'name' => 'Outro',
            'email' => uniqid('outro').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $studentDeOutro = Student::create(['professional_id' => $outro->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);

        $this->postJson('/agenda/horarios', [
            'student_id' => $studentDeOutro->id,
            'dia_semana' => 3,
            'hora' => '18:00',
        ], $headers)->assertNotFound();
    }

    public function test_horario_aparece_na_data_certa_da_semana(): void
    {
        [$professional, $headers] = $this->autenticar();
        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);

        // Segunda-feira de uma semana conhecida, calculada dinamicamente pra
        // não depender de qual dia é "hoje" quando o teste roda.
        $segunda = Carbon::parse('next monday', 'UTC');
        AgendaSlot::create([
            'professional_id' => $professional->id,
            'student_id' => $student->id,
            'dia_semana' => $segunda->dayOfWeek, // 1
            'hora' => '18:00',
        ]);

        $resposta = $this->getJson('/agenda?semana='.$segunda->toDateString(), $headers)->assertOk();
        $dias = $resposta->json('dias');

        $diaEsperado = collect($dias)->firstWhere('data', $segunda->toDateString());
        $this->assertNotNull($diaEsperado);
        $this->assertCount(1, $diaEsperado['horarios']);
        $this->assertSame('18:00', $diaEsperado['horarios'][0]['hora']);
        $this->assertSame($student->id, $diaEsperado['horarios'][0]['student']['id']);
        $this->assertFalse($diaEsperado['horarios'][0]['eh_excecao']);

        // Outro dia da mesma semana não deve ter esse horário
        $outroDia = collect($dias)->firstWhere('data', $segunda->copy()->addDay()->toDateString());
        $this->assertCount(0, $outroDia['horarios']);
    }

    public function test_troca_aluno_numa_data_especifica_sem_afetar_a_regra_fixa(): void
    {
        [$professional, $headers] = $this->autenticar();
        $titular = Student::create(['professional_id' => $professional->id, 'name' => 'Titular', 'invite_token' => uniqid()]);
        $substituto = Student::create(['professional_id' => $professional->id, 'name' => 'Substituto', 'invite_token' => uniqid()]);

        $segunda = Carbon::parse('next monday', 'UTC');
        $slot = AgendaSlot::create([
            'professional_id' => $professional->id,
            'student_id' => $titular->id,
            'dia_semana' => $segunda->dayOfWeek,
            'hora' => '18:00',
        ]);

        $this->patchJson("/agenda/horarios/{$slot->id}/ocorrencias", [
            'data' => $segunda->toDateString(),
            'student_id' => $substituto->id,
        ], $headers)->assertOk();

        // Na data trocada, aparece o substituto
        $respostaSemana1 = $this->getJson('/agenda?semana='.$segunda->toDateString(), $headers)->json('dias');
        $horarioSemana1 = collect($respostaSemana1)->firstWhere('data', $segunda->toDateString())['horarios'][0];
        $this->assertSame($substituto->id, $horarioSemana1['student']['id']);
        $this->assertTrue($horarioSemana1['eh_excecao']);

        // Na semana seguinte, volta a ser o titular (a exceção é só daquela data)
        $proximaSegunda = $segunda->copy()->addWeek();
        $respostaSemana2 = $this->getJson('/agenda?semana='.$proximaSegunda->toDateString(), $headers)->json('dias');
        $horarioSemana2 = collect($respostaSemana2)->firstWhere('data', $proximaSegunda->toDateString())['horarios'][0];
        $this->assertSame($titular->id, $horarioSemana2['student']['id']);
        $this->assertFalse($horarioSemana2['eh_excecao']);
    }

    public function test_marca_vago_numa_data_especifica(): void
    {
        [$professional, $headers] = $this->autenticar();
        $titular = Student::create(['professional_id' => $professional->id, 'name' => 'Titular', 'invite_token' => uniqid()]);
        $segunda = Carbon::parse('next monday', 'UTC');
        $slot = AgendaSlot::create([
            'professional_id' => $professional->id,
            'student_id' => $titular->id,
            'dia_semana' => $segunda->dayOfWeek,
            'hora' => '18:00',
        ]);

        $this->patchJson("/agenda/horarios/{$slot->id}/ocorrencias", [
            'data' => $segunda->toDateString(),
            'student_id' => null,
        ], $headers)->assertOk();

        $dias = $this->getJson('/agenda?semana='.$segunda->toDateString(), $headers)->json('dias');
        $horario = collect($dias)->firstWhere('data', $segunda->toDateString())['horarios'][0];
        $this->assertNull($horario['student']);
        $this->assertTrue($horario['eh_excecao']);
    }

    public function test_marca_presenca_e_falta(): void
    {
        [$professional, $headers] = $this->autenticar();
        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);
        $slot = AgendaSlot::create([
            'professional_id' => $professional->id,
            'student_id' => $student->id,
            'dia_semana' => 3,
            'hora' => '18:00',
        ]);

        $this->patchJson("/agenda/horarios/{$slot->id}/ocorrencias", [
            'data' => '2026-01-07',
            'presenca' => 'falta',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('ocorrencia.presenca', 'falta')
            // Marcar falta não pode apagar o aluno do horário (bug achado na
            // verificação ao vivo: mandar só "presenca" zerava student_id).
            ->assertJsonPath('ocorrencia.student_id', $student->id);

        $this->assertDatabaseHas('agenda_ocorrencias', [
            'slot_id' => $slot->id,
            'student_id' => $student->id,
            'presenca' => 'falta',
        ]);
    }

    public function test_nao_deixa_mexer_em_horario_de_outro_personal(): void
    {
        [, $headers] = $this->autenticar();
        $outro = Professional::create([
            'name' => 'Outro',
            'email' => uniqid('outro').'@example.com',
            'password_hash' => bcrypt('senha12345'),
        ]);
        $studentDeOutro = Student::create(['professional_id' => $outro->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);
        $slotDeOutro = AgendaSlot::create([
            'professional_id' => $outro->id,
            'student_id' => $studentDeOutro->id,
            'dia_semana' => 2,
            'hora' => '10:00',
        ]);

        $this->patchJson("/agenda/horarios/{$slotDeOutro->id}", ['ativo' => false], $headers)->assertNotFound();
        $this->patchJson("/agenda/horarios/{$slotDeOutro->id}/ocorrencias", ['data' => '2026-01-07'], $headers)->assertNotFound();
    }

    public function test_slot_inativo_nao_aparece_na_listagem(): void
    {
        [$professional, $headers] = $this->autenticar();
        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);
        $segunda = Carbon::parse('next monday', 'UTC');
        $slot = AgendaSlot::create([
            'professional_id' => $professional->id,
            'student_id' => $student->id,
            'dia_semana' => $segunda->dayOfWeek,
            'hora' => '18:00',
        ]);

        $this->patchJson("/agenda/horarios/{$slot->id}", ['ativo' => false], $headers)->assertOk();

        $dias = $this->getJson('/agenda?semana='.$segunda->toDateString(), $headers)->json('dias');
        $dia = collect($dias)->firstWhere('data', $segunda->toDateString());
        $this->assertCount(0, $dia['horarios']);
    }
}
