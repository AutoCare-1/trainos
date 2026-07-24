<?php

namespace App\Http\Controllers;

use App\Models\ConsultorIaMessage;
use App\Support\ConsultorIa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsultorIaController extends Controller
{
    // GET / — histórico do chat, em ordem cronológica
    public function index(Request $request): JsonResponse
    {
        $messages = ConsultorIaMessage::where('professional_id', $request->user()->id)
            ->orderBy('created_at')
            ->get();

        return response()->json(['messages' => $messages]);
    }

    // POST /chat — envia uma pergunta e recebe a resposta da IA (com tool use)
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string'],
        ]);
        $conteudo = trim($validated['content']);
        if ($conteudo === '') {
            return response()->json(['error' => 'content é obrigatório'], 400);
        }

        $professionalId = $request->user()->id;

        $mensagemPersonal = ConsultorIaMessage::create([
            'professional_id' => $professionalId,
            'role' => 'personal',
            'content' => $conteudo,
        ]);

        $historico = ConsultorIaMessage::where('professional_id', $professionalId)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->sortBy('created_at')
            ->values();

        $texto = ConsultorIa::responderConsultor($professionalId, $historico);

        $mensagemIa = ConsultorIaMessage::create([
            'professional_id' => $professionalId,
            'role' => 'ai',
            'content' => $texto,
        ]);

        return response()->json(['message' => $mensagemPersonal, 'aiReply' => $mensagemIa], 201);
    }
}
