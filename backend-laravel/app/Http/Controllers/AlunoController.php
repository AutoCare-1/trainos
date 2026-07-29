<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAlunoRequest;
use App\Http\Requests\UpdateAlunoRequest;
use App\Models\BodyMeasurement;
use App\Models\Exercise;
use App\Models\Student;
use App\Models\StudentBillingPlan;
use App\Support\Estagnacao;
use App\Support\Gamification;
use App\Support\Money;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AlunoController extends Controller
{
    /**
     * Aplica a cobrança informada (se veio alguma) sem nunca dar UPDATE em
     * monthly_value/billing_type — fecha o plano vigente (ends_on = hoje) e cria
     * um novo (starts_on = hoje) só quando o valor/tipo realmente muda, pra
     * preservar a receita já calculada de meses anteriores. Reenviar o mesmo
     * valor não cria linha de histórico redundante.
     */
    private function aplicarCobranca(Student $student, ?string $billingType, mixed $monthlyValue): void
    {
        if ($billingType === null || $monthlyValue === null) {
            return;
        }

        $planoVigente = StudentBillingPlan::where('student_id', $student->id)->whereNull('ends_on')->first();

        $mudou = ! $planoVigente
            || $planoVigente->billing_type !== $billingType
            || Money::toCents($planoVigente->monthly_value) !== Money::toCents($monthlyValue);

        if (! $mudou) {
            return;
        }

        $hoje = now()->toDateString();

        if ($planoVigente) {
            $planoVigente->update(['ends_on' => $hoje]);
        }

        StudentBillingPlan::create([
            'student_id' => $student->id,
            'professional_id' => $student->professional_id,
            'billing_type' => $billingType,
            'monthly_value' => $monthlyValue,
            'starts_on' => $hoje,
        ]);
    }

    // Conta, por aluno, quantos exercícios não bateram o melhor resultado das
    // últimas sessões (janela de App\Support\Estagnacao::JANELA_PADRAO).
    // Consolidado em App\Support\Estagnacao — mesma fonte de verdade usada pela
    // notificação push estagnacao_detectada, pra nunca dar veredito divergente.
    private function contarEstagnacaoPorAluno(string $professionalId): array
    {
        $comparacoes = Estagnacao::compararUltimasSessoes($professionalId);

        $porAluno = [];
        foreach ($comparacoes as $c) {
            $porAluno[$c['student_id']] ??= 0;
            if ($c['ultima'] <= $c['anterior']) {
                $porAluno[$c['student_id']]++;
            }
        }

        return $porAluno;
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
            // par_q_answers/anamnese são colunas json — select('s.*') via DB::table() puro
            // não passa pelo cast do model Student, então voltam como string crua;
            // decodifica manualmente pra bater com o pg do Node (que já entrega json/jsonb parseado).
            $s->par_q_answers = $s->par_q_answers !== null ? json_decode($s->par_q_answers) : null;
            $s->anamnese = $s->anamnese !== null ? json_decode($s->anamnese) : null;

            return $s;
        });

        return response()->json(['students' => $students]);
    }

    // POST / — cadastra aluno e gera link de convite (token)
    public function store(StoreAlunoRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $professionalId = $request->user()->id;

        $student = DB::transaction(function () use ($validated, $professionalId) {
            $student = Student::create([
                'professional_id' => $professionalId,
                'name' => trim($validated['name']),
                'email' => isset($validated['email']) ? trim($validated['email']) ?: null : null,
                'phone' => isset($validated['phone']) ? trim($validated['phone']) ?: null : null,
                'objective' => isset($validated['objective']) ? trim($validated['objective']) ?: null : null,
                'weight_kg' => $validated['weight_kg'] ?? null,
                'height_cm' => $validated['height_cm'] ?? null,
                'invite_token' => Str::random(14),
            ])->refresh();

            $this->aplicarCobranca($student, $validated['billing_type'] ?? null, $validated['monthly_value'] ?? null);

            return $student;
        });

        return response()->json(['student' => $student], 201);
    }

    // PATCH /:id — edita dados do aluno (não existia edição até então); aplica a
    // regra de fechar/criar plano de cobrança se billing_type/monthly_value vierem
    public function update(UpdateAlunoRequest $request, string $id): JsonResponse
    {
        $professionalId = $request->user()->id;
        $validated = $request->validated();

        $student = Student::where('id', $id)->where('professional_id', $professionalId)->first();
        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        DB::transaction(function () use ($student, $validated) {
            $camposBasicos = collect($validated)->only(['email', 'phone', 'objective', 'weight_kg', 'height_cm'])->all();
            if (isset($validated['name'])) {
                $camposBasicos['name'] = trim($validated['name']);
            }
            if (array_key_exists('email', $camposBasicos)) {
                $camposBasicos['email'] = $camposBasicos['email'] ? trim($camposBasicos['email']) ?: null : null;
            }
            if (array_key_exists('phone', $camposBasicos)) {
                $camposBasicos['phone'] = $camposBasicos['phone'] ? trim($camposBasicos['phone']) ?: null : null;
            }
            if (array_key_exists('objective', $camposBasicos)) {
                $camposBasicos['objective'] = $camposBasicos['objective'] ? trim($camposBasicos['objective']) ?: null : null;
            }

            if (! empty($camposBasicos)) {
                $student->update($camposBasicos);
            }

            $this->aplicarCobranca($student, $validated['billing_type'] ?? null, $validated['monthly_value'] ?? null);
        });

        return response()->json(['student' => $student->fresh()]);
    }

    // PATCH /:id/lembrete-pagamento — liga/desliga o lembrete de pagamento na
    // notificação de "treino venceu" do aluno (ver TreinoVencidoAlunoRule).
    // Fica no aluno, não no treino: cobrança e vencimento de treino são sistemas
    // independentes, então quem decide se faz sentido juntar os dois é o personal.
    public function lembretePagamento(Request $request, string $id): JsonResponse
    {
        $professionalId = $request->user()->id;
        $student = Student::where('id', $id)->where('professional_id', $professionalId)->first();
        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        $validated = $request->validate(['enabled' => ['required', 'boolean']]);

        $student->update(['lembrar_pagamento_vencimento' => $validated['enabled']]);

        return response()->json(['student' => $student->fresh()]);
    }

    // PATCH /:id/cobranca/encerrar — para a cobrança do aluno de contar a partir de
    // hoje (ou da data informada). Sem isso, um plano vigente nunca para de entrar
    // na receita mensal, mesmo que o aluno cancele/saia — mesmo padrão do
    // "encerrar" de GastoController.
    public function encerrarCobranca(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'ends_on' => ['nullable', 'date'],
        ]);

        $student = Student::where('id', $id)->where('professional_id', $request->user()->id)->first();
        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        $plano = StudentBillingPlan::where('student_id', $student->id)->whereNull('ends_on')->first();
        if (! $plano) {
            return response()->json(['error' => 'Este aluno não tem cobrança vigente'], 400);
        }

        $endsOn = $validated['ends_on'] ?? now()->toDateString();
        if ($endsOn < $plano->starts_on->toDateString()) {
            return response()->json(['error' => 'A data de encerramento não pode ser antes do início do plano'], 422);
        }

        $plano->update(['ends_on' => $endsOn]);

        return response()->json(['billing_plan' => $plano]);
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

        // Mesma fonte de verdade de contarEstagnacaoPorAluno() e da notificação
        // push estagnacao_detectada — ver App\Support\Estagnacao.
        $comparacoesEstagnadas = collect(Estagnacao::compararUltimasSessoes($student->professional_id))
            ->filter(fn ($c) => $c['student_id'] === $student->id && $c['ultima'] <= $c['anterior']);
        $nomesExercicios = Exercise::whereIn('id', $comparacoesEstagnadas->pluck('exercise_id'))->pluck('name', 'id');
        $alertasEstagnacao = $comparacoesEstagnadas
            ->map(fn ($c) => [
                'exercise_id' => $c['exercise_id'],
                'exercise_name' => $nomesExercicios[$c['exercise_id']] ?? null,
                'ultima' => $c['ultima'],
                'anterior' => $c['anterior'],
            ])
            ->sortBy('exercise_name')
            ->values();

        $planoCobranca = StudentBillingPlan::where('student_id', $student->id)->whereNull('ends_on')->first();

        return response()->json([
            'student' => $student,
            'workouts' => $workouts,
            'measurements' => $measurements,
            'gamificacao' => $gamificacao,
            'alertasEstagnacao' => $alertasEstagnacao,
            'billing_plan' => $planoCobranca,
        ]);
    }

    // POST /:id/medicoes — registra uma nova medição (peso/cintura/quadril/%gordura) do aluno
    public function postMedicao(Request $request, string $id): JsonResponse
    {
        $student = Student::where('id', $id)->where('professional_id', $request->user()->id)->first();
        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        $validated = $request->validate([
            'weight_kg' => ['required', 'numeric', 'min:0.1'],
            'waist_cm' => ['nullable', 'numeric', 'min:0.1'],
            'hip_cm' => ['nullable', 'numeric', 'min:0.1'],
            'body_fat_pct' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'recorded_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $measurement = BodyMeasurement::create([
            'student_id' => $student->id,
            'recorded_at' => $validated['recorded_at'] ?? now()->toDateString(),
            'weight_kg' => $validated['weight_kg'],
            'waist_cm' => $validated['waist_cm'] ?? null,
            'hip_cm' => $validated['hip_cm'] ?? null,
            'body_fat_pct' => $validated['body_fat_pct'] ?? null,
            'notes' => isset($validated['notes']) ? trim($validated['notes']) ?: null : null,
        ])->refresh();

        return response()->json(['measurement' => $measurement], 201);
    }

    // PATCH /:id/avaliacao — atualiza anamnese de saúde (PAR-Q resumido) do aluno
    public function updateAvaliacao(Request $request, string $id): JsonResponse
    {
        $student = Student::where('id', $id)->where('professional_id', $request->user()->id)->first();
        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        $validated = $request->validate([
            'par_q_answers' => ['nullable', 'array'],
            'health_notes' => ['nullable', 'string'],
            'birth_date' => ['nullable', 'date'],
            'anamnese' => ['nullable', 'array'],
        ]);

        $student->update([
            'par_q_answers' => $validated['par_q_answers'] ?? null,
            'health_notes' => isset($validated['health_notes']) ? trim($validated['health_notes']) ?: null : null,
            'birth_date' => $validated['birth_date'] ?? null,
            'anamnese' => $validated['anamnese'] ?? null,
        ]);

        return response()->json(['student' => $student->fresh()]);
    }

    // POST /:id/foto — profissional envia a foto do aluno (fallback, caso o aluno não tenha feito isso)
    public function uploadFoto(Request $request, string $id): JsonResponse
    {
        $student = Student::where('id', $id)->where('professional_id', $request->user()->id)->first();
        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        $request->validate(['foto' => ['required', 'file', 'mimetypes:image/*', 'max:10240']]);

        $photoUrl = Uploads::storePublic($request->file('foto'), 'student-photos');
        $student->update(['photo_url' => $photoUrl]);

        return response()->json(['student' => $student->fresh()]);
    }
}
