<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStudentForProfessional;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Chat do lado do profissional — mesma tabela messages do portal do aluno, sem resposta de IA (isso só acontece quando quem escreve é o aluno). */
class AlunoChatController extends Controller
{
    use ResolvesStudentForProfessional;

    // GET /alunos/:id/mensagens — histórico do chat + estado do autopilot
    public function index(Request $request, string $id): JsonResponse
    {
        $student = $this->buscarAlunoDoProfissional($request, $id);
        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        $messages = Message::where('student_id', $student->id)->orderBy('created_at')->get();

        return response()->json(['messages' => $messages, 'ai_autopilot' => $student->ai_autopilot]);
    }

    // POST /alunos/:id/mensagens — profissional responde manualmente
    public function store(Request $request, string $id): JsonResponse
    {
        $student = $this->buscarAlunoDoProfissional($request, $id);
        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        $request->validate(['content' => ['required', 'string', 'max:4000']]);
        $conteudo = trim((string) $request->input('content'));
        if ($conteudo === '') {
            return response()->json(['error' => 'content é obrigatório'], 400);
        }

        $message = Message::create([
            'student_id' => $student->id,
            'professional_id' => $request->user()->id,
            'sender' => 'professional',
            'content' => $conteudo,
        ])->refresh();

        return response()->json(['message' => $message], 201);
    }

    // PATCH /alunos/:id/autopilot — liga/desliga a resposta automática da IA
    public function autopilot(Request $request, string $id): JsonResponse
    {
        $student = $this->buscarAlunoDoProfissional($request, $id);
        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        $validated = $request->validate(['enabled' => ['required', 'boolean']]);

        $student->update(['ai_autopilot' => $validated['enabled']]);

        return response()->json(['student' => $student->fresh()]);
    }
}
