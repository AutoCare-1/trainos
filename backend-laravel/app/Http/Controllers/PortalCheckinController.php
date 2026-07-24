<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStudentByToken;
use App\Models\Checkin;
use App\Support\Checkins;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PortalCheckinController extends Controller
{
    use ResolvesStudentByToken;

    // GET /:token/checkins/summary — marcadores semanal/mensal/anual + grid da semana atual
    public function summary(string $token): JsonResponse
    {
        $student = $this->buscarAlunoPorToken($token);
        if (! $student) {
            return response()->json(['error' => 'Link inválido'], 404);
        }

        return response()->json([
            'semana' => Checkins::calcularResumoSemana($student->id, null),
            'mes' => Checkins::calcularResumoMes($student->id, null),
            'ano' => Checkins::calcularResumoAno($student->id, null),
            'checkinHoje' => Checkins::existeCheckinHoje($student->id),
        ]);
    }

    // GET /:token/checkins?period=week|month|year&ref=YYYY-MM-DD — histórico navegável
    // (grid/contagem do período + galeria de fotos com comentário, mais recente primeiro)
    public function index(Request $request, string $token): JsonResponse
    {
        $student = $this->buscarAlunoPorToken($token);
        if (! $student) {
            return response()->json(['error' => 'Link inválido'], 404);
        }

        $ref = $request->query('ref');
        $period = in_array($request->query('period'), ['month', 'year'], true) ? $request->query('period') : 'week';

        $fotos = Checkins::listarCheckinsPeriodo($student->id, $period, $ref);

        return match ($period) {
            'month' => response()->json(['period' => $period, 'mes' => Checkins::calcularResumoMes($student->id, $ref), 'fotos' => $fotos]),
            'year' => response()->json(['period' => $period, 'ano' => Checkins::calcularResumoAno($student->id, $ref), 'fotos' => $fotos]),
            default => response()->json(['period' => $period, 'semana' => Checkins::calcularResumoSemana($student->id, $ref), 'fotos' => $fotos]),
        };
    }

    // POST /:token/checkins — marca o treino de hoje (substitui a foto se já tiver check-in hoje)
    public function store(Request $request, string $token): JsonResponse
    {
        $student = $this->buscarAlunoPorToken($token);
        if (! $student) {
            return response()->json(['error' => 'Link inválido'], 404);
        }

        $request->validate(['foto' => ['required', 'file', 'mimetypes:image/*', 'max:10240']]);
        $comment = trim((string) $request->input('comment')) ?: null;

        $filePath = Uploads::storePrivate($request->file('foto'), 'checkins', $token);

        $arquivoAnterior = Checkin::where('student_id', $student->id)
            ->where('checkin_date', now()->toDateString())
            ->value('file_path');

        $checkin = Checkin::updateOrCreate(
            ['student_id' => $student->id, 'checkin_date' => now()->toDateString()],
            ['file_path' => $filePath, 'comment' => $comment]
        );

        // Se já existia check-in hoje, a foto antiga foi substituída — remove o
        // arquivo órfão do disco (sem travar a resposta se der erro).
        if ($arquivoAnterior && $arquivoAnterior !== $filePath) {
            Uploads::deletePrivateQuietly($arquivoAnterior);
        }

        return response()->json(['checkin' => $checkin], 201);
    }

    // GET /:token/checkins/:checkinId/imagem — serve o arquivo (autenticado pelo token do aluno)
    public function imagem(string $token, string $checkinId): JsonResponse|BinaryFileResponse
    {
        $student = $this->buscarAlunoPorToken($token);
        if (! $student) {
            return response()->json(['error' => 'Link inválido'], 404);
        }

        $checkin = Checkin::where('id', $checkinId)->where('student_id', $student->id)->first();
        if (! $checkin) {
            return response()->json(['error' => 'Check-in não encontrado'], 404);
        }

        return response()->file(Uploads::privateAbsolutePath($checkin->file_path));
    }
}
