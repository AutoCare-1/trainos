<?php

namespace Tests\Feature;

use App\Models\Challenge;
use App\Models\ChallengeParticipant;
use App\Models\NotificationLog;
use App\Models\Professional;
use App\Models\SessionEntry;
use App\Models\Student;
use App\Models\TrainingSession;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Notifications\PushNotification;
use Database\Seeders\NotificationTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Item 12 da revisão: "último rank notificado" agora vive em
 * challenge_participants.ultima_posicao_notificada, não numa tabela dedicada.
 */
class MudancaRankingDesafioNotificationTest extends TestCase
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

    private function alunoComSessoesConcluidas(Professional $professional, Challenge $challenge, string $nome, int $sessoes): Student
    {
        $student = Student::create([
            'professional_id' => $professional->id, 'name' => $nome, 'invite_token' => uniqid('token'),
        ]);
        ChallengeParticipant::create(['challenge_id' => $challenge->id, 'student_id' => $student->id]);

        $exercise = \App\Models\Exercise::firstOrCreate(['name' => 'Agachamento'], ['muscle_group' => 'pernas']);
        $workout = Workout::create([
            'professional_id' => $professional->id, 'student_id' => $student->id,
            'name' => 'Treino', 'status' => 'sent', 'sent_at' => now()->subDays(5),
        ]);
        $we = WorkoutExercise::create(['workout_id' => $workout->id, 'exercise_id' => $exercise->id, 'order_index' => 0, 'sets' => 3, 'reps' => '10']);

        for ($i = 0; $i < $sessoes; $i++) {
            $session = TrainingSession::create([
                'workout_id' => $workout->id, 'student_id' => $student->id, 'status' => 'completed',
                'started_at' => now()->subHours($i + 1), 'finished_at' => now()->subHours($i + 1)->addMinutes(30),
            ]);
            SessionEntry::create(['training_session_id' => $session->id, 'workout_exercise_id' => $we->id, 'set_number' => 1, 'load_kg_done' => 20]);
        }

        return $student;
    }

    public function test_primeira_observacao_nao_notifica_so_grava_posicao_inicial(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-27 12:00:00'));

        $professional = Professional::create([
            'name' => 'Personal Teste', 'email' => uniqid('personal').'@example.com', 'password_hash' => bcrypt('senha12345'),
        ]);
        $challenge = Challenge::create([
            'professional_id' => $professional->id, 'name' => 'Desafio Teste',
            'start_date' => now()->subDays(3)->toDateString(), 'end_date' => now()->addDays(3)->toDateString(),
        ]);
        $lider = $this->alunoComSessoesConcluidas($professional, $challenge, 'Líder', 3);
        $this->alunoComSessoesConcluidas($professional, $challenge, 'Segundo', 1);

        Artisan::call('notifications:process');

        // Outras regras (ex: medalha_conquistada de "primeiro treino") disparam
        // normalmente aqui — o que este teste garante é que mudanca_ranking_desafio
        // especificamente não dispara na primeira observação.
        $this->assertSame(0, NotificationLog::where('tipo_chave', 'mudanca_ranking_desafio')->count());
        $participante = ChallengeParticipant::where('student_id', $lider->id)->first();
        $this->assertSame(1, $participante->ultima_posicao_notificada, 'deveria gravar a posição inicial mesmo sem notificar');
    }

    public function test_notifica_quando_posicao_muda_na_segunda_observacao(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-07-27 12:00:00'));

        $professional = Professional::create([
            'name' => 'Personal Teste', 'email' => uniqid('personal').'@example.com', 'password_hash' => bcrypt('senha12345'),
        ]);
        $challenge = Challenge::create([
            'professional_id' => $professional->id, 'name' => 'Desafio Teste',
            'start_date' => now()->subDays(3)->toDateString(), 'end_date' => now()->addDays(3)->toDateString(),
        ]);
        $atrasado = $this->alunoComSessoesConcluidas($professional, $challenge, 'Atrasado', 1);
        $this->alunoComSessoesConcluidas($professional, $challenge, 'Na frente', 3);

        // Primeira observação: Atrasado em 2º lugar — só grava, não notifica.
        Artisan::call('notifications:process');
        $this->assertSame(0, NotificationLog::where('tipo_chave', 'mudanca_ranking_desafio')->count());

        // Atrasado faz mais 3 sessões e ultrapassa — segunda observação deve notificar a mudança.
        $workout = Workout::where('student_id', $atrasado->id)->first();
        $we = WorkoutExercise::where('workout_id', $workout->id)->first();
        for ($i = 0; $i < 3; $i++) {
            $session = TrainingSession::create([
                'workout_id' => $workout->id, 'student_id' => $atrasado->id, 'status' => 'completed',
                'started_at' => now()->subMinutes(10), 'finished_at' => now()->subMinutes(5),
            ]);
            SessionEntry::create(['training_session_id' => $session->id, 'workout_exercise_id' => $we->id, 'set_number' => 1, 'load_kg_done' => 20]);
        }

        Carbon::setTestNow(Carbon::parse('2026-07-27 12:20:00'));
        Artisan::call('notifications:process');

        Notification::assertSentTo($atrasado, PushNotification::class);
        $this->assertSame(1, ChallengeParticipant::where('student_id', $atrasado->id)->first()->ultima_posicao_notificada);
    }

    public function test_mudanca_suprimida_pelo_coordenador_nao_marca_a_posicao_como_notificada(): void
    {
        // Chama a Rule direto (sem passar pelo ProcessNotifications) pra isolar
        // o contrato: a posição só deve ser gravada como "vista" se alguém
        // realmente confirmar o envio via aoConfirmarEnvio — nunca só por
        // avaliar() ter rodado. Sem essa correção, ultima_posicao_notificada já
        // teria sido atualizada aqui embaixo, mesmo sem nenhum push ter saído.
        Carbon::setTestNow(Carbon::parse('2026-07-27 12:00:00'));

        $professional = Professional::create([
            'name' => 'Personal Teste', 'email' => uniqid('personal').'@example.com', 'password_hash' => bcrypt('senha12345'),
        ]);
        $challenge = Challenge::create([
            'professional_id' => $professional->id, 'name' => 'Desafio Teste',
            'start_date' => now()->subDays(3)->toDateString(), 'end_date' => now()->addDays(3)->toDateString(),
        ]);
        $atrasado = $this->alunoComSessoesConcluidas($professional, $challenge, 'Atrasado', 1);
        $this->alunoComSessoesConcluidas($professional, $challenge, 'Na frente', 3);

        // Primeira observação: só grava a posição inicial (2º lugar).
        (new \App\Notifications\Rules\MudancaRankingDesafioRule)->avaliar();
        $participante = ChallengeParticipant::where('student_id', $atrasado->id)->first();
        $this->assertSame(2, $participante->ultima_posicao_notificada);

        // Ultrapassa e vira 1º — segunda avaliação gera um candidato de mudança.
        $workout = Workout::where('student_id', $atrasado->id)->first();
        $we = WorkoutExercise::where('workout_id', $workout->id)->first();
        for ($i = 0; $i < 3; $i++) {
            $session = TrainingSession::create([
                'workout_id' => $workout->id, 'student_id' => $atrasado->id, 'status' => 'completed',
                'started_at' => now()->subMinutes(10), 'finished_at' => now()->subMinutes(5),
            ]);
            SessionEntry::create(['training_session_id' => $session->id, 'workout_exercise_id' => $we->id, 'set_number' => 1, 'load_kg_done' => 20]);
        }

        // Os dois trocaram de lugar entre si — cada um gera seu próprio candidato.
        $candidatos = (new \App\Notifications\Rules\MudancaRankingDesafioRule)->avaliar();
        $this->assertCount(2, $candidatos, 'os dois participantes trocaram de posição entre si');
        $candidatoAtrasado = collect($candidatos)->first(fn ($c) => $c->studentId === $atrasado->id);
        $this->assertNotNull($candidatoAtrasado->aoConfirmarEnvio, 'candidato de mudança precisa do callback de confirmação');

        // Simula supressão: NUNCA chama aoConfirmarEnvio (equivalente a o
        // CoordenadorNotificacoes ter descartado este candidato).
        $participante->refresh();
        $this->assertSame(
            2,
            $participante->ultima_posicao_notificada,
            'posição não deveria ter sido marcada como notificada sem confirmação de envio'
        );

        // Confirma que o callback, quando de fato chamado, grava a posição nova.
        ($candidatoAtrasado->aoConfirmarEnvio)();
        $this->assertSame(1, $participante->refresh()->ultima_posicao_notificada);
    }
}
