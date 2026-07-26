<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    // GET /push/vapid-public-key — chave pública é informação pública por natureza
    // (vai em todo Subscribe do navegador), sem necessidade de autenticação.
    public function vapidPublicKey(): JsonResponse
    {
        return response()->json(['publicKey' => config('webpush.vapid.public_key')]);
    }

    // POST /push/subscribe — salva (ou atualiza) a subscription do personal autenticado
    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string'],
        ]);

        $request->user()->updatePushSubscription(
            endpoint: $validated['endpoint'],
            key: $validated['keys']['p256dh'],
            token: $validated['keys']['auth'],
            contentEncoding: $validated['contentEncoding'] ?? null,
        );

        return response()->json(null, 204);
    }

    // DELETE /push/subscribe — remove a subscription (ex: aluno/personal desativou notificações no navegador)
    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate(['endpoint' => ['required', 'string']]);

        $request->user()->deletePushSubscription($validated['endpoint']);

        return response()->json(null, 204);
    }
}
