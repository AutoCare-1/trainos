<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\SessionEntry;
use App\Models\TrainingSession;
use App\Models\Workout;
use App\Models\WorkoutExercise;
use App\Models\WorkoutReview;
use App\Support\Gamification;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PortalController extends Controller
{
    // GET /:token — dados do aluno + treino selecionado (?workout_id=, senão o mais
    // recente não-arquivado) + lista dos treinos disponíveis pro aluno escolher
    public function show(Request $request, string $token): JsonResponse
    {
        $student = $this->alunoDoPortal($request);

        // Só treinos não-arquivados entram na lista de escolha — arquivar é decisão
        // manual do personal (nunca automática por expirar), então um treino vencido
        // continua escolhível até o personal decidir arquivá-lo.
        $workoutsDisponiveis = DB::table('workouts')
            ->where('student_id', $student->id)
            ->where('status', 'sent')
            ->whereNull('archived_at')
            ->orderByDesc('sent_at')
            ->get(['id', 'name', 'sent_at', 'expires_at']);

        $workoutIdSelecionado = $request->query('workout_id');
        $workout = $workoutIdSelecionado
            ? $workoutsDisponiveis->firstWhere('id', $workoutIdSelecionado)
            : null;
        // workout_id inválido/de outro aluno ou nenhum informado -> cai no mais recente.
        $workout ??= $workoutsDisponiveis->first();

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

        // Treino vencido (expires_at já passou), não-arquivado e ainda sem revisão —
        // bloqueia o portal com a anamnese de revisão até o aluno responder, mesmo
        // modelo do onboardingCompleted. Arquivar sem revisar (personal decidiu que
        // não precisa) não conta como pendente.
        $revisaoPendente = DB::table('workouts as w')
            ->leftJoin('workout_reviews as wr', 'wr.workout_id', '=', 'w.id')
            ->where('w.student_id', $student->id)
            ->where('w.status', 'sent')
            ->whereNull('w.archived_at')
            ->whereNotNull('w.expires_at')
            ->where('w.expires_at', '<=', now()->toDateString())
            ->whereNull('wr.id')
            ->orderBy('w.expires_at')
            ->select('w.id', 'w.name')
            ->first();

        return response()->json([
            'student' => [
                'id' => $student->id, 'name' => $student->name,
                'objective' => $student->objective, 'photo_url' => $student->photo_url,
            ],
            'workouts' => $workoutsDisponiveis->values(),
            'workout' => $workout,
            'exercises' => $exercises,
            'activeSessionId' => $activeSession->id ?? null,
            'registeredCounts' => $registeredCounts,
            'measurements' => $measurements,
            'gamificacao' => $gamificacao,
            'desafio' => $desafioAtivo ? array_merge((array) $desafioAtivo, ['leaderboard' => $leaderboard]) : null,
            'onboardingCompleted' => $student->onboarding_completed_at !== null,
            'revisaoPendente' => $revisaoPendente ? ['workout_id' => $revisaoPendente->id, 'workout_name' => $revisaoPendente->name] : null,
        ]);
    }

    // POST /:token/avaliacao — o próprio aluno responde a avaliação de saúde (PAR-Q) no primeiro acesso
    public function avaliacao(Request $request, string $token): JsonResponse
    {
        $student = $this->alunoDoPortal($request);

        // Onboarding é de uma vez só. Sem essa trava, reenviar o POST
        // sobrescrevia a anamnese inteira do aluno (inclusive o PAR-Q) por
        // qualquer um com o link — e o link circula por WhatsApp. Editar
        // depois é pelo personal, em PATCH /alunos/{id}/avaliacao.
        if ($student->onboarding_completed_at !== null) {
            return response()->json(['error' => 'Sua avaliação já foi respondida.'], 409);
        }

        $validated = $request->validate([
            'par_q_answers' => ['required', 'array'],
            'health_notes' => ['nullable', 'string'],
            'birth_date' => ['nullable', 'date'],
            'anamnese' => ['nullable', 'array'],
        ]);

        $student->update([
            'par_q_answers' => $validated['par_q_answers'],
            'health_notes' => trim($validated['health_notes'] ?? '') ?: null,
            'birth_date' => $validated['birth_date'] ?? null,
            'anamnese' => $validated['anamnese'] ?? null,
            'onboarding_completed_at' => now(),
        ]);

        return response()->json(['onboardingCompleted' => true], 201);
    }

    // POST /:token/revisao — o aluno responde a anamnese de revisão quando um
    // treino com prazo definido vence (ver PortalController::show, revisaoPendente)
    public function revisao(Request $request, string $token): JsonResponse
    {
        $student = $this->alunoDoPortal($request);

        $validated = $request->validate([
            'workout_id' => ['required', 'string'],
            'respostas' => ['required', 'array'],
            'respostas.avaliacao_treino' => ['nullable', 'string', 'max:50'],
            'respostas.gostou_mais' => ['nullable', 'string', 'max:2000'],
            'respostas.nao_gostou' => ['nullable', 'string', 'max:2000'],
            'respostas.percebeu_evolucao' => ['nullable', 'string', 'max:50'],
            'respostas.aspectos_progresso' => ['nullable', 'array'],
            'respostas.aspectos_progresso.*' => ['string', 'max:50'],
            'respostas.aspectos_progresso_outro' => ['nullable', 'string', 'max:2000'],
            'respostas.manteve_frequencia' => ['nullable', 'string', 'max:50'],
            'respostas.treinos_por_semana' => ['nullable', 'max:20'],
            'respostas.dificuldade_rotina' => ['nullable', 'string', 'max:2000'],
            'respostas.sugestao_melhoria' => ['nullable', 'string', 'max:2000'],
            'respostas.sugestao_modalidade' => ['nullable', 'string', 'max:2000'],
            'respostas.sugestao_geral' => ['nullable', 'string', 'max:2000'],
        ]);

        $workout = Workout::where('id', $validated['workout_id'])
            ->where('student_id', $student->id)
            ->first();
        if (! $workout) {
            return response()->json(['error' => 'Treino não encontrado'], 404);
        }

        if (WorkoutReview::where('workout_id', $workout->id)->exists()) {
            return response()->json(['error' => 'Esse treino já tem uma revisão registrada'], 409);
        }

        // Student não tem cast de created_at (coluna preenchida pelo useCurrent() do
        // banco, não pelo Eloquent) — vem como string crua, precisa de parse manual.
        $semanas = (int) round(Carbon::parse($student->created_at)->diffInDays(now()) / 7);

        $revisao = WorkoutReview::create([
            'student_id' => $student->id,
            'workout_id' => $workout->id,
            'tempo_acompanhamento_semanas' => $semanas,
            'respostas' => $validated['respostas'],
        ])->refresh();

        return response()->json(['review' => $revisao], 201);
    }

    // POST /:token/foto — o próprio aluno envia sua foto de perfil
    public function foto(Request $request, string $token): JsonResponse
    {
        $student = $this->alunoDoPortal($request);

        $request->validate(['foto' => ['required', 'file', 'mimetypes:image/*', 'max:10240']]);

        $photoUrl = Uploads::storePublic($request->file('foto'), 'student-photos');
        $student->update(['photo_url' => $photoUrl]);

        return response()->json(['photoUrl' => $photoUrl], 201);
    }

    // POST /:token/sessoes — inicia (ou retoma) uma sessão de execução do treino
    public function iniciarSessao(Request $request, string $token): JsonResponse
    {
        $student = $this->alunoDoPortal($request);

        $validated = $request->validate(['workout_id' => ['required', 'string']]);

        // Confere que o treino é do próprio aluno antes de vincular a sessão a ele
        // — sem isso, um workout_id de outro aluno seria aceito sem checagem de dono.
        $treino = Workout::where('id', $validated['workout_id'])
            ->where('student_id', $student->id)
            ->first();
        if (! $treino) {
            return response()->json(['error' => 'Treino não encontrado'], 404);
        }

        // Lock a nível de linha (dentro de uma transação) fecha a janela entre
        // checar "já existe sessão em andamento?" e criar uma nova — sem isso,
        // duas requisições quase simultâneas (dois dispositivos, ou retry de
        // rede) podiam criar duas sessões in_progress pro mesmo treino.
        $session = DB::transaction(function () use ($treino, $student) {
            $existente = TrainingSession::where('workout_id', $treino->id)
                ->where('student_id', $student->id)
                ->where('status', 'in_progress')
                ->lockForUpdate()
                ->first();
            // Retomar vale sempre: se o personal arquivou o treino com o aluno
            // no meio da execução, deixá-lo preso na academia sem conseguir
            // continuar seria pior que qualquer coisa que a trava abaixo evita.
            if ($existente) {
                return $existente;
            }

            // Começar do zero, não: rascunho que o personal ainda não terminou
            // de montar, ou treino já arquivado, não deve virar sessão nova. O
            // portal não oferece o botão, mas o endpoint aceitava o id direto.
            if ($treino->status !== 'sent' || $treino->archived_at !== null) {
                return null;
            }

            return TrainingSession::create([
                'workout_id' => $treino->id,
                'student_id' => $student->id,
            ])->refresh();
        });

        if (! $session) {
            return response()->json(['error' => 'Esse treino não está disponível pra treinar agora.'], 409);
        }

        return response()->json(['session' => $session], $session->wasRecentlyCreated ? 201 : 200);
    }

    // POST /:token/sessoes/:sessionId/registros — registra uma série executada
    public function registrarSerie(Request $request, string $token, string $sessionId): JsonResponse
    {
        $student = $this->alunoDoPortal($request);

        $validated = $request->validate([
            'workout_exercise_id' => ['required', 'string'],
            'set_number' => ['required', 'integer', 'min:1'],
            'reps_done' => ['nullable', 'integer'],
            'load_kg_done' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string'],
            'client_entry_id' => ['nullable', 'uuid'],
        ]);

        $session = TrainingSession::where('id', $sessionId)->where('student_id', $student->id)->first();
        if (! $session) {
            return response()->json(['error' => 'Sessão não encontrada'], 404);
        }

        // Série que veio da fila offline: o ID é gerado no cliente, então o
        // mesmo registro pode ser despachado mais de uma vez (retry depois de
        // timeout, ou app reaberto no meio da sincronização). Devolver a entry
        // já gravada mantém a fila idempotente sem depender do set_number —
        // que offline pode ser recalculado errado se o aluno registrar séries
        // em dois dispositivos.
        if (! empty($validated['client_entry_id'])) {
            $jaEnviada = SessionEntry::where('client_entry_id', $validated['client_entry_id'])->first();
            if ($jaEnviada) {
                // Mesmo ID apontando pra outra sessão não é retry — é colisão
                // (ou cliente adulterado). Não pode sobrescrever nem vazar a
                // entry de outra sessão.
                if ($jaEnviada->training_session_id !== $sessionId) {
                    return response()->json(['error' => 'Registro já usado em outra sessão'], 409);
                }

                return response()->json(['entry' => $jaEnviada, 'isPr' => $jaEnviada->is_pr]);
            }
        }

        // Confere que o exercício realmente pertence ao treino desta sessão —
        // sem isso, um workout_exercise_id de outro treino/aluno seria aceito
        // sem checagem de dono.
        $pertenceAoTreino = WorkoutExercise::where('id', $validated['workout_exercise_id'])
            ->where('workout_id', $session->workout_id)
            ->exists();
        if (! $pertenceAoTreino) {
            return response()->json(['error' => 'Exercício não encontrado neste treino'], 404);
        }

        // Se a mesma série já foi registrada (double-tap no botão, ou retry de
        // rede), devolve a entry existente em vez de duplicar — a constraint
        // única em (training_session_id, workout_exercise_id, set_number)
        // garante isso mesmo sob concorrência real (duas requisições simultâneas).
        $existente = SessionEntry::where('training_session_id', $sessionId)
            ->where('workout_exercise_id', $validated['workout_exercise_id'])
            ->where('set_number', $validated['set_number'])
            ->first();
        if ($existente) {
            return response()->json(['entry' => $existente, 'isPr' => $existente->is_pr]);
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
            'client_entry_id' => $validated['client_entry_id'] ?? null,
        ])->refresh();

        return response()->json(['entry' => $entry, 'isPr' => $isPr], 201);
    }

    // POST /:token/sessoes/:sessionId/concluir — finaliza a sessão + feedback pós-treino
    public function concluirSessao(Request $request, string $token, string $sessionId): JsonResponse
    {
        $student = $this->alunoDoPortal($request);

        $validated = $request->validate([
            // 0-10 porque é assim que o RPE entra no system prompt do coach IA
            // ("RPE 0-10") e nos gráficos de evolução — valor fora da faixa
            // envenena os dois sem nunca dar erro.
            'effort_rpe' => ['nullable', 'integer', 'min:0', 'max:10'],
            'satisfaction' => ['nullable', 'integer', 'min:0', 'max:10'],
            'discomfort' => ['nullable', 'string'],
            'comment' => ['nullable', 'string'],
            'finished_at' => ['nullable', 'date'],
        ]);

        $session = TrainingSession::where('id', $sessionId)->where('student_id', $student->id)->first();
        if (! $session) {
            return response()->json(['error' => 'Sessão não encontrada'], 404);
        }

        // Concluir sessão também entra na fila offline, então pode chegar duas
        // vezes. Reescrever finished_at no reenvio moveria a sessão pro dia da
        // sincronização — o que distorce streak, gamificação e a ordenação que
        // Estagnacao/Progressao usam pra achar "a última sessão". Mantém a data
        // original; só o feedback continua sendo atualizável.
        if ($session->status !== 'completed') {
            $session->update([
                'status' => 'completed',
                'finished_at' => $this->momentoDaConclusao($validated['finished_at'] ?? null),
            ]);
        }

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

    /**
     * Quando o treino foi de fato concluído.
     *
     * A fila offline pode despachar horas depois (o aluno treina às 7h sem
     * sinal na academia e o celular só sincroniza à noite), e now() gravava o
     * horário da SINCRONIZAÇÃO. Treino de domingo despachado na segunda
     * quebrava o streak — que é a métrica da gamificação.
     *
     * O horário do cliente não é confiável (relógio errado, adiantado de
     * propósito pra forjar streak), então só é aceito dentro de uma janela
     * defensável: nada no futuro, nada mais velho que 7 dias.
     */
    private function momentoDaConclusao(?string $informado): \Illuminate\Support\Carbon
    {
        if ($informado === null) {
            return now();
        }

        try {
            $momento = \Illuminate\Support\Carbon::parse($informado);
        } catch (\Throwable) {
            return now();
        }

        if ($momento->isFuture() || $momento->lt(now()->subDays(7))) {
            return now();
        }

        return $momento;
    }
}
