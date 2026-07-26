<?php

namespace App\Http\Controllers;

use App\Models\ProfessionalNotificationSetting;
use App\Models\TipoNotificacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificacaoController extends Controller
{
    // GET / — catálogo inteiro, agrupado por categoria, com o estado atual (ligado/desligado)
    // pra esse personal. Preferência global por enquanto (student_id sempre null) — a coluna já
    // existe pra permitir por-aluno no futuro sem migration nova.
    public function index(Request $request): JsonResponse
    {
        $professionalId = $request->user()->id;

        $configuradas = ProfessionalNotificationSetting::where('professional_id', $professionalId)
            ->whereNull('student_id')
            ->get()
            ->keyBy('tipo_chave');

        $tipos = TipoNotificacao::orderBy('categoria')->orderBy('nome')->get()
            ->map(fn (TipoNotificacao $tipo) => [
                'chave' => $tipo->chave,
                'nome' => $tipo->nome,
                'descricao' => $tipo->descricao,
                'categoria' => $tipo->categoria,
                'publico' => $tipo->publico,
                'enabled' => $configuradas->get($tipo->chave)?->enabled ?? $tipo->ativo_por_padrao,
            ])
            ->groupBy('categoria');

        return response()->json(['categorias' => $tipos]);
    }

    // PATCH /:chave — liga/desliga um tipo pra esse personal (preferência global)
    public function toggle(Request $request, string $chave): JsonResponse
    {
        $tipo = TipoNotificacao::find($chave);
        if (! $tipo) {
            return response()->json(['error' => 'Tipo de notificação não encontrado'], 404);
        }

        $validated = $request->validate(['enabled' => ['required', 'boolean']]);

        $config = ProfessionalNotificationSetting::updateOrCreate(
            ['professional_id' => $request->user()->id, 'tipo_chave' => $chave, 'student_id' => null],
            ['enabled' => $validated['enabled']]
        );

        return response()->json(['chave' => $chave, 'enabled' => $config->enabled]);
    }
}
