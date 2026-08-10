<?php

namespace App\Http\Middleware;

use App\Models\Professional;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe as rotas do CRM interno (/admin) aos donos do produto.
 *
 * Roda DEPOIS de JwtAuthenticate (que só decodifica o token, sem tocar o banco),
 * então aqui é o primeiro ponto que precisa mesmo consultar professionals — é o
 * único lugar do app que lê is_admin, e uma query por request de CRM não tem
 * peso nenhum (são pouquíssimas, feitas por uma ou duas pessoas).
 *
 * Devolve 404, não 403: quem não é admin não deve nem descobrir que o CRM
 * existe. Um 403 confirmaria a rota pra quem estivesse sondando.
 */
class AdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->user()?->id;
        $ehAdmin = $id && Professional::whereKey($id)->value('is_admin');

        if (! $ehAdmin) {
            return response()->json(['error' => 'Não encontrado'], 404);
        }

        return $next($request);
    }
}
