<?php

namespace App\Http\Middleware;

use App\Models\Professional;
use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Espelha requireAuth() do Node (backend/src/middleware/auth.ts): só verifica a
 * assinatura e a expiração do JWT — nunca consulta o banco.
 *
 * O guard padrão do jwt-auth (auth:api → JWTGuard::user()) faz um SELECT em
 * professionals a cada request autenticada (provider->retrieveById), pra
 * hidratar o model inteiro. O Node nunca fez isso: requireAuth só decodifica o
 * token e confia na claim `sub`; só a rota /auth/me busca o profissional de
 * verdade no banco. Copiar o comportamento do guard padrão introduzia uma
 * dependência de banco a MAIS que o Node nunca teve em toda rota autenticada —
 * qualquer soluço passageiro na conexão (comum depois de um processo PHP local
 * ficar horas ocioso) derrubava a autenticação inteira, não só a query em si,
 * e aparecia pro usuário como "não autenticado" mesmo com um token perfeitamente
 * válido. Esse middleware devolve exatamente o mesmo comportamento do Node.
 */
class JwtAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization');
        $token = (is_string($header) && str_starts_with($header, 'Bearer ')) ? substr($header, 7) : null;

        if (! $token) {
            return response()->json(['error' => 'Não autenticado'], 401);
        }

        try {
            $payload = JWTAuth::setToken($token)->getPayload();
        } catch (JWTException) {
            return response()->json(['error' => 'Token inválido ou expirado'], 401);
        }

        // Model "leve": só o id vem da claim sub, sem tocar o banco — os
        // controllers só usam $request->user()->id (nunca ->name/->email).
        $professional = (new Professional())->forceFill(['id' => $payload->get('sub')]);
        $professional->exists = true;

        $request->setUserResolver(fn () => $professional);

        return $next($request);
    }
}
