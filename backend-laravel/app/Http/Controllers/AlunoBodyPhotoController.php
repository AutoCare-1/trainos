<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStudentForProfessional;
use App\Models\BodyPhoto;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Galeria de evolução física vista pelo profissional — só leitura (quem registra é o aluno, pelo portal). */
class AlunoBodyPhotoController extends Controller
{
    use ResolvesStudentForProfessional;

    // GET /alunos/:id/body-photos
    public function index(Request $request, string $id): JsonResponse
    {
        $student = $this->buscarAlunoDoProfissional($request, $id);
        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        $photos = BodyPhoto::where('student_id', $student->id)
            ->orderByDesc('taken_at')
            ->get(['id', 'student_id', 'taken_at', 'ai_feedback', 'compared_to_photo_id', 'created_at']);

        return response()->json(['photos' => $photos]);
    }

    // GET /alunos/:id/body-photos/:photoId/imagem — serve o arquivo (autenticado por JWT + dono do aluno)
    public function imagem(Request $request, string $id, string $photoId): JsonResponse|BinaryFileResponse
    {
        $student = $this->buscarAlunoDoProfissional($request, $id);
        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        $photo = BodyPhoto::where('id', $photoId)->where('student_id', $student->id)->first();
        if (! $photo) {
            return response()->json(['error' => 'Foto não encontrada'], 404);
        }

        return response()->file(Uploads::privateAbsolutePath($photo->file_path));
    }
}
