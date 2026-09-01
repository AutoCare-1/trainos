<?php

namespace App\Http\Controllers;

use App\Models\HydrationLog;
use App\Models\MealLog;
use App\Models\NutritionSuggestion;
use App\Models\Student;
use App\Support\Uploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * O que o aluno registrou de alimentação — lado do personal.
 *
 * Só leitura, de propósito. O personal olha o padrão da semana e orienta de
 * forma geral (ou encaminha a um nutricionista); ele não edita nem prescreve
 * nada aqui, porque montar cardápio é privativo do nutricionista.
 */
class AlunoNutricaoController extends Controller
{
    private function alunoDoPersonal(Request $request, string $id): ?Student
    {
        return Student::where('id', $id)->where('professional_id', $request->user()->id)->first();
    }

    // GET /alunos/:id/nutricao?dias=7 — últimos dias do diário do aluno
    public function index(Request $request, string $id): JsonResponse
    {
        $student = $this->alunoDoPersonal($request, $id);
        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        $dias = max(1, min(30, (int) $request->query('dias', 7)));
        $desde = now()->subDays($dias - 1)->toDateString();

        $refeicoes = MealLog::where('student_id', $student->id)
            ->whereDate('data', '>=', $desde)
            ->orderByDesc('data')
            ->orderBy('created_at')
            ->get(['id', 'data', 'momento', 'descricao', 'created_at', 'file_path'])
            ->map(fn (MealLog $r) => [
                ...$r->only(['id', 'momento', 'descricao', 'created_at']),
                // toDateString explícito: only() devolve o Carbon cru, sem
                // passar pelo cast date:Y-m-d do model — e aí a data chegava
                // como ISO completo e quebrava a formatação no frontend.
                'data' => $r->data->toDateString(),
                'tem_foto' => $r->file_path !== null,
            ]);

        $agua = HydrationLog::where('student_id', $student->id)
            ->whereDate('data', '>=', $desde)
            ->orderByDesc('data')
            ->get(['data', 'copos']);

        // As orientações que a IA deu ao aluno aparecem aqui porque é o
        // personal quem responde profissionalmente por ele — não pode haver
        // orientação circulando no app sem ele saber o que foi dito.
        $sugestoes = NutritionSuggestion::where('student_id', $student->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'momento', 'resposta', 'encaminhou_nutricionista', 'created_at']);

        return response()->json([
            'refeicoes' => $refeicoes,
            'agua' => $agua,
            'sugestoes' => $sugestoes,
        ]);
    }

    // GET /alunos/:id/nutricao/refeicoes/{refeicaoId}/imagem — foto da refeição
    public function imagem(Request $request, string $id, string $refeicaoId): JsonResponse|BinaryFileResponse
    {
        $student = $this->alunoDoPersonal($request, $id);
        if (! $student) {
            return response()->json(['error' => 'Aluno não encontrado'], 404);
        }

        $refeicao = MealLog::where('id', $refeicaoId)->where('student_id', $student->id)->first();
        if (! $refeicao || ! $refeicao->file_path) {
            return response()->json(['error' => 'Foto não encontrada'], 404);
        }

        return response()->file(Uploads::privateAbsolutePath($refeicao->file_path));
    }
}
