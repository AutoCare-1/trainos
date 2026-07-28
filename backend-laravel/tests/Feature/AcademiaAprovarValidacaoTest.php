<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\GymAnalysisResult;
use App\Models\GymMediaSubmission;
use App\Models\GymWorkoutRecommendation;
use App\Models\Professional;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AcademiaController::aprovar aceitava sets/rest_seconds editados pelo personal
 * sem min — mesma classe de bug do #46 (TreinoController/ModeloController),
 * só que faltando aqui.
 */
class AcademiaAprovarValidacaoTest extends TestCase
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

    private function criarSubmissaoPendente(string $professionalId, string $studentId): GymMediaSubmission
    {
        $submission = GymMediaSubmission::create([
            'student_id' => $studentId,
            'professional_id' => $professionalId,
            'submission_type' => 'photo',
            'status' => 'completed',
        ])->refresh();

        $analysisResult = GymAnalysisResult::create([
            'submission_id' => $submission->id,
            'machines_json' => [],
            'zones_identified' => [],
            'total_unique_machines' => 0,
            'gaps' => [],
        ])->refresh();

        GymWorkoutRecommendation::create([
            'submission_id' => $submission->id,
            'analysis_result_id' => $analysisResult->id,
            'name' => 'Treino sugerido',
            'split_type' => 'fullbody',
            'recommended_items' => [],
            'approval_status' => 'pending',
        ]);

        return $submission;
    }

    public function test_aprovar_com_zero_series_devolve_422(): void
    {
        [$professional, $headers] = $this->autenticar();
        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);
        $submission = $this->criarSubmissaoPendente($professional->id, $student->id);
        $exercise = Exercise::firstOrCreate(['name' => 'Supino'], ['muscle_group' => 'peito']);

        $this->patchJson("/academia/{$submission->id}/aprovar", [
            'items' => [
                ['exercise_id' => $exercise->id, 'sets' => 0, 'reps' => '10'],
            ],
        ], $headers)->assertStatus(422);

        $this->assertDatabaseCount('workouts', 0);
    }

    public function test_aprovar_com_descanso_negativo_devolve_422(): void
    {
        [$professional, $headers] = $this->autenticar();
        $student = Student::create(['professional_id' => $professional->id, 'name' => 'Aluno', 'invite_token' => uniqid()]);
        $submission = $this->criarSubmissaoPendente($professional->id, $student->id);
        $exercise = Exercise::firstOrCreate(['name' => 'Supino'], ['muscle_group' => 'peito']);

        $this->patchJson("/academia/{$submission->id}/aprovar", [
            'items' => [
                ['exercise_id' => $exercise->id, 'sets' => 3, 'reps' => '10', 'rest_seconds' => -30],
            ],
        ], $headers)->assertStatus(422);

        $this->assertDatabaseCount('workouts', 0);
    }
}
