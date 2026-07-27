<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStudentByToken;
use App\Models\Message;
use App\Models\Student;
use App\Support\Chat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PortalChatController extends Controller
{
    use ResolvesStudentByToken;

    /** @return array{nome: string, objetivo: ?string, pesoKg: ?float, alturaCm: ?float, treinoAtual: ?string, exerciciosAtuais: string[], sessoesConcluidas: int, ultimoRpe: ?int} */
    private function montarContextoAluno(Student $student): array
    {
        $workout = DB::table('workouts')
            ->where('student_id', $student->id)
            ->where('status', 'sent')
            ->orderByDesc('sent_at')
            ->first();

        $exercicios = [];
        if ($workout) {
            $exercicios = DB::table('workout_exercises as we')
                ->join('exercises as e', 'e.id', '=', 'we.exercise_id')
                ->where('we.workout_id', $workout->id)
                ->orderBy('we.order_index')
                ->pluck('e.name')
                ->all();
        }

        $sessoesConcluidas = DB::table('training_sessions')
            ->where('student_id', $student->id)
            ->where('status', 'completed')
            ->count();

        $ultimoRpe = DB::table('feedbacks as f')
            ->join('training_sessions as ts', 'ts.id', '=', 'f.training_session_id')
            ->where('ts.student_id', $student->id)
            ->orderByDesc('f.created_at')
            ->value('f.effort_rpe');

        return [
            'nome' => $student->name,
            'objetivo' => $student->objective,
            'pesoKg' => $student->weight_kg !== null ? (float) $student->weight_kg : null,
            'alturaCm' => $student->height_cm !== null ? (float) $student->height_cm : null,
            'treinoAtual' => $workout->name ?? null,
            'exerciciosAtuais' => $exercicios,
            'sessoesConcluidas' => $sessoesConcluidas,
            'ultimoRpe' => $ultimoRpe !== null ? (int) $ultimoRpe : null,
        ];
    }

    // GET /:token/mensagens — histórico do chat do aluno
    public function index(string $token): JsonResponse
    {
        $student = $this->buscarAlunoPorToken($token);
        if (! $student) {
            return response()->json(['error' => 'Link inválido'], 404);
        }

        $messages = Message::where('student_id', $student->id)->orderBy('created_at')->get();

        // Verdade de "lido" no servidor (pro tipo de notificação mensagem_nao_lida) —
        // antes disso só existia como localStorage no navegador do aluno.
        Message::where('student_id', $student->id)
            ->whereIn('sender', ['ai', 'professional'])
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['messages' => $messages]);
    }

    // POST /:token/mensagens — aluno envia mensagem; IA responde se o autopilot estiver ligado
    public function store(Request $request, string $token): JsonResponse
    {
        $student = $this->buscarAlunoPorToken($token);
        if (! $student) {
            return response()->json(['error' => 'Link inválido'], 404);
        }

        $conteudo = trim((string) $request->input('content'));
        if ($conteudo === '') {
            return response()->json(['error' => 'content é obrigatório'], 400);
        }

        $mensagemAluno = Message::create([
            'student_id' => $student->id,
            'professional_id' => $student->professional_id,
            'sender' => 'student',
            'content' => $conteudo,
        ])->refresh();

        $respostaIa = null;
        if ($student->ai_autopilot) {
            try {
                $historico = Message::where('student_id', $student->id)
                    ->orderByDesc('created_at')
                    ->limit(30)
                    ->get()
                    ->sortBy('created_at')
                    ->values();

                $contexto = $this->montarContextoAluno($student);
                $texto = Chat::responderComoPersonal($historico, $contexto);

                $respostaIa = Message::create([
                    'student_id' => $student->id,
                    'professional_id' => $student->professional_id,
                    'sender' => 'ai',
                    'content' => $texto,
                ])->refresh();
            } catch (\Throwable $e) {
                // IA fora do ar não pode impedir o registro da mensagem do aluno —
                // o profissional responde manualmente depois.
                Log::error('[Chat IA] Falha ao gerar resposta automática: '.$e->getMessage());
            }
        }

        return response()->json(['message' => $mensagemAluno, 'aiReply' => $respostaIa], 201);
    }
}
