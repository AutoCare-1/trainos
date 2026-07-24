<?php

namespace App\Http\Controllers;

use App\Models\Challenge;
use App\Models\ChallengeParticipant;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DesafioController extends Controller
{
    // GET / — lista desafios do profissional, com contagem de participantes e status calculado
    public function index(Request $request): JsonResponse
    {
        $challenges = DB::table('challenges as c')
            ->where('c.professional_id', $request->user()->id)
            ->orderByDesc('c.start_date')
            ->select('c.*')
            ->selectSub(
                DB::table('challenge_participants')->selectRaw('count(*)')
                    ->whereColumn('challenge_id', 'c.id'),
                'total_participantes'
            )
            ->selectRaw(<<<'SQL'
                case
                  when current_date < c.start_date then 'agendado'
                  when current_date > c.end_date then 'encerrado'
                  else 'ativo'
                end as status
                SQL)
            ->get();

        return response()->json(['challenges' => $challenges]);
    }

    // POST / — cria um desafio e adiciona os alunos participantes
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['string'],
        ]);

        $professionalId = $request->user()->id;

        $challenge = DB::transaction(function () use ($validated, $professionalId) {
            $challenge = Challenge::create([
                'professional_id' => $professionalId,
                'name' => trim($validated['name']),
                'description' => trim($validated['description'] ?? '') ?: null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
            ]);

            $validStudentIds = Student::where('professional_id', $professionalId)
                ->whereIn('id', $validated['student_ids'])
                ->pluck('id');

            foreach ($validStudentIds as $studentId) {
                ChallengeParticipant::firstOrCreate([
                    'challenge_id' => $challenge->id,
                    'student_id' => $studentId,
                ]);
            }

            return $challenge;
        });

        return response()->json(['challenge' => $challenge], 201);
    }

    // GET /:id — detalhe do desafio com quadro de destaques (pontos = treinos concluídos no período)
    public function show(Request $request, string $id): JsonResponse
    {
        $challenge = Challenge::where('id', $id)
            ->where('professional_id', $request->user()->id)
            ->first();
        if (! $challenge) {
            return response()->json(['error' => 'Desafio não encontrado'], 404);
        }

        $leaderboard = DB::table('challenge_participants as cp')
            ->join('students as s', 's.id', '=', 'cp.student_id')
            ->leftJoin('workouts as w', 'w.student_id', '=', 's.id')
            ->leftJoin('training_sessions as ts', function ($join) {
                $join->on('ts.workout_id', '=', 'w.id')
                    ->on('ts.student_id', '=', 's.id');
            })
            ->where('cp.challenge_id', $challenge->id)
            ->groupBy('s.id', 's.name', 's.photo_url')
            ->orderByDesc('pontos')
            ->orderBy('s.name')
            ->select('s.id as student_id', 's.name', 's.photo_url')
            ->selectRaw(
                "count(ts.id) filter (where ts.status = 'completed' and ts.finished_at::date between ? and ?) as pontos",
                [$challenge->start_date, $challenge->end_date]
            )
            ->get();

        return response()->json(['challenge' => $challenge, 'leaderboard' => $leaderboard]);
    }

    // DELETE /:id — remove o desafio
    public function destroy(Request $request, string $id): JsonResponse
    {
        $deleted = Challenge::where('id', $id)
            ->where('professional_id', $request->user()->id)
            ->delete();

        if (! $deleted) {
            return response()->json(['error' => 'Desafio não encontrado'], 404);
        }

        return response()->json(null, 204);
    }
}
