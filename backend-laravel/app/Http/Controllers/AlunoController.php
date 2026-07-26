<?php

namespace App\Http\Controllers;

use App\Models\BodyMeasurement;
use App\Models\Student;
use App\Support\Gamification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AlunoController extends Controller
{
    // Conta, por aluno, quantos exercícios não tiveram a carga máxima aumentada
    // entre as duas últimas sessões concluídas em que ele registrou peso.
    // Espelha contarEstagnacaoPorAluno() de backend/src/routes/alunos.ts do Node.
    private function contarEstagnacaoPorAluno(string $professionalId): array
    {
        $rows = DB::select(
            <<<'SQL'
            with cargas as (
              select ts.student_id, we.exercise_id, ts.id as session_id, ts.finished_at,
                     max(se.load_kg_done) as carga_max
              from session_entries se
              join training_sessions ts on ts.id = se.training_session_id
              join workout_exercises we on we.id = se.workout_exercise_id
              join students s on s.id = ts.student_id
              where s.professional_id = ? and ts.status = 'completed' and se.load_kg_done is not null
              group by ts.student_id, we.exercise_id, ts.id, ts.finished_at
            ),
            ranked as (
              select *, row_number() over (partition by student_id, exercise_id order by finished_at desc) as rn
              from cargas
            ),
            comparacao as (
              select student_id, exercise_id,
                     max(case when rn = 1 then carga_max end) as ultima,
                     max(case when rn = 2 then carga_max end) as anterior
              from ranked
              where rn <= 2
              group by student_id, exercise_id
              having max(case when rn = 2 then carga_max end) is not null
            )
            select student_id, sum(case when ultima <= anterior then 1 else 0 end) as estagnados
            from comparacao
            group by student_id
            SQL,
            [$professionalId]
        );

        return collect($rows)->mapWithKeys(fn ($r) => [$r->student_id => (int) $r->estagnados])->all();
    }

    // GET / — lista alunos do profissional autenticado, com último treino e status
    public function index(Request $request): JsonResponse
    {
        $professionalId = $request->user()->id;

        $students = DB::table('students as s')
            ->where('s.professional_id', $professionalId)
            ->orderByDesc('s.created_at')
            ->select('s.*')
            ->selectSub(
                DB::table('workouts')->select('name')
                    ->whereColumn('student_id', 's.id')
                    ->orderByDesc('created_at')->limit(1),
                'ultimo_treino'
            )
            ->selectSub(
                DB::table('training_sessions')
                    ->join('workouts', 'workouts.id', '=', 'training_sessions.workout_id')
                    ->whereColumn('workouts.student_id', 's.id')
                    ->where('training_sessions.status', 'completed')
                    ->selectRaw('count(*)'),
                'sessoes_concluidas'
            )
            ->selectSub(
                DB::table('training_sessions')
                    ->join('workouts', 'workouts.id', '=', 'training_sessions.workout_id')
                    ->whereColumn('workouts.student_id', 's.id')
                    ->where('training_sessions.status', 'completed')
                    ->selectRaw('max(training_sessions.finished_at)'),
                'ultima_sessao_em'
            )
            ->selectRaw(
                'exists(select 1 from workouts w where w.student_id = s.id and w.status = ?) as tem_treino_enviado',
                ['sent']
            )
            ->get();

        $estagnacao = $this->contarEstagnacaoPorAluno($professionalId);

        $students = $students->map(function ($s) use ($estagnacao) {
            $s->exercicios_sem_progresso = $estagnacao[$s->id] ?? 0;
            // par_q_answers é coluna json — select('s.*') via DB::table() puro não passa
            // pelo cast do model Student, então volta como string crua; decodifica manualmente
            // pra bater com o pg do Node (que já entrega json/jsonb parseado).
            $s->par_q_answers = $s->par_q_answers !== null ? json_decode($s->par_q_answers) : null;

            return $s;
        });

        return response()->json(['students' => $students]);
    }

    // POST / — cadastra aluno e gera link de convite (token)
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string'],
            'email' => ['nullable', 'string'],
            'phone' => ['nullable', 'string'],
            'objective' => ['nullable', 'string'],
            'weight_kg' => ['nullable', 'numeric'],
            'height_cm' => ['nullable', 'numeric'],
        ]);

        $student = Student::create([
            'professional_id' => $request->user()->id,
            'name' => trim($validated['name']),
            'email' => isset($validated['email']) ? trim($validated['email']) ?: null : null,
            'phone' => isset($validated['phone']) ? trim($validated['phone']) ?: null : null,
            'objective' => isset($validated['objective']) ? trim($validated['objective']) ?: null : null,
            'weight_kg' => $validated['weight_kg'] ?? null,
            'height_cm' => $validated['height_cm'] ?? null,
            'invite_token' => Str::random(14),
        ]);

        return response()->json(['student' => $student], 201);
    }

    // GET /:id — perfil do aluno + treinos
    public function show(Request $request, string $id): JsonResponse
    {
        $student = Student::where('id', $id)
            ->where('professional_id', $request->user()->id)
            ->first();

        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        $workouts = DB::table('workouts')
            ->where('student_id', $student->id)
            ->orderByDesc('created_at')
            ->get();

        $measurements = BodyMeasurement::where('student_id', $student->id)
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

        $alertasEstagnacao = DB::select(
            <<<'SQL'
            with cargas as (
              select we.exercise_id, ts.id as session_id, ts.finished_at,
                     max(se.load_kg_done) as carga_max
              from session_entries se
              join training_sessions ts on ts.id = se.training_session_id
              join workout_exercises we on we.id = se.workout_exercise_id
              where ts.student_id = ? and ts.status = 'completed' and se.load_kg_done is not null
              group by we.exercise_id, ts.id, ts.finished_at
            ),
            ranked as (
              select *, row_number() over (partition by exercise_id order by finished_at desc) as rn
              from cargas
            ),
            comparacao as (
              select exercise_id,
                     max(case when rn = 1 then carga_max end) as ultima,
                     max(case when rn = 2 then carga_max end) as anterior
              from ranked
              where rn <= 2
              group by exercise_id
              having max(case when rn = 2 then carga_max end) is not null
            )
            select c.exercise_id, e.name as exercise_name, c.ultima, c.anterior
            from comparacao c
            join exercises e on e.id = c.exercise_id
            where c.ultima <= c.anterior
            order by e.name
            SQL,
            [$student->id]
        );

        return response()->json([
            'student' => $student,
            'workouts' => $workouts,
            'measurements' => $measurements,
            'gamificacao' => $gamificacao,
            'alertasEstagnacao' => $alertasEstagnacao,
        ]);
    }
}
