<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\GymAnalysisResult;
use App\Models\GymMediaAsset;
use App\Models\GymMediaSubmission;
use App\Models\GymWorkoutRecommendation;
use App\Models\Workout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademiaController extends Controller
{
    // GET / — submissões dos alunos do profissional, mais recente primeiro (filtro opcional ?status=pending)
    public function index(Request $request): JsonResponse
    {
        $statusFiltro = $request->query('status');

        $submissions = DB::table('gym_media_submissions as sub')
            ->join('students as s', 's.id', '=', 'sub.student_id')
            ->leftJoin('gym_workout_recommendations as r', 'r.submission_id', '=', 'sub.id')
            ->where('sub.professional_id', $request->user()->id)
            ->when($statusFiltro, fn ($query) => $query->where('r.approval_status', $statusFiltro))
            ->orderByDesc('sub.created_at')
            ->select(
                'sub.*',
                's.name as student_name',
                's.photo_url as student_photo_url',
                'r.id as recommendation_id',
                'r.name as recommendation_name',
                'r.approval_status'
            )
            ->get();

        return response()->json(['submissions' => $submissions]);
    }

    // GET /:submissionId — detalhe completo (mídia, análise, recomendação com dados dos exercícios)
    public function show(Request $request, string $submissionId): JsonResponse
    {
        $professionalId = $request->user()->id;

        $submission = DB::table('gym_media_submissions as sub')
            ->join('students as s', 's.id', '=', 'sub.student_id')
            ->where('sub.id', $submissionId)
            ->where('sub.professional_id', $professionalId)
            ->select('sub.*', 's.name as student_name', 's.photo_url as student_photo_url', 's.objective', 's.health_notes', 's.par_q_answers')
            ->first();

        if (! $submission) {
            return response()->json(['error' => 'Submissão não encontrada'], 404);
        }
        // par_q_answers é coluna json — a query acima é DB::table() puro (não passa pelo
        // cast do model Student), então volta como string crua e precisa ser decodificada
        // manualmente pra bater com o pg do Node (que já entrega json/jsonb parseado).
        $submission->par_q_answers = $submission->par_q_answers !== null ? json_decode($submission->par_q_answers) : null;

        // MySQL já ordena NULL antes de qualquer valor em ASC por padrão (comportamento
        // oposto ao Postgres) — orderBy simples reproduz o "nulls first" do original.
        $assets = GymMediaAsset::where('submission_id', $submission->id)
            ->orderBy('frame_index')
            ->get();

        $analysis = GymAnalysisResult::where('submission_id', $submission->id)->first();
        $recommendation = GymWorkoutRecommendation::where('submission_id', $submission->id)->first();

        $items = [];
        if ($recommendation) {
            $ids = collect($recommendation->recommended_items)->pluck('exercise_id');
            $exercisesById = Exercise::whereIn('id', $ids)->get()->keyBy('id');

            $items = collect($recommendation->recommended_items)->map(function ($item) use ($exercisesById) {
                $exercise = $exercisesById->get($item['exercise_id']);

                return array_merge($item, [
                    'muscle_group' => $exercise?->muscle_group,
                    'image_url' => $exercise?->image_url,
                ]);
            })->all();
        }

        return response()->json([
            'submission' => $submission,
            'assets' => $assets,
            'analysis' => $analysis,
            'recommendation' => $recommendation ? array_merge($recommendation->toArray(), ['recommended_items' => $items]) : null,
        ]);
    }

    // PATCH /:submissionId/aprovar — cria o treino real do aluno a partir da recomendação (com possíveis edições)
    public function aprovar(Request $request, string $submissionId): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.exercise_id' => ['required_with:items', 'string'],
            'items.*.sets' => ['required_with:items', 'integer', 'min:1'],
            'items.*.reps' => ['required_with:items', 'string'],
            'items.*.rest_seconds' => ['nullable', 'integer', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $professionalId = $request->user()->id;

        $submission = GymMediaSubmission::where('id', $submissionId)
            ->where('professional_id', $professionalId)
            ->first();
        if (! $submission) {
            return response()->json(['error' => 'Submissão não encontrada'], 404);
        }

        $recommendation = GymWorkoutRecommendation::where('submission_id', $submission->id)->first();
        if (! $recommendation) {
            return response()->json(['error' => 'Recomendação não encontrada'], 404);
        }
        if ($recommendation->approval_status !== 'pending') {
            return response()->json(['error' => 'Esta recomendação já foi decidida'], 400);
        }

        $itensFinais = ! empty($validated['items']) ? $validated['items'] : $recommendation->recommended_items;
        if (empty($itensFinais)) {
            return response()->json(['error' => 'Nenhum exercício pra aprovar'], 400);
        }

        $workout = DB::transaction(function () use ($professionalId, $submission, $recommendation, $itensFinais, $validated) {
            $workout = Workout::create([
                'professional_id' => $professionalId,
                'student_id' => $submission->student_id,
                'name' => $recommendation->name,
            ]);

            $orderIndex = 0;
            foreach ($itensFinais as $item) {
                $workout->exercises()->create([
                    'exercise_id' => $item['exercise_id'],
                    'order_index' => $orderIndex++,
                    'sets' => $item['sets'],
                    'reps' => $item['reps'],
                    'rest_seconds' => $item['rest_seconds'] ?? null,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            $recommendation->update([
                'approval_status' => 'approved',
                'approved_workout_id' => $workout->id,
                'professional_notes' => $validated['notes'] ?? null,
                'approved_at' => now(),
            ]);

            return $workout;
        });

        return response()->json(['workout' => $workout]);
    }

    // PATCH /:submissionId/rejeitar — descarta a recomendação, sem criar treino
    public function rejeitar(Request $request, string $submissionId): JsonResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string'],
        ]);

        $recommendation = GymWorkoutRecommendation::query()
            ->join('gym_media_submissions as sub', 'sub.id', '=', 'gym_workout_recommendations.submission_id')
            ->where('sub.id', $submissionId)
            ->where('sub.professional_id', $request->user()->id)
            ->where('gym_workout_recommendations.approval_status', 'pending')
            ->select('gym_workout_recommendations.*')
            ->first();

        if (! $recommendation) {
            return response()->json(['error' => 'Recomendação não encontrada ou já decidida'], 404);
        }

        $recommendation->update([
            'approval_status' => 'rejected',
            'professional_notes' => $validated['notes'] ?? null,
            'approved_at' => now(),
        ]);

        return response()->json(['recommendation' => $recommendation]);
    }
}
