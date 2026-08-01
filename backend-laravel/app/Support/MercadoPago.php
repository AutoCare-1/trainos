<?php

namespace App\Support;

use App\Models\Professional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cobrança recorrente do PERSONAL com o TrainOS (não do aluno — isso é outro
 * sistema, ver StudentBillingPlan). Mesmo padrão de App\Support\Strava: classe
 * estática, Http:: cru, sem SDK (o projeto não usa SDK de pagamento nenhum e
 * esse padrão já funciona bem aqui).
 */
class MercadoPago
{
    private const BASE_URL = 'https://api.mercadopago.com';

    private static function accessToken(): string
    {
        $token = config('services.mercado_pago.access_token');
        if (! $token) {
            throw new RuntimeException('MERCADO_PAGO_ACCESS_TOKEN não configurado no .env');
        }

        return $token;
    }

    /**
     * Cria a assinatura ("preapproval sem plano associado" — valor definido
     * aqui, não num plano cadastrado do lado do Mercado Pago, já que os planos
     * já são curados em config/planos_assinatura.php) e devolve a URL de
     * checkout hospedada pelo Mercado Pago pra redirecionar o personal.
     */
    public static function criarAssinatura(Professional $professional, string $planoChave, string $subscriptionId): string
    {
        $plano = config("planos_assinatura.planos.{$planoChave}");
        if (! $plano) {
            throw new RuntimeException("Plano de assinatura inválido: {$planoChave}");
        }

        $response = Http::withToken(self::accessToken())->post(self::BASE_URL.'/preapproval', [
            'reason' => "Clube Mais Personal — plano {$plano['nome']}",
            'external_reference' => $subscriptionId,
            'payer_email' => $professional->email,
            'back_url' => config('app.frontend_url').'/plano',
            'auto_recurring' => [
                'frequency' => 1,
                'frequency_type' => 'months',
                'transaction_amount' => (float) $plano['valor_mensal'],
                'currency_id' => 'BRL',
            ],
            'status' => 'pending',
        ]);

        if ($response->failed()) {
            throw new RuntimeException("Mercado Pago rejeitou a criação da assinatura: {$response->status()} {$response->body()}");
        }

        $dados = $response->json();
        if (! isset($dados['init_point'])) {
            throw new RuntimeException('Mercado Pago não devolveu init_point na criação da assinatura.');
        }

        return $dados['init_point'];
    }

    public static function buscarPreapproval(string $preapprovalId): array
    {
        $response = Http::withToken(self::accessToken())->get(self::BASE_URL."/preapproval/{$preapprovalId}");
        if ($response->failed()) {
            throw new RuntimeException("Erro ao buscar preapproval no Mercado Pago: {$response->status()} {$response->body()}");
        }

        return $response->json();
    }

    public static function buscarPagamento(string $paymentId): array
    {
        $response = Http::withToken(self::accessToken())->get(self::BASE_URL."/v1/payments/{$paymentId}");
        if ($response->failed()) {
            throw new RuntimeException("Erro ao buscar pagamento no Mercado Pago: {$response->status()} {$response->body()}");
        }

        return $response->json();
    }

    /**
     * Recalcula o HMAC-SHA256 do webhook (manifest documentado pelo Mercado
     * Pago: "id:{data.id};request-id:{x-request-id};ts:{ts};") e compara com o
     * header x-signature. Sem isso, qualquer um poderia forjar uma chamada
     * dizendo "pagamento aprovado" pro nosso endpoint público.
     */
    public static function validarAssinaturaWebhook(Request $request, string $dataId): bool
    {
        $secret = config('services.mercado_pago.webhook_secret');
        if (! $secret) {
            throw new RuntimeException('MERCADO_PAGO_WEBHOOK_SECRET não configurado no .env');
        }

        $signatureHeader = $request->header('x-signature');
        $requestId = $request->header('x-request-id');
        if (! $signatureHeader || ! $requestId) {
            return false;
        }

        $partes = [];
        foreach (explode(',', $signatureHeader) as $par) {
            [$chave, $valor] = array_pad(explode('=', $par, 2), 2, null);
            if ($chave !== null && $valor !== null) {
                $partes[trim($chave)] = trim($valor);
            }
        }

        $ts = $partes['ts'] ?? null;
        $v1 = $partes['v1'] ?? null;
        if (! $ts || ! $v1) {
            return false;
        }

        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$ts};";
        $esperado = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($esperado, $v1);
    }
}
