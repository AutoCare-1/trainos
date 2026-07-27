<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStudentByToken;
use App\Models\FormAnalysisResult;
use App\Models\FormCorrectionVideo;
use App\Models\FormFeedbackHistory;
use App\Models\TrainingSession;
use App\Support\FormAnalyzer;
use App\Support\Uploads;
use App\Support\VideoProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PortalFormaController extends Controller
{
    use ResolvesStudentByToken;

    private static function inferirNivelAluno(int $totalSessoesConcluidas): string
    {
        if ($totalSessoesConcluidas < 20) {
            return 'iniciante';
        }
        if ($totalSessoesConcluidas < 50) {
            return 'intermediário';
        }

        return 'avançado';
    }

    // POST /:token/forma — analisa a forma de execução a partir de um vídeo curto (3-15s) de uma série
    public function store(Request $request, string $token): JsonResponse
    {
        $student = $this->buscarAlunoPorToken($token);
        if (! $student) {
            return response()->json(['error' => 'Link inválido'], 404);
        }

        $request->validate(['video' => ['required', 'file', 'mimetypes:video/*', 'max:51200']]);

        $exerciseId = $request->input('exercise_id');
        $exerciseName = trim((string) $request->input('exercise_name'));
        $workoutId = $request->input('workout_id') ?: null;
        if (! $exerciseId || $exerciseName === '') {
            return response()->json(['error' => 'exercise_id e exercise_name são obrigatórios'], 400);
        }

        $videoUrl = Uploads::storePublic($request->file('video'), 'form-videos');
        $videoAbsPath = public_path(ltrim($videoUrl, '/'));

        $duracao = VideoProcessor::obterDuracaoVideo($videoAbsPath);
        if ($duracao < 3 || $duracao > 15) {
            @unlink($videoAbsPath);

            return response()->json(['error' => 'O vídeo precisa ter entre 3 e 15 segundos'], 400);
        }

        $video = FormCorrectionVideo::create([
            'student_id' => $student->id,
            'workout_id' => $workoutId,
            'exercise_id' => $exerciseId,
            'video_file_path' => $videoUrl,
            'video_duration_seconds' => $duracao,
        ])->refresh();

        $dirFrames = Uploads::publicRoot()."/form-frames/{$video->id}";
        try {
            $frames = VideoProcessor::extrairFramesChave($videoAbsPath, $dirFrames);

            $sessoesConcluidas = TrainingSession::where('student_id', $student->id)
                ->where('status', 'completed')
                ->count();
            $nivel = self::inferirNivelAluno($sessoesConcluidas);

            $analise = FormAnalyzer::analisarForma($frames, $exerciseName, $nivel);

            $analysisResult = FormAnalysisResult::create([
                'video_id' => $video->id,
                'amplitude_assessment' => $analise['amplitude_assessment'],
                'posture_assessment' => $analise['posture_assessment'],
                'tempo_assessment' => $analise['tempo_assessment'],
                'compensations' => $analise['compensations'],
                'safety_notes' => $analise['safety_notes'],
                'three_key_feedback' => $analise['three_key_feedback'],
                'analysis_status' => 'completed',
            ])->refresh();

            DB::statement(
                <<<'SQL'
                insert into form_feedback_history (id, student_id, exercise_id, feedback_count, last_feedback_at)
                values (?, ?, ?, 1, now())
                on duplicate key update
                  feedback_count = feedback_count + 1, last_feedback_at = now()
                SQL,
                [(string) Str::orderedUuid(), $student->id, $exerciseId]
            );

            return response()->json(['video' => $video, 'analysis' => $analysisResult], 201);
        } catch (\Throwable $e) {
            Log::error('[Análise de forma] Falha no pipeline: '.$e->getMessage());
            $analysisResult = FormAnalysisResult::create([
                'video_id' => $video->id,
                'safety_notes' => $e->getMessage(),
                'three_key_feedback' => [],
                'analysis_status' => 'failed',
            ])->refresh();

            return response()->json(['video' => $video, 'analysis' => $analysisResult], 201);
        } finally {
            File::deleteDirectory($dirFrames);
        }
    }
}
