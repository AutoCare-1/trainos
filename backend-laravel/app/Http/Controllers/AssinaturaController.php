<?php

namespace App\Http\Controllers;

use App\Models\Professional;
use App\Models\ProfessionalSubscription;
use App\Support\Assinatura;
use App\Support\MercadoPago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssinaturaController extends Controller
{
    // GET /assinatura — status da assinatura do personal (usado pela tela "Meu
    // Plano" e pelo banner de aviso no topo do app)
    public function show(Request $request): JsonResponse
    {
        $professional = Professional::findOrFail($request->user()->id);
        $status = Assinatura::status($professional);
        $planos = config('planos_assinatura.planos');

        $faturas = $status['subscription']
            ? $status['subscription']->payments()->orderByDesc('created_at')->get(['valor', 'status', 'pago_em', 'created_at'])
            : collect();

        return response()->json([
            'plano_chave' => $status['subscription']?->plano_chave,
            'status' => $status['subscription']?->status,
            'em_teste' => $status['em_teste'],
            'dias_restantes_teste' => $status['dias_restantes_teste'],
            'dias_restantes_carencia' => $status['dias_restantes_carencia'],
            'limite_alunos' => $status['limite_alunos'],
            'alunos_ativos' => Assinatura::alunosAtivos($professional),
            'proxima_cobranca_em' => $status['subscription']?->proxima_cobranca_em,
            'planos' => $planos,
            'faturas' => $faturas,
        ]);
    }

    // POST /assinatura/checkout — cria/reaproveita a assinatura local (pendente)
    // e devolve a URL de checkout do Mercado Pago pra redirecionar o personal
    public function checkout(Request $request): JsonResponse
    {
        $professional = Professional::findOrFail($request->user()->id);
        $validated = $request->validate([
            'plano_chave' => ['required', 'string', 'in:'.implode(',', array_keys(config('planos_assinatura.planos')))],
        ]);

        $subscription = ProfessionalSubscription::updateOrCreate(
            ['professional_id' => $professional->id],
            ['plano_chave' => $validated['plano_chave'], 'status' => ProfessionalSubscription::STATUS_PENDENTE]
        )->refresh();

        try {
            $checkoutUrl = MercadoPago::criarAssinatura($professional, $validated['plano_chave'], $subscription->id);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Não foi possível iniciar o checkout. Tente de novo em alguns minutos.'], 502);
        }

        return response()->json(['checkout_url' => $checkoutUrl]);
    }
}
