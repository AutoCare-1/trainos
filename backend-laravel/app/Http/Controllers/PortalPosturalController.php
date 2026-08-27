<?php

namespace App\Http\Controllers;

use App\Models\PosturalAssessment;
use App\Support\AvaliacaoPostural;
use App\Support\ErrorReporting;
use App\Support\KillSwitchIa;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PortalPosturalController extends Controller
{
    // GET /:token/postural — histórico de avaliações posturais do aluno, mais recente primeiro
    public function index(Request $request, string $token): JsonResponse
    {
        $student = $this->alunoDoPortal($request);

        $avaliacoes = PosturalAssessment::where('student_id', $student->id)
            ->orderByDesc('taken_at')
            ->get(['id', 'student_id', 'taken_at', 'ai_feedback', 'compared_to_assessment_id', 'created_at']);

        return response()->json(['assessments' => $avaliacoes]);
    }

    // POST /:token/postural — o aluno envia as 3 fotos (frente/lado/costas); dispara avaliação da Coach IA
    public function store(Request $request, string $token): JsonResponse
    {
        if ($resp = KillSwitchIa::verificar('avaliacao_postural')) {
            return $resp;
        }

        $student = $this->alunoDoPortal($request);

        $request->validate([
            'foto_frente' => ['required', 'file', 'mimetypes:image/*', 'max:15360'],
            'foto_lado' => ['required', 'file', 'mimetypes:image/*', 'max:15360'],
            'foto_costas' => ['required', 'file', 'mimetypes:image/*', 'max:15360'],
        ]);

        $frentePath = Uploads::storePrivate($request->file('foto_frente'), 'postural', $token);
        $ladoPath = Uploads::storePrivate($request->file('foto_lado'), 'postural', $token);
        $costasPath = Uploads::storePrivate($request->file('foto_costas'), 'postural', $token);

        $frenteAbs = Uploads::privateAbsolutePath($frentePath);
        $ladoAbs = Uploads::privateAbsolutePath($ladoPath);
        $costasAbs = Uploads::privateAbsolutePath($costasPath);

        $anterior = PosturalAssessment::where('student_id', $student->id)
            ->orderByDesc('taken_at')
            ->first(['id', 'front_photo_path', 'side_photo_path', 'back_photo_path']);

        $comentarioIndisponivel = $anterior
            ? 'Avaliação registrada! Em breve o comentário da Coach IA aparece por aqui.'
            : 'Primeira avaliação postural registrada! Esse é o seu ponto de partida.';

        try {
            $aiFeedback = $anterior
                ? AvaliacaoPostural::avaliarComparacao(
                    $student->name,
                    [
                        'frente' => Uploads::privateAbsolutePath($anterior->front_photo_path),
                        'lado' => Uploads::privateAbsolutePath($anterior->side_photo_path),
                        'costas' => Uploads::privateAbsolutePath($anterior->back_photo_path),
                    ],
                    ['frente' => $frenteAbs, 'lado' => $ladoAbs, 'costas' => $costasAbs],
                    $student->professional_id,
                )
                : AvaliacaoPostural::avaliarPrimeira($student->name, $frenteAbs, $ladoAbs, $costasAbs, $student->professional_id);
        } catch (\Throwable $e) {
            ErrorReporting::capturarFalhaIa('avaliacao_postural', $e, ['student_id' => $student->id]);
            $aiFeedback = $comentarioIndisponivel;
        }

        $avaliacao = PosturalAssessment::create([
            'student_id' => $student->id,
            'front_photo_path' => $frentePath,
            'side_photo_path' => $ladoPath,
            'back_photo_path' => $costasPath,
            'ai_feedback' => $aiFeedback,
            'compared_to_assessment_id' => $anterior?->id,
        ])->refresh();

        return response()->json(['assessment' => $avaliacao->makeHidden([
            'front_photo_path', 'side_photo_path', 'back_photo_path',
        ])], 201);
    }

    // GET /:token/postural/:id/imagem/:angulo — serve o arquivo (autenticado pelo token do aluno)
    public function imagem(Request $request, string $token, string $id, string $angulo): JsonResponse|BinaryFileResponse
    {
        $student = $this->alunoDoPortal($request);

        $avaliacao = PosturalAssessment::where('id', $id)->where('student_id', $student->id)->first();
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
