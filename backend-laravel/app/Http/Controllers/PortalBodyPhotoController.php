<?php

namespace App\Http\Controllers;

use App\Models\BodyPhoto;
use App\Support\ErrorReporting;
use App\Support\EvolucaoFisica;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PortalBodyPhotoController extends Controller
{
    // GET /:token/body-photos — galeria de fotos de evolução do aluno, mais recente primeiro
    public function index(Request $request, string $token): JsonResponse
    {
        $student = $this->alunoDoPortal($request);

        $photos = BodyPhoto::where('student_id', $student->id)
            ->orderByDesc('taken_at')
            ->get(['id', 'student_id', 'taken_at', 'ai_feedback', 'compared_to_photo_id', 'created_at']);

        return response()->json(['photos' => $photos]);
    }

    // POST /:token/body-photos — o aluno registra uma nova foto; dispara comentário da Coach IA
    public function store(Request $request, string $token): JsonResponse
    {
        $student = $this->alunoDoPortal($request);

        $request->validate(['foto' => ['required', 'file', 'mimetypes:image/*', 'max:15360']]);

        $filePath = Uploads::storePrivate($request->file('foto'), 'body-photos', $token);
        $caminhoAbsolutoNova = Uploads::privateAbsolutePath($filePath);

        $anterior = BodyPhoto::where('student_id', $student->id)
            ->orderByDesc('taken_at')
            ->first(['id', 'file_path']);

        $comentarioIndisponivel = $anterior
            ? 'Foto registrada! Em breve o comentário da Coach IA aparece por aqui.'
            : 'Primeira foto registrada! Esse é o seu ponto de partida — daqui pra frente dá pra acompanhar sua evolução de verdade.';

        // Registrar a foto nunca pode falhar por causa da IA — nem quando ela está
        // fora do ar (catch abaixo), nem quando foi desligada de propósito
        // (kill-switch): os dois casos caem no mesmo comentário-placeholder.
        if (config('ia_pipelines.evolucao_fisica') === false) {
            $aiFeedback = $comentarioIndisponivel;
        } else {
            try {
                $aiFeedback = $anterior
                    ? EvolucaoFisica::compararEvolucaoFisica($student->name, Uploads::privateAbsolutePath($anterior->file_path), $caminhoAbsolutoNova, $student->professional_id)
                    : EvolucaoFisica::comentarPrimeiraFoto($student->name, $caminhoAbsolutoNova, $student->professional_id);
            } catch (\Throwable $e) {
                ErrorReporting::capturarFalhaIa('evolucao_fisica', $e, ['student_id' => $student->id]);
                $aiFeedback = $comentarioIndisponivel;
            }
        }

        $photo = BodyPhoto::create([
            'student_id' => $student->id,
            'file_path' => $filePath,
            'ai_feedback' => $aiFeedback,
            'compared_to_photo_id' => $anterior?->id,
        ])->refresh();

        return response()->json(['photo' => $photo->makeHidden('file_path')], 201);
    }

    // GET /:token/body-photos/:photoId/imagem — serve o arquivo (autenticado pelo token do aluno)
    public function imagem(Request $request, string $token, string $photoId): JsonResponse|BinaryFileResponse
    {
        $student = $this->alunoDoPortal($request);

        $photo = BodyPhoto::where('id', $photoId)->where('student_id', $student->id)->first();
        if (! $photo) {
            return response()->json(['error' => 'Foto não encontrada'], 404);
        }

        return response()->file(Uploads::privateAbsolutePath($photo->file_path));
    }
}
