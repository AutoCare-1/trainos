<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStudentByToken;
use App\Models\Feedback;
use App\Models\SessionEntry;
use App\Models\TrainingSession;
use App\Support\Gamification;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalController extends Controller
{
    use ResolvesStudentByToken;

    // GET /:token — dados do aluno + treino mais recente enviado
    public function show(string $token): JsonResponse
    {
        $student = $this->buscarAlunoPorToken($token);
        if (! $student) {
            return response()->json(['error' => 'Link inválido'], 404);
        }

        $workout = DB::table('workouts')
            ->where('student_id', $student->id)
            ->where('status', 'sent')
            ->orderByDesc('sent_at')
            ->first();

        $exercises = [];
        $activeSession = null;
        if ($workout) {
            $exercises = DB::table('workout_exercises as we')
                ->join('exercises as e', 'e.id', '=', 'we.exercise_id')
                ->leftJoin('exercise_media_overrides as emo', function ($join) use ($student) {
                    $join->on('emo.exercise_id', '=', 'e.id')
                        ->where('emo.professional_id', '=', $student->professional_id);
                })
                ->where('we.workout_id', $workout->id)
                ->orderBy('we.order_index')
                ->select(
                    'we.*', 'e.name as exercise_name', 'e.muscle_group', 'e.instructions',
                    DB::raw('coalesce(emo.video_url, e.video_url) as video_url'),
                    'e.image_url', 'e.image_credit'
                )
                ->get();

            $activeSession = DB::table('training_sessions')
                ->where('workout_id', $workout->id)
                ->where('student_id', $student->id)
                ->where('status', 'in_progress')
                ->select('id')
                ->first();
        }

        // Se o aluno já tinha uma sessão em andamento (ex: fechou o app no meio do treino
        // e reabriu o link depois), devolve quantas séries já foram registradas por
        // exercício — sem isso, o app reiniciaria a contagem do zero e duplicaria séries
        // já salvas ao registrar de novo.
        $registeredCounts = [];
        if ($activeSession) {
            $counts = DB::table('session_entries')
                ->where('training_session_id', $activeSession->id)
                ->select('workout_exercise_id', DB::raw('count(*) as total'))
                ->groupBy('workout_exercise_id')
                ->get();
            foreach ($counts as $c) {
                $registeredCounts[$c->workout_exercise_id] = (int) $c->total;
            }
        }

        $measurements = DB::table('body_measurements')
            ->where('student_id', $student->id)
            ->orderBy('recorded_at')
            ->get();

        $datas = DB::table('training_sessions as ts')
            ->join('workouts as w', 'w.id', '=', 'ts.workout_id')
            ->where('w.student_id', $student->id)
            ->where('ts.status', 'completed')
            ->pluck('ts.finished_at')
            ->all();
        $streak = Gamification::calcularStreak($datas);
        $gamificacao = [
            'total_sessoes' => count($datas),
            'streak' => $streak,
            'badges' => Gamification::calcularBadges(count($datas), $streak),
        ];

        $desafioAtivo = DB::table('challenges as c')
            ->join('challenge_participants as cp', 'cp.challenge_id', '=', 'c.id')
            ->where('cp.student_id', $student->id)
            ->whereRaw('current_date between c.start_date and c.end_date')
            ->orderByDesc('c.start_date')
            ->select('c.*')
            ->first();

        $leaderboard = [];
        if ($desafioAtivo) {
            $leaderboard = DB::table('challenge_participants as cp')
                ->join('students as s', 's.id', '=', 'cp.student_id')
                ->leftJoin('workouts as w', 'w.student_id', '=', 's.id')
                ->leftJoin('training_sessions as ts', function ($join) {
                    $join->on('ts.workout_id', '=', 'w.id')->on('ts.student_id', '=', 's.id');
                })
                ->where('cp.challenge_id', $desafioAtivo->id)
                ->groupBy('s.id', 's.name', 's.photo_url')
                ->orderByDesc('pontos')
                ->orderBy('s.name')
                ->select('s.id as student_id', 's.name', 's.photo_url')
                ->selectRaw(
                    "sum(case when ts.status = 'completed' and date(ts.finished_at) between ? and ? then 1 else 0 end) as pontos",
                    [$desafioAtivo->start_date, $desafioAtivo->end_date]
                )
                ->get();
        }

        return response()->json([
            'student' => [
                'id' => $student->id, 'name' => $student->name,
                'objective' => $student->objective, 'photo_url' => $student->photo_url,
            ],
            'workout' => $workout,
            'exercises' => $exercises,
            'activeSessionId' => $activeSession->id ?? null,
            'registeredCounts' => $registeredCounts,
            'measurements' => $measurements,
            'gamificacao' => $gamificacao,
            'desafio' => $desafioAtivo ? array_merge((array) $desafioAtivo, ['leaderboard' => $leaderboard]) : null,
            'onboardingCompleted' => $student->onboarding_completed_at !== null,
        ]);
    }

    // POST /:token/avaliacao — o próprio aluno responde a avaliação de saúde (PAR-Q) no primeiro acesso
    public function avaliacao(Request $request, string $token): JsonResponse
    {
        $student = $this->buscarAlunoPorToken($token);
        if (! $student) {
            return response()->json(['error' => 'Link inválido'], 404);
        }

        $validated = $request->validate([
            'par_q_answers' => ['required', 'array'],
            'health_notes' => ['nullable', 'string'],
        ]);

        $student->update([
            'par_q_answers' => $validated['par_q_answers'],
            'health_notes' => trim($validated['health_notes'] ?? '') ?: null,
            'onboarding_completed_at' => now(),
        ]);

        return response()->json(['onboardingCompleted' => true], 201);
    }

    // POST /:token/foto — o próprio aluno envia sua foto de perfil
    public function foto(Request $request, string $token): JsonResponse
    {
        $student = $this->buscarAlunoPorToken($token);
        if (! $student) {
            return response()->json(['error' => 'Link inválido'], 404);
        }

        $request->validate(['foto' => ['required', 'file', 'mimetypes:image/*', 'max:10240']]);

        $photoUrl = Uploads::storePublic($request->file('foto'), 'student-photos');
        $student->update(['photo_url' => $photoUrl]);

        return response()->json(['photoUrl' => $photoUrl], 201);
    }

    // POST /:token/sessoes — inicia (ou retoma) uma sessão de execução do treino
    public function iniciarSessao(Request $request, string $token): JsonResponse
    {
        $student = $this->buscarAlunoPorToken($token);
        if (! $student) {
            return response()->json(['error' => 'Link inválido'], 404);
        }

        $validated = $request->validate(['workout_id' => ['required', 'string']]);

        $existente = TrainingSession::where('workout_id', $validated['workout_id'])
            ->where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->first();
        if ($existente) {
            return response()->json(['session' => $existente]);
        }

        $session = TrainingSession::create([
            'workout_id' => $validated['workout_id'],
            'student_id' => $student->id,
        ])->refresh();

        return response()->json(['session' => $session], 201);
    }

    // POST /:token/sessoes/:sessionId/registros — registra uma série executada
    public function registrarSerie(Request $request, string $token, string $sessionId): JsonResponse
    {
        $student = $this->buscarAlunoPorToken($token);
        if (! $student) {
            return response()->json(['error' => 'Link inválido'], 404);
        }

        $validated = $request->validate([
            'workout_exercise_id' => ['required', 'string'],
            'set_number' => ['required', 'integer'],
            'reps_done' => ['nullable', 'integer'],
            'load_kg_done' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
        ]);

        $session = TrainingSession::where('id', $sessionId)->where('student_id', $student->id)->first();
        if (! $session) {
            return response()->json(['error' => 'Sessão não encontrada'], 404);
        }

        // Checa recorde ANTES de inserir a nova série, comparando com o maior peso
        // já registrado pelo aluno nesse exercício (em qualquer treino/sessão).
        $isPr = false;
        if (! empty($validated['load_kg_done'])) {
            $maxAnterior = (float) (DB::selectOne(
                <<<'SQL'
                select max(se.load_kg_done) as max_anterior
                from session_entries se
                join workout_exercises we on we.id = se.workout_exercise_id
                join training_sessions ts on ts.id = se.training_session_id
                where ts.student_id = ?
                  and we.exercise_id = (select exercise_id from workout_exercises where id = ?)
                SQL,
                [$student->id, $validated['workout_exercise_id']]
            )->max_anterior ?? 0);
            $isPr = $validated['load_kg_done'] > $maxAnterior;
        }

        $entry = SessionEntry::create([
            'training_session_id' => $sessionId,
            'workout_exercise_id' => $validated['workout_exercise_id'],
            'set_number' => $validated['set_number'],
            'reps_done' => $validated['reps_done'] ?? null,
            'load_kg_done' => $validated['load_kg_done'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_pr' => $isPr,
        ])->refresh();

        return response()->json(['entry' => $entry, 'isPr' => $isPr], 201);
    }

    // POST /:token/sessoes/:sessionId/concluir — finaliza a sessão + feedback pós-treino
    public function concluirSessao(Request $request, string $token, string $sessionId): JsonResponse
    {
        $student = $this->buscarAlunoPorToken($token);
        if (! $student) {
            return response()->json(['error' => 'Link inválido'], 404);
        }

        $validated = $request->validate([
            'effort_rpe' => ['nullable', 'integer'],
            'satisfaction' => ['nullable', 'integer'],
            'discomfort' => ['nullable', 'string'],
            'comment' => ['nullable', 'string'],
        ]);

        $session = TrainingSession::where('id', $sessionId)->where('student_id', $student->id)->first();
        if (! $session) {
            return response()->json(['error' => 'Sessão não encontrada'], 404);
        }
        $session->update(['status' => 'completed', 'finished_at' => now()]);

        Feedback::updateOrCreate(
            ['training_session_id' => $sessionId],
            [
                'effort_rpe' => $validated['effort_rpe'] ?? null,
                'satisfaction' => $validated['satisfaction'] ?? null,
                'discomfort' => trim($validated['discomfort'] ?? '') ?: null,
                'comment' => trim($validated['comment'] ?? '') ?: null,
            ]
        );

        return response()->json(['session' => $session]);
    }
}
