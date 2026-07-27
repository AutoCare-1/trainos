<?php

namespace App\Http\Controllers;

use App\Models\ContentIdea;
use App\Support\ConteudoAgregados;
use App\Support\ConteudoIdeias;
use App\Support\ErrorReporting;
use App\Support\KillSwitchIa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContentController extends Controller
{
    // Limite de gerações (não de ideias) por dia — cada geração cria um lote (batch)
    // de várias ideias; isso controla o custo da busca na web e da própria chamada.
    private const LIMITE_GERACOES_POR_DIA = 5;

    // GET / — histórico de ideias já geradas, mais recente primeiro
    public function index(Request $request): JsonResponse
    {
        $ideas = ContentIdea::where('professional_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['ideas' => $ideas]);
    }

    // POST / — gera um novo lote de ideias (funde tendência de formato + dado agregado da base)
    public function store(Request $request): JsonResponse
    {
        if ($resp = KillSwitchIa::verificar('ideias_conteudo')) {
            return $resp;
        }

        $professionalId = $request->user()->id;

        $geracoesHoje = ContentIdea::where('professional_id', $professionalId)
            ->where('created_at', '>=', now()->startOfDay())
            ->distinct('batch_id')
            ->count('batch_id');

        if ($geracoesHoje >= self::LIMITE_GERACOES_POR_DIA) {
            return response()->json([
                'error' => 'Limite de '.self::LIMITE_GERACOES_POR_DIA.' gerações por dia atingido. Tente novamente amanhã.',
            ], 429);
        }

        $direcionamento = trim((string) $request->input('direcionamento')) ?: null;

        try {
            $resumoAgregado = ConteudoAgregados::montarResumoAgregadoAlunos($professionalId);
            $ideias = ConteudoIdeias::gerarIdeiasConteudo($resumoAgregado, $direcionamento);
        } catch (\Throwable $e) {
            ErrorReporting::capturarFalhaIa('ideias_conteudo', $e, ['professional_id' => $professionalId]);

            return response()->json(['error' => 'Não foi possível gerar ideias agora, tente de novo em instantes.'], 502);
        }

        $batchId = (string) Str::orderedUuid();
        $inseridas = collect($ideias)->map(fn ($ideia) => ContentIdea::create([
            'professional_id' => $professionalId,
            'batch_id' => $batchId,
            'format' => $ideia['format'],
            'title' => $ideia['title'],
            'description' => $ideia['description'],
            'caption_suggestion' => $ideia['caption_suggestion'],
        ])->refresh());

        return response()->json(['ideas' => $inseridas], 201);
    }

    // PATCH /:id — favorita/desfavorita uma ideia
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'saved' => ['required', 'boolean'],
        ]);

        $idea = ContentIdea::where('id', $id)
            ->where('professional_id', $request->user()->id)
            ->first();
        if (! $idea) {
            return response()->json(['error' => 'Ideia não encontrada'], 404);
        }

        $idea->update(['saved' => $validated['saved']]);

        return response()->json(['idea' => $idea]);
    }
}
