<?php

namespace App\Http\Controllers;

use App\Models\WorkoutTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModeloController extends Controller
{
    // GET / — lista modelos do profissional, com quantidade de exercícios
    public function index(Request $request): JsonResponse
    {
        $templates = DB::table('workout_templates as t')
            ->where('t.professional_id', $request->user()->id)
            ->orderByDesc('t.created_at')
            ->select('t.*')
            ->selectSub(
                DB::table('workout_template_exercises')->selectRaw('count(*)')
                    ->whereColumn('template_id', 't.id'),
                'total_exercicios'
            )
            ->get();

        return response()->json(['templates' => $templates]);
    }

    // POST / — cria um modelo de treino
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.exercise_id' => ['required', 'string'],
            'items.*.sets' => ['required', 'integer'],
            'items.*.reps' => ['required', 'string'],
            'items.*.load_kg' => ['nullable', 'numeric'],
            'items.*.rest_seconds' => ['nullable', 'integer'],
            'items.*.notes' => ['nullable', 'string'],
            'items.*.structure_type' => ['nullable', 'string'],
            'items.*.group_label' => ['nullable', 'string'],
        ]);

        $professionalId = $request->user()->id;

        $template = DB::transaction(function () use ($validated, $professionalId) {
            $template = WorkoutTemplate::create([
                'professional_id' => $professionalId,
                'name' => trim($validated['name']),
            ]);

            $orderIndex = 0;
            foreach ($validated['items'] as $item) {
                $template->exercises()->create([
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

            return $template;
        });

        return response()->json(['template' => $template], 201);
    }

    // GET /:id — detalhe do modelo com exercícios
    public function show(Request $request, string $id): JsonResponse
    {
        $template = WorkoutTemplate::where('id', $id)
            ->where('professional_id', $request->user()->id)
            ->first();
        if (! $template) {
            return response()->json(['error' => 'Modelo não encontrado'], 404);
        }

        $exercises = DB::table('workout_template_exercises as wte')
            ->join('exercises as e', 'e.id', '=', 'wte.exercise_id')
            ->where('wte.template_id', $template->id)
            ->orderBy('wte.order_index')
            ->select('wte.*', 'e.name as exercise_name', 'e.muscle_group', 'e.image_url', 'e.image_credit')
            ->get();

        return response()->json(['template' => $template, 'exercises' => $exercises]);
    }

    // DELETE /:id — remove um modelo
    public function destroy(Request $request, string $id): JsonResponse
    {
        $deleted = WorkoutTemplate::where('id', $id)
            ->where('professional_id', $request->user()->id)
            ->delete();

        if (! $deleted) {
            return response()->json(['error' => 'Modelo não encontrado'], 404);
        }

        return response()->json(null, 204);
    }
}
