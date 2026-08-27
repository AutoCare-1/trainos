<?php

namespace App\Http\Middleware;

use App\Models\Professional;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
 *
 * A ÚNICA consulta ao banco que existe aqui é a marca d'água de revogação
 * (professionals.tokens_valid_after), e ela vem de um cache curto — a
 * resiliência que o parágrafo acima protege continua valendo: banco fora do ar
 * não derruba quem já está autenticado, porque a falha é tratada como "nada
 * revogado". O motivo de aceitar essa consulta é que sem ela não havia como
 * invalidar sessão nenhuma: com TTL de 7 dias e blacklist desligada, trocar a
 * senha não derrubava o token vazado, e "Sair" era só limpar o localStorage.
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

        $professionalId = (string) $payload->get('sub');

        if (self::foiRevogado($professionalId, (int) $payload->get('iat'))) {
            return response()->json(['error' => 'Token inválido ou expirado'], 401);
        }

        // Model "leve": só o id vem da claim sub, sem tocar o banco — os
        // controllers só usam $request->user()->id (nunca ->name/->email).
        $professional = (new Professional())->forceFill(['id' => $professionalId]);
        $professional->exists = true;

        $request->setUserResolver(fn () => $professional);

        return $next($request);
    }

    /**
     * Token emitido antes da marca d'água do personal não vale mais.
     *
     * Cache de 60s pra não virar um SELECT por request (o ponto do middleware
     * é justamente não ter isso), e falha de banco devolve false: derrubar
     * todo mundo porque o banco soluçou seria pior que o problema que a
     * revogação resolve — quem revogou de fato espera no máximo um minuto.
     */
    private static function foiRevogado(string $professionalId, int $emitidoEm): bool
    {
        try {
            $revogadoAte = Cache::remember(
                "tokens_valid_after:{$professionalId}",
                60,
                fn () => DB::table('professionals')->where('id', $professionalId)->value('tokens_valid_after')
            );
        } catch (\Throwable) {
            return false;
        }

        // <= e não <: o iat do JWT tem precisão de segundos, então um token
        // emitido no MESMO segundo em que a sessão foi revogada escaparia. Na
        // dúvida, revoga — o custo é um login novo feito no mesmo segundo do
        // logout precisar ser refeito, coisa que na prática não acontece
        // (entre um e outro tem redirect e o usuário digitando a senha).
        return $revogadoAte !== null && $emitidoEm <= Carbon::parse($revogadoAte)->timestamp;
    }
}
