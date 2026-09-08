<?php

namespace App\Console\Commands;

use App\Models\BodyPhoto;
use App\Models\Exercise;
use App\Models\GymAnalysisResult;
use App\Models\GymMediaSubmission;
use App\Models\GymWorkoutRecommendation;
use App\Models\Professional;
use App\Models\Student;
use App\Models\Workout;
use Illuminate\Console\Command;

/**
 * Dados de fixture pros 3 testes de fumaça (Playwright) — só pra CI/local,
 * NUNCA em produção. Cria direto no banco, sem chamar a Anthropic (a análise
 * de academia já vem pronta, como se a IA já tivesse rodado), pra não gerar
 * custo nem dependência de rede real durante o CI.
 */
class SeedE2e extends Command
{
    protected $signature = 'e2e:seed';

    protected $description = 'Cria os dados de fixture usados pelos testes de fumaça (Playwright). Bloqueado fora de local/testing.';

    public const EMAIL_PROFISSIONAL = 'e2e@clubemais.dev';

    public const SENHA_PROFISSIONAL = 'senha-e2e-12345';

    public const TOKEN_ALUNO = 'e2e-token-fixo-aluno';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('e2e:seed só pode rodar em local/testing — nunca em produção.');

            return self::FAILURE;
        }

        $professional = Professional::updateOrCreate(
            ['email' => self::EMAIL_PROFISSIONAL],
            ['name' => 'Personal E2E', 'password_hash' => bcrypt(self::SENHA_PROFISSIONAL)]
        );

        $student = Student::updateOrCreate(
            ['invite_token' => self::TOKEN_ALUNO],
            [
                'professional_id' => $professional->id,
                'name' => 'Aluno E2E',
                // Sem isso, o portal trava na tela de onboarding/PAR-Q antes de
                // mostrar qualquer treino.
                'onboarding_completed_at' => now(),
            ]
        );

        $exercicio = Exercise::first();
        if (! $exercicio) {
            $this->error('Nenhum exercício encontrado — rode "php artisan db:seed" (ExerciseSeeder) antes.');

            return self::FAILURE;
        }

        // Idempotente: rodar de novo sem migrate:fresh no meio não deve duplicar
        // treino/submissão/foto (só professional/student usam updateOrCreate).
        Workout::where('student_id', $student->id)->delete();
        GymMediaSubmission::where('student_id', $student->id)->delete();
        BodyPhoto::where('student_id', $student->id)->delete();

        // Treino enviado, com 1 exercício — pro fluxo "aluno abre o treino do dia e registra uma série".
        $workout = Workout::create([
            'professional_id' => $professional->id,
            'student_id' => $student->id,
            'name' => 'Treino E2E',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        $workout->exercises()->create([
            'exercise_id' => $exercicio->id,
            'order_index' => 0,
            'sets' => 3,
            'reps' => '10',
            'rest_seconds' => 60,
        ]);

        // Submissão de análise de academia já com análise + recomendação prontas
        // (como se a IA já tivesse rodado) — pro fluxo "personal aprova análise pendente".
        $submission = GymMediaSubmission::create([
            'student_id' => $student->id,
            'professional_id' => $professional->id,
            'submission_type' => 'photo',
            'days_per_week' => 3,
            'status' => 'completed',
        ]);
        $analise = GymAnalysisResult::create([
            'submission_id' => $submission->id,
            // Formato tem que bater com MachineDetectado (frontend/lib/types.ts) —
            // faltar primary_muscles/secondary_muscles/etc. quebra o render (.join()
            // em undefined), foi exatamente o bug achado rodando este teste pela 1a vez.
            'machines_json' => ['machines' => [[
                'name' => 'Leg press',
                'category' => 'pernas',
                'primary_muscles' => ['quadríceps', 'glúteos'],
                'secondary_muscles' => ['posterior de coxa'],
                'confidence' => 0.9,
                'notes' => 'Fixture de E2E — não passou pela IA de verdade.',
            ]]],
            'zones_identified' => ['pernas'],
            'total_unique_machines' => 1,
            'coverage_estimate' => 'parcial',
            'gaps' => ['sem equipamento de costas'],
            'notes' => 'Fixture de E2E — não passou pela IA de verdade.',
        ]);
        GymWorkoutRecommendation::create([
            'submission_id' => $submission->id,
            'analysis_result_id' => $analise->id,
            'name' => 'Treino sugerido (E2E)',
            'split_type' => 'fullbody',
            'reasoning' => 'Fixture de E2E — não passou pela IA de verdade.',
            // Formato tem que bater com RecommendedItem (frontend/lib/types.ts).
            'recommended_items' => [[
                'exercise_id' => $exercicio->id,
                'exercise_name' => $exercicio->name,
                'sets' => 3,
                'reps' => '10',
                'rest_seconds' => 60,
                'notes' => '',
            ]],
            'approval_status' => 'pending',
        ]);

        // Foto de evolução — sem isso a seção "Evolução (fotos)" da ficha do
        // aluno fica escondida (só renderiza se houver ao menos 1 foto).
        BodyPhoto::create([
            'student_id' => $student->id,
            'file_path' => 'body-photos/e2e-fixture/foto.jpg',
            'taken_at' => now(),
            'ai_feedback' => 'Fixture de E2E — não passou pela IA de verdade.',
        ]);

        $this->info('Fixture de E2E criada:');
        $this->info('  Profissional: '.self::EMAIL_PROFISSIONAL.' / '.self::SENHA_PROFISSIONAL);
        $this->info('  Aluno (token): '.self::TOKEN_ALUNO);
        $this->info('  Submissão de academia pendente: '.$submission->id);

        return self::SUCCESS;
    }
}
