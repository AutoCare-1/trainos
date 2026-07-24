<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStudentByToken;
use App\Models\BodyPhoto;
use App\Support\EvolucaoFisica;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PortalBodyPhotoController extends Controller
{
    use ResolvesStudentByToken;

    // GET /:token/body-photos — galeria de fotos de evolução do aluno, mais recente primeiro
    public function index(string $token): JsonResponse
    {
        $student = $this->buscarAlunoPorToken($token);
        if (! $student) {
            return response()->json(['error' => 'Link inválido'], 404);
        }

        $photos = BodyPhoto::where('student_id', $student->id)
            ->orderByDesc('taken_at')
            ->get(['id', 'student_id', 'taken_at', 'ai_feedback', 'compared_to_photo_id', 'created_at']);

        return response()->json(['photos' => $photos]);
    }

    // POST /:token/body-photos — o aluno registra uma nova foto; dispara comentário da Coach IA
    public function store(Request $request, string $token): JsonResponse
    {
        $student = $this->buscarAlunoPorToken($token);
        if (! $student) {
            return response()->json(['error' => 'Link inválido'], 404);
        }

        $request->validate(['foto' => ['required', 'file', 'mimetypes:image/*', 'max:15360']]);

        $filePath = Uploads::storePrivate($request->file('foto'), 'body-photos', $token);
        $caminhoAbsolutoNova = Uploads::privateAbsolutePath($filePath);

        $anterior = BodyPhoto::where('student_id', $student->id)
            ->orderByDesc('taken_at')
            ->first(['id', 'file_path']);

        try {
            $aiFeedback = $anterior
                ? EvolucaoFisica::compararEvolucaoFisica($student->name, Uploads::privateAbsolutePath($anterior->file_path), $caminhoAbsolutoNova)
                : EvolucaoFisica::comentarPrimeiraFoto($student->name, $caminhoAbsolutoNova);
        } catch (\Throwable $e) {
            // A IA fora do ar não pode impedir o registro da foto — o comentário
            // fica em branco e o aluno vê a foto normalmente.
            Log::error('[Evolução física] Falha ao gerar comentário da IA: '.$e->getMessage());
            $aiFeedback = $anterior
                ? 'Foto registrada! Em breve o comentário da Coach IA aparece por aqui.'
                : 'Primeira foto registrada! Esse é o seu ponto de partida — daqui pra frente dá pra acompanhar sua evolução de verdade.';
        }

        $photo = BodyPhoto::create([
            'student_id' => $student->id,
            'file_path' => $filePath,
            'ai_feedback' => $aiFeedback,
            'compared_to_photo_id' => $anterior?->id,
        ]);

        return response()->json(['photo' => $photo->makeHidden('file_path')], 201);
    }

    // GET /:token/body-photos/:photoId/imagem — serve o arquivo (autenticado pelo token do aluno)
    public function imagem(string $token, string $photoId): JsonResponse|BinaryFileResponse
    {
        $student = $this->buscarAlunoPorToken($token);
        if (! $student) {
            return response()->json(['error' => 'Link inválido'], 404);
        }

        $photo = BodyPhoto::where('id', $photoId)->where('student_id', $student->id)->first();
        if (! $photo) {
            return response()->json(['error' => 'Foto não encontrada'], 404);
        }

        return response()->file(Uploads::privateAbsolutePath($photo->file_path));
    }
}
