<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStudentForProfessional;
use App\Models\PosturalAssessment;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Histórico de avaliação postural visto pelo profissional — só leitura (quem registra é o aluno, pelo portal). */
class AlunoPosturalController extends Controller
{
    use ResolvesStudentForProfessional;

    // GET /alunos/:id/postural
    public function index(Request $request, string $id): JsonResponse
    {
        $student = $this->buscarAlunoDoProfissional($request, $id);
        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        $avaliacoes = PosturalAssessment::where('student_id', $student->id)
            ->orderByDesc('taken_at')
            ->get(['id', 'student_id', 'taken_at', 'ai_feedback', 'compared_to_assessment_id', 'created_at']);

        return response()->json(['assessments' => $avaliacoes]);
    }

    // GET /alunos/:id/postural/:assessmentId/imagem/:angulo — serve o arquivo (autenticado por JWT + dono do aluno)
    public function imagem(Request $request, string $id, string $assessmentId, string $angulo): JsonResponse|BinaryFileResponse
    {
        $student = $this->buscarAlunoDoProfissional($request, $id);
        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        $avaliacao = PosturalAssessment::where('id', $assessmentId)->where('student_id', $student->id)->first();
        if (! $avaliacao) {
            return response()->json(['error' => 'Avaliação não encontrada'], 404);
        }

        $caminho = match ($angulo) {
            'frente' => $avaliacao->front_photo_path,
            'lado' => $avaliacao->side_photo_path,
            'costas' => $avaliacao->back_photo_path,
            default => null,
        };
        if (! $caminho) {
            return response()->json(['error' => 'Ângulo inválido'], 400);
        }

        return response()->file(Uploads::privateAbsolutePath($caminho));
    }
}
