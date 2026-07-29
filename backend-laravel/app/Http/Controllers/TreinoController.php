<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Workout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TreinoController extends Controller
{
    // POST / — cria um treino (rascunho) com seus exercícios
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'string'],
            'name' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.exercise_id' => ['required', 'string', 'exists:exercises,id'],
            'items.*.sets' => ['required', 'integer', 'min:1'],
            'items.*.reps' => ['required', 'string'],
            'items.*.load_kg' => ['nullable', 'numeric'],
            'items.*.rest_seconds' => ['nullable', 'integer'],
            'items.*.notes' => ['nullable', 'string'],
            'items.*.structure_type' => ['nullable', 'string'],
            'items.*.group_label' => ['nullable', 'string'],
        ]);

        $professionalId = $request->user()->id;

        $student = Student::where('id', $validated['student_id'])
            ->where('professional_id', $professionalId)
            ->first();
        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        $workout = DB::transaction(function () use ($validated, $professionalId, $student) {
            $workout = Workout::create([
                'professional_id' => $professionalId,
                'student_id' => $student->id,
                'name' => trim($validated['name']),
            ]);

            $orderIndex = 0;
            foreach ($validated['items'] as $item) {
                $workout->exercises()->create([
                    'exercise_id' => $item['exercise_id'],
                    'order_index' => $orderIndex++,
                    'sets' => $item['sets'],
                    'reps' => $item['reps'],
                    'load_kg' => $item['load_kg'] ?? null,
                    'rest_seconds' => $item['rest_seconds'] ?? null,
                    'notes' => $item['notes'] ?? null,
                    'structure_type' => trim($item['structure_type'] ?? '') ?: 'tradicional',
                    'group_label' => trim($item['group_label'] ?? '') ?: null,
                ]);
            }

            return $workout;
        });

        return response()->json(['workout' => $workout], 201);
    }

    // GET /:id — detalhe do treino com exercícios
    public function show(Request $request, string $id): JsonResponse
    {
        $professionalId = $request->user()->id;

        $workout = Workout::where('id', $id)
            ->where('professional_id', $professionalId)
            ->first();
        if (! $workout) {
            return response()->json(['error' => 'Treino não encontrado'], 404);
        }

        $exercises = DB::table('workout_exercises as we')
            ->join('exercises as e', 'e.id', '=', 'we.exercise_id')
            ->leftJoin('exercise_media_overrides as emo', function ($join) use ($professionalId) {
                $join->on('emo.exercise_id', '=', 'e.id')
                    ->where('emo.professional_id', '=', $professionalId);
            })
            ->where('we.workout_id', $workout->id)
            ->orderBy('we.order_index')
            ->select(
                'we.*',
                'e.name as exercise_name',
                'e.muscle_group',
                'e.instructions',
                DB::raw('coalesce(emo.video_url, e.video_url) as video_url'),
                'e.image_url',
                'e.image_credit'
            )
            ->get();

        return response()->json(['workout' => $workout, 'exercises' => $exercises]);
    }

    // POST /:id/enviar — publica o treino para o aluno, com validade opcional (em semanas)
    public function enviar(Request $request, string $id): JsonResponse
    {
        $workout = Workout::where('id', $id)
            ->where('professional_id', $request->user()->id)
            ->first();
        if (! $workout) {
            return response()->json(['error' => 'Treino não encontrado'], 404);
        }

        $validated = $request->validate([
            'duration_weeks' => ['nullable', 'integer', 'min:1'],
        ]);

        $agora = now();
        $durationWeeks = $validated['duration_weeks'] ?? null;

        $workout->update([
            'status' => 'sent',
            'sent_at' => $agora,
            'duration_weeks' => $durationWeeks,
            'expires_at' => $durationWeeks ? $agora->copy()->addWeeks($durationWeeks)->toDateString() : null,
        ]);

        return response()->json(['workout' => $workout]);
    }

    // PATCH /:id/arquivar — arquiva ou desarquiva um treino já enviado; decisão manual
    // do personal (nunca automática por expirar) — só treinos não-arquivados aparecem
    // pro aluno escolher no portal.
    public function arquivar(Request $request, string $id): JsonResponse
    {
        $workout = Workout::where('id', $id)
            ->where('professional_id', $request->user()->id)
            ->first();
        if (! $workout) {
            return response()->json(['error' => 'Treino não encontrado'], 404);
        }

        $validated = $request->validate(['arquivado' => ['required', 'boolean']]);

        $workout->update(['archived_at' => $validated['arquivado'] ? now() : null]);

        return response()->json(['workout' => $workout]);
    }
}
