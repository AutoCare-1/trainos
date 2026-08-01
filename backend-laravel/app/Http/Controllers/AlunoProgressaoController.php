<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStudentForProfessional;
use App\Support\Progressao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Sugestão de progressão por exercício, pra montar a próxima prescrição —
 * cálculo determinístico em App\Support\Progressao, sem IA. Só leitura: quem
 * decide aceitar, ignorar ou ajustar o número é o personal, na tela de treino.
 */
class AlunoProgressaoController extends Controller
{
    use ResolvesStudentForProfessional;

    // GET /alunos/:id/progressao
    public function index(Request $request, string $id): JsonResponse
    {
        $student = $this->buscarAlunoDoProfissional($request, $id);
        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        $sugestoes = Progressao::paraAluno($student->id, $student->professional_id);

        return response()->json(['sugestoes' => $sugestoes]);
    }
}
