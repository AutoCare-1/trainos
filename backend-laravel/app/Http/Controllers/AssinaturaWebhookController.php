<?php

namespace App\Http\Controllers;

use App\Models\ProfessionalSubscription;
use App\Models\ProfessionalSubscriptionPayment;
use App\Support\MercadoPago;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * POST /assinatura/webhook — rota pública (sem JWT, o Mercado Pago não manda
 * Bearer token do personal), avisando sobre eventos da assinatura recorrente.
 * Espelha o mesmo cuidado que o resto do projeto já tem com webhook/callback
 * de terceiro: nunca confiar sem validar a origem (aqui, a assinatura HMAC do
 * header x-signature — ver MercadoPago::validarAssinaturaWebhook).
 */
class AssinaturaWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $type = $request->input('type') ?? $request->query('type');
        $dataId = $request->input('data.id') ?? $request->query('data.id');

        if (! $dataId) {
            return response()->json(['ok' => true]);
        }

        if (! MercadoPago::validarAssinaturaWebhook($request, (string) $dataId)) {
            return response()->json(['error' => 'Assinatura inválida'], 401);
        }

        try {
            match ($type) {
                'preapproval', 'subscription_preapproval' => $this->tratarPreapproval((string) $dataId),
                'payment', 'subscription_authorized_payment' => $this->tratarPagamento((string) $dataId),
                default => Log::info('Webhook Mercado Pago com type não tratado', ['type' => $type]),
            };
        } catch (\Throwable $e) {
            // Loga e devolve 200 mesmo assim: um problema no nosso lado (payload
            // em formato inesperado, campo ausente) não deve virar retry
            // infinito do Mercado Pago — só a assinatura HMAC inválida acima
            // merece um status de erro de verdade.
            Log::error('Falha ao processar webhook do Mercado Pago', [
                'type' => $type, 'data_id' => $dataId, 'erro' => $e->getMessage(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    private function tratarPreapproval(string $preapprovalId): void
    {
        $preapproval = MercadoPago::buscarPreapproval($preapprovalId);
        $subscriptionId = $preapproval['external_reference'] ?? null;
        $subscription = $subscriptionId ? ProfessionalSubscription::find($subscriptionId) : null;
        if (! $subscription) {
            Log::warning('Webhook de preapproval sem assinatura local correspondente', ['preapproval_id' => $preapprovalId]);

            return;
        }

        $statusMp = $preapproval['status'] ?? null;
        $subscription->update([
            'mp_preapproval_id' => $preapprovalId,
            'status' => match ($statusMp) {
                'authorized' => ProfessionalSubscription::STATUS_ATIVA,
                'paused', 'cancelled' => ProfessionalSubscription::STATUS_CANCELADA,
                default => $subscription->status,
            },
        ]);
    }

    private function tratarPagamento(string $paymentId): void
    {
        // Idempotência: o Mercado Pago reenvia o mesmo evento por conta própria.
        // Sem esta parada, cada reentrega gravava outro pagamento e rodava o
        // addMonth() de novo lá embaixo — reentrega virava mês grátis.
        if (ProfessionalSubscriptionPayment::where('mp_payment_id', $paymentId)->exists()) {
            Log::info('Webhook de pagamento já processado, ignorando reentrega', ['payment_id' => $paymentId]);

            return;
        }

        $pagamento = MercadoPago::buscarPagamento($paymentId);

        // O formato exato de onde vem o preapproval_id num pagamento de
        // assinatura pode variar — tenta os campos mais comuns antes de
        // desistir, em vez de assumir um só e quebrar quando o outro aparecer.
        $preapprovalId = $pagamento['preapproval_id']
            ?? $pagamento['metadata']['preapproval_id']
            ?? $pagamento['external_reference']
            ?? null;

        $subscription = $preapprovalId
            ? ProfessionalSubscription::where('mp_preapproval_id', $preapprovalId)
                ->orWhere('id', $preapprovalId)
                ->first()
            : null;

        if (! $subscription) {
            Log::warning('Webhook de pagamento sem assinatura local correspondente', ['payment_id' => $paymentId]);

            return;
        }

        $aprovado = ($pagamento['status'] ?? null) === 'approved';
        $valor = (float) ($pagamento['transaction_amount'] ?? 0);

        ProfessionalSubscriptionPayment::create([
            'subscription_id' => $subscription->id,
            'mp_payment_id' => (string) $paymentId,
            'valor' => $valor,
            'status' => $aprovado ? 'aprovado' : 'recusado',
            'pago_em' => $aprovado ? now()->toDateString() : null,
        ]);

        // Um "approved" que veio com valor diferente do plano contratado não
        // estende assinatura nenhuma: o pagamento fica registrado (pra não
        // sumir dinheiro que entrou), mas quem decide o que fazer é uma pessoa
        // olhando o log — automatizar isso é dar mês de assinatura por um
        // valor que ninguém conferiu.
        $valorEsperado = config("planos_assinatura.planos.{$subscription->plano_chave}.valor_mensal");
        if ($aprovado && $valorEsperado !== null && abs($valor - (float) $valorEsperado) >= 0.01) {
            Log::warning('Pagamento aprovado com valor divergente do plano — revisar manualmente', [
                'payment_id' => $paymentId,
                'subscription_id' => $subscription->id,
                'plano' => $subscription->plano_chave,
                'valor_recebido' => $valor,
                'valor_esperado' => $valorEsperado,
            ]);

            return;
        }

        if ($aprovado) {
            $subscription->update([
                'status' => ProfessionalSubscription::STATUS_ATIVA,
                'atraso_desde' => null,
                'proxima_cobranca_em' => now()->addMonth()->toDateString(),
            ]);
        } elseif ($subscription->status !== ProfessionalSubscription::STATUS_ATRASADA) {
            $subscription->update([
                'status' => ProfessionalSubscription::STATUS_ATRASADA,
                'atraso_desde' => now()->toDateString(),
            ]);
        }
    }
}
