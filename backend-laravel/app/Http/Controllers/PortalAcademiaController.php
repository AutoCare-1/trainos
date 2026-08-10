<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStudentByToken;
use App\Models\Exercise;
use App\Models\GymAnalysisResult;
use App\Models\GymMediaSubmission;
use App\Models\GymWorkoutRecommendation;
use App\Support\AcademiaAnalyzer;
use App\Support\AcademiaRecomendador;
use App\Support\ErrorReporting;
use App\Support\KillSwitchIa;
use App\Support\Uploads;
use App\Support\VideoProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PortalAcademiaController extends Controller
{
    use ResolvesStudentByToken;

    private const LABEL_PAR_Q = [
        'cardiaco' => 'histórico cardíaco',
        'tontura' => 'tontura/equilíbrio',
        'articular' => 'problema articular',
        'pressao_medicacao' => 'pressão alta ou uso de medicação contínua',
    ];

    private static function limitacoesParQ(?array $respostas): array
    {
        if (! $respostas) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn ($k) => ! empty($respostas[$k]) ? self::LABEL_PAR_Q[$k] : null,
            array_keys(self::LABEL_PAR_Q)
        )));
    }

    // GET /:token/academia — histórico de submissões do aluno, mais recente primeiro
    public function index(string $token): JsonResponse
    {
        $student = $this->buscarAlunoPorToken($token);
        if (! $student) {
            return response()->json(['error' => 'Link inválido'], 404);
        }

        $submissions = DB::table('gym_media_submissions as s')
            ->leftJoin('gym_workout_recommendations as r', 'r.submission_id', '=', 's.id')
            ->where('s.student_id', $student->id)
            ->orderByDesc('s.created_at')
            ->select('s.*', 'r.approval_status', 'r.name as recommendation_name')
            ->get();

        return response()->json(['submissions' => $submissions]);
    }

    // GET /:token/academia/:submissionId — detalhe de uma submissão (mídia, análise e recomendação)
    public function show(string $token, string $submissionId): JsonResponse
    {
        $student = $this->buscarAlunoPorToken($token);
        if (! $student) {
            return response()->json(['error' => 'Link inválido'], 404);
        }

        $submission = GymMediaSubmission::where('id', $submissionId)->where('student_id', $student->id)->first();
        if (! $submission) {
            return response()->json(['error' => 'Submissão não encontrada'], 404);
        }

        // MySQL já ordena NULL antes de qualquer valor em ASC por padrão (comportamento
        // oposto ao Postgres) — orderBy simples reproduz o "nulls first" do original.
        $assets = DB::table('gym_media_assets')
            ->where('submission_id', $submission->id)
            ->orderBy('frame_index')
            ->get();

        return response()->json([
            'submission' => $submission,
            'assets' => $assets,
            'analysis' => GymAnalysisResult::where('submission_id', $submission->id)->first(),
            'recommendation' => GymWorkoutRecommendation::where('submission_id', $submission->id)->first(),
        ]);
    }

    // POST /:token/academia — aluno envia foto(s) ou vídeo da academia; dispara o pipeline completo
    public function store(Request $request, string $token): JsonResponse
    {
        // Sem detecção de equipamentos não há nada útil a fazer aqui (a
        // recomendação depende dela) — desliga o endpoint inteiro. Ver o
        // segundo kill-switch (academia_recomendacao) mais abaixo, que só
        // pula a etapa de recomendação, mantendo a análise.
        if ($resp = KillSwitchIa::verificar('academia_analise')) {
            return $resp;
        }

        $student = $this->buscarAlunoPorToken($token);
        if (! $student) {
            return response()->json(['error' => 'Link inválido'], 404);
        }

        $request->validate([
            'media' => ['required', 'array', 'min:1', 'max:10'],
            'media.*' => ['file', 'mimetypes:image/*,video/*', 'max:102400'],
        ]);
        $files = $request->file('media');

        $videos = array_values(array_filter($files, fn ($f) => str_starts_with($f->getMimeType(), 'video/')));
        if (count($videos) > 1 || (count($videos) === 1 && count($files) > 1)) {
            return response()->json(['error' => 'Envie um vídeo por vez, ou uma ou mais fotos'], 400);
        }

        $daysPerWeek = min(6, max(2, (int) $request->input('days_per_week') ?: 3));
        $submissionType = count($videos) === 1 ? 'video' : (count($files) > 1 ? 'album' : 'photo');

        $submission = GymMediaSubmission::create([
            'student_id' => $student->id,
            'professional_id' => $student->professional_id,
            'submission_type' => $submissionType,
            'days_per_week' => $daysPerWeek,
        ]);

        try {
            $caminhosParaAnalise = [];

            if ($submissionType === 'video') {
                $videoUrl = Uploads::storePublic($videos[0], 'gym-media');
                $videoAbsPath = public_path(ltrim($videoUrl, '/'));
                $dirFrames = Uploads::publicRoot()."/gym-media/{$submission->id}-frames";

                $frames = VideoProcessor::extrairFramesDeVideo($videoAbsPath, $dirFrames);
                foreach ($frames as $i => $caminho) {
                    $caminhosParaAnalise[] = [
                        'absoluto' => $caminho,
                        'asset_type' => 'video_frame',
                        'file_path' => "/uploads/gym-media/{$submission->id}-frames/".basename($caminho),
                        'frame_index' => $i,
                    ];
                }
            } else {
                foreach ($files as $file) {
                    $url = Uploads::storePublic($file, 'gym-media');
                    $caminhosParaAnalise[] = [
                        'absoluto' => public_path(ltrim($url, '/')),
                        'asset_type' => 'photo',
                        'file_path' => $url,
                        'frame_index' => null,
                    ];
                }
            }

            foreach ($caminhosParaAnalise as $item) {
                DB::table('gym_media_assets')->insert([
                    'id' => (string) Str::orderedUuid(),
                    'submission_id' => $submission->id,
                    'asset_type' => $item['asset_type'],
                    'file_path' => $item['file_path'],
                    'frame_index' => $item['frame_index'],
                ]);
            }

            $analise = AcademiaAnalyzer::analisarMidiaAcademia(array_map(fn ($c) => $c['absoluto'], $caminhosParaAnalise), $student->professional_id);

            $analysisResult = GymAnalysisResult::create([
                'submission_id' => $submission->id,
                'machines_json' => $analise['machines'],
                'zones_identified' => $analise['zones_identified'],
                'total_unique_machines' => count($analise['machines']['machines']),
                'coverage_estimate' => $analise['coverage_estimate'],
                'gaps' => $analise['gaps'],
                'notes' => $analise['notes'],
            ])->refresh();

            // A recomendação pode ser desligada sozinha (mantendo a detecção de
            // equipamentos) — a submissão fica completa mesmo sem ela; o
            // personal só não vê um treino sugerido, só a análise.
            $recommendation = null;
            if (config('ia_pipelines.academia_recomendacao') !== false) {
                $exercicios = Exercise::orderBy('muscle_group')->orderBy('name')
                    ->get(['id', 'name', 'muscle_group', 'equipment'])
                    ->map(fn ($e) => $e->toArray())
                    ->all();

                $recomendacao = AcademiaRecomendador::recomendarTreino($analise['machines'], $exercicios, [
                    'nome' => $student->name,
                    'objective' => $student->objective,
                    'healthNotes' => $student->health_notes,
                    'limitacoesParQ' => self::limitacoesParQ($student->par_q_answers),
                    'daysPerWeek' => $daysPerWeek,
                ], $student->professional_id);

                $recommendation = GymWorkoutRecommendation::create([
                    'submission_id' => $submission->id,
                    'analysis_result_id' => $analysisResult->id,
                    'name' => $recomendacao['name'],
                    'split_type' => $recomendacao['split_type'],
                    'reasoning' => $recomendacao['reasoning'],
                    'recommended_items' => $recomendacao['items'],
                ])->refresh();
            }

            $submission->update(['status' => 'completed']);

            return response()->json([
                'submission' => $submission,
                'analysis' => $analysisResult,
                'recommendation' => $recommendation,
            ], 201);
        } catch (\Throwable $e) {
            ErrorReporting::capturarFalhaIa('academia_analise', $e, ['student_id' => $student->id, 'submission_id' => $submission->id]);
            // Nunca a mensagem crua da exceção aqui — pode vazar detalhe interno
            // (path de arquivo, erro de SDK/API) pro aluno, que só vê essa resposta.
            $submission->update(['status' => 'failed', 'error_message' => 'Não foi possível concluir a análise agora.']);

            return response()->json([
                'submission' => $submission,
                'analysis' => null,
                'recommendation' => null,
            ], 201);
        }
    }
}
