<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalPushController extends Controller
{
    // POST /:token/push/subscribe — salva (ou atualiza) a subscription do aluno
    public function subscribe(Request $request, string $token): JsonResponse
    {
        $student = $this->alunoDoPortal($request);

        $validated = $request->validate([
            'endpoint' => ['required', 'string'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string'],
        ]);

        $student->updatePushSubscription(
            endpoint: $validated['endpoint'],
            key: $validated['keys']['p256dh'],
            token: $validated['keys']['auth'],
            contentEncoding: $validated['contentEncoding'] ?? null,
        );

        return response()->json(null, 204);
    }

    // DELETE /:token/push/subscribe
    public function unsubscribe(Request $request, string $token): JsonResponse
    {
        $student = $this->alunoDoPortal($request);

        $validated = $request->validate(['endpoint' => ['required', 'string']]);

        $student->deletePushSubscription($validated['endpoint']);

        return response()->json(null, 204);
    }
}
