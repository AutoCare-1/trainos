<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStudentForProfessional;
use App\Models\Checkin;
use App\Support\Checkins;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Frequência de check-in vista pelo profissional — mesma leitura que o aluno tem no portal, sem poder registrar. */
class AlunoCheckinController extends Controller
{
    use ResolvesStudentForProfessional;

    // GET /alunos/:id/checkins/summary
    public function summary(Request $request, string $id): JsonResponse
    {
        $student = $this->buscarAlunoDoProfissional($request, $id);
        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        return response()->json([
            'semana' => Checkins::calcularResumoSemana($student->id, null),
            'mes' => Checkins::calcularResumoMes($student->id, null),
            'ano' => Checkins::calcularResumoAno($student->id, null),
            'checkinHoje' => Checkins::existeCheckinHoje($student->id),
        ]);
    }

    // GET /alunos/:id/checkins?period=week|month|year&ref=YYYY-MM-DD
    public function index(Request $request, string $id): JsonResponse
    {
        $student = $this->buscarAlunoDoProfissional($request, $id);
        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
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

    // GET /alunos/:id/checkins/:checkinId/imagem — serve o arquivo (autenticado por JWT + dono do aluno)
    public function imagem(Request $request, string $id, string $checkinId): JsonResponse|BinaryFileResponse
    {
        $student = $this->buscarAlunoDoProfissional($request, $id);
        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        $checkin = Checkin::where('id', $checkinId)->where('student_id', $student->id)->first();
        if (! $checkin) {
            return response()->json(['error' => 'Check-in não encontrado'], 404);
        }

        return response()->file(Uploads::privateAbsolutePath($checkin->file_path));
    }
}
