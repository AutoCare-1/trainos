<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStudentForProfessional;
use App\Models\WorkoutReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Histórico de anamneses de revisão vistas pelo profissional — só leitura (quem responde é o aluno, pelo portal). */
class AlunoRevisaoController extends Controller
{
    use ResolvesStudentForProfessional;

    // GET /alunos/:id/revisoes
    public function index(Request $request, string $id): JsonResponse
    {
        $student = $this->buscarAlunoDoProfissional($request, $id);
        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        $revisoes = WorkoutReview::where('workout_reviews.student_id', $student->id)
            ->join('workouts', 'workouts.id', '=', 'workout_reviews.workout_id')
            ->orderByDesc('workout_reviews.created_at')
            ->select('workout_reviews.*', 'workouts.name as workout_name')
            ->get();

        return response()->json(['reviews' => $revisoes]);
    }
}
