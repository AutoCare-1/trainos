<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\ExerciseMediaOverride;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExercicioController extends Controller
{
    // GET / — biblioteca de exercícios, com o vídeo customizado do profissional
    // (se houver) sobrepondo o padrão da biblioteca.
    public function index(Request $request): JsonResponse
    {
        $professionalId = $request->user()->id;

        $exercises = DB::table('exercises as e')
            ->leftJoin('exercise_media_overrides as emo', function ($join) use ($professionalId) {
                $join->on('emo.exercise_id', '=', 'e.id')
                    ->where('emo.professional_id', '=', $professionalId);
            })
            ->orderBy('e.muscle_group')
            ->orderBy('e.name')
            ->select(
                'e.*',
                DB::raw('coalesce(emo.video_url, e.video_url) as video_url'),
                DB::raw('emo.video_url is not null as video_customizado')
            )
            ->get();

        return response()->json(['exercises' => $exercises]);
    }

    // POST /:id/video — envia (ou substitui) o vídeo customizado do profissional
    // pra este exercício.
    public function uploadVideo(Request $request, string $id): JsonResponse
    {
        if (! Exercise::whereKey($id)->exists()) {
            return response()->json(['error' => 'Exercício não encontrado'], 404);
        }

        $request->validate([
            'video' => ['required', 'file', 'mimetypes:video/*', 'max:102400'], // 100MB
        ]);

        $videoUrl = Uploads::storePublic($request->file('video'), 'exercise-videos');

        $override = ExerciseMediaOverride::updateOrCreate(
            ['professional_id' => $request->user()->id, 'exercise_id' => $id],
            ['video_url' => $videoUrl]
        );

        return response()->json(['override' => $override], 201);
    }

    // DELETE /:id/video — remove o vídeo customizado, voltando ao padrão da biblioteca.
    public function deleteVideo(Request $request, string $id): JsonResponse
    {
        ExerciseMediaOverride::where('professional_id', $request->user()->id)
            ->where('exercise_id', $id)
            ->delete();

        return response()->json(null, 204);
    }
}
