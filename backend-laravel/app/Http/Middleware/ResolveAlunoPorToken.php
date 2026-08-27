<?php

namespace App\Http\Middleware;

use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve o aluno pelo invite_token da URL — a autenticação das rotas do
 * portal, que são públicas e nunca usam JWT.
 *
 * Existia como trait chamado no começo de cada método de controller, sempre
 * com as mesmas quatro linhas ("busca; se não achou, 404 Link inválido"): 31
 * repetições em 9 controllers. Além do ruído, era uma checagem que uma rota
 * nova podia simplesmente esquecer de fazer — e esquecer significa rota do
 * portal sem autenticação nenhuma.
 *
 * O aluno resolvido vai em $request->attributes ('aluno'), lido pelos
 * controllers via Controller::alunoDoPortal().
 */
class ResolveAlunoPorToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->route('token');

        $student = Student::where('invite_token', $token)->where('status', 'active')->first();
        if (! $student) {
            return response()->json(['error' => 'Link inválido'], 404);
        }

        $request->attributes->set('aluno', $student);

        return $next($request);
    }
}
