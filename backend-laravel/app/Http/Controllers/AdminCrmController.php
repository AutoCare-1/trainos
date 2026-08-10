<?php

namespace App\Http\Controllers;

use App\Models\Professional;
use App\Support\Crm;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CRM interno do produto — só para os donos (ver App\Http\Middleware\AdminOnly).
 * Não confundir com o dashboard "Meu Negócio" (NegocioController), que é o
 * financeiro de UM personal: aqui é o financeiro do Clube Mais como empresa.
 */
class AdminCrmController extends Controller
{
    /** GET /admin/dashboard?meses=12 — visão consolidada. */
    public function dashboard(Request $request): JsonResponse
    {
        $meses = max(1, min(24, (int) $request->query('meses', 12)));

        $inicio = now()->startOfMonth();
        $fim = now()->endOfMonth();
        $cotacao = (float) config('ia_precos.usd_brl');

        $faturamento = Crm::faturamentoCentavos($inicio, $fim);
        $custoIaUsd = Crm::custoIaUsd($inicio, $fim);
        $custoIa = Money::toCents($custoIaUsd * $cotacao);
        $custoPlataforma = Crm::custoPlataformaCentavos($inicio, $fim);
        $lucro = $faturamento - $custoIa - $custoPlataforma;

        return response()->json([
            'mes_referencia' => $inicio->format('Y-m'),
            'cotacao_usd_brl' => $cotacao,
            'resumo' => [
                'faturamento' => Money::fromCents($faturamento),
                'custo_ia' => Money::fromCents($custoIa),
                'custo_ia_usd' => round($custoIaUsd, 4),
                'custo_plataforma' => Money::fromCents($custoPlataforma),
                'lucro' => Money::fromCents($lucro),
                'margem_pct' => $faturamento > 0 ? round(($lucro / $faturamento) * 100, 1) : null,
                'mrr' => Money::fromCents(Crm::mrrCentavos()),
            ],
            'assinantes' => Crm::assinantes(),
            'planos' => array_map(
                fn ($p) => [...$p, 'mrr' => Money::fromCents($p['mrr_centavos'])],
                Crm::distribuicaoPorPlano()
            ),
            'serie_mensal' => array_map(fn ($m) => [
                'mes' => $m['mes'],
                'faturamento' => Money::fromCents($m['faturamento_centavos']),
                'custo_ia' => Money::fromCents($m['custo_ia_centavos']),
                'custo_plataforma' => Money::fromCents($m['custo_plataforma_centavos']),
                'lucro' => Money::fromCents($m['lucro_centavos']),
            ], Crm::serieMensal($meses)),
            'custo_ia_por_pipeline' => array_map(fn ($p) => [
                ...$p,
                'custo_brl' => round($p['custo_usd'] * $cotacao, 2),
            ], Crm::custoIaPorPipeline($inicio, $fim)),
            'rateio_lucro' => array_map(
                fn ($s) => [...$s, 'valor' => Money::fromCents($s['valor_centavos'])],
                // Referência é HOJE, não o dia 1º: quem entrou no meio do mês
                // participa do lucro deste mês. Usar o início do mês excluiria
                // todo sócio cadastrado agora — que é justamente o caso de quem
                // acabou de configurar a divisão.
                Crm::rateioLucro($lucro, now())
            ),
            // Avisa se algum modelo rodou sem preço cadastrado — nesse caso o
            // custo entrou como zero e o lucro acima está otimista.
            'modelos_sem_preco' => Crm::modelosSemPreco(),
        ]);
    }

    // ---- Custos da plataforma -------------------------------------------------

    public function listarCustos(): JsonResponse
    {
        $custos = DB::table('platform_costs')
            ->orderByDesc('is_recurring')
            ->orderByDesc('starts_on')
            ->get(['id', 'description', 'amount', 'is_recurring', 'starts_on', 'ends_on']);

        return response()->json(['custos' => $custos]);
    }

    public function criarCusto(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'is_recurring' => ['required', 'boolean'],
            'starts_on' => ['required', 'date'],
        ]);

        $id = (string) Str::uuid();
        DB::table('platform_costs')->insert([
            'id' => $id,
            'description' => $dados['description'],
            'amount' => $dados['amount'],
            'is_recurring' => $dados['is_recurring'],
            'starts_on' => $dados['starts_on'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $id], 201);
    }

    /**
     * Encerra um custo recorrente. Mesmo comportamento de GastoController::encerrar:
     * o mês corrente ainda conta, para de contar a partir do mês seguinte.
     */
    public function encerrarCusto(string $id): JsonResponse
    {
        $custo = DB::table('platform_costs')->where('id', $id)->first();
        if (! $custo) {
            return response()->json(['error' => 'Custo não encontrado'], 404);
        }
        if ($custo->ends_on) {
            return response()->json(['error' => 'Este custo já foi encerrado.'], 422);
        }

        DB::table('platform_costs')->where('id', $id)->update([
            'ends_on' => now()->toDateString(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    // ---- Divisão de lucro -----------------------------------------------------

    public function listarSocios(): JsonResponse
    {
        $socios = DB::table('profit_shares')
            ->whereNull('ends_on')
            ->orderByDesc('percentual')
            ->get(['id', 'nome', 'percentual', 'starts_on']);

        return response()->json([
            'socios' => $socios,
            'percentual_total' => (float) $socios->sum('percentual'),
        ]);
    }

    public function criarSocio(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'percentual' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $totalAtual = (float) DB::table('profit_shares')->whereNull('ends_on')->sum('percentual');
        if ($totalAtual + (float) $dados['percentual'] > 100) {
            return response()->json([
                'error' => 'A soma dos percentuais passaria de 100% (hoje está em '.$totalAtual.'%).',
            ], 422);
        }

        $id = (string) Str::uuid();
        DB::table('profit_shares')->insert([
            'id' => $id,
            'nome' => $dados['nome'],
            'percentual' => $dados['percentual'],
            'starts_on' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['id' => $id], 201);
    }

    /**
     * Muda o percentual de um sócio fechando a linha vigente e criando outra —
     * nunca UPDATE no valor, senão o rateio de meses passados mudaria junto.
     */
    public function atualizarSocio(Request $request, string $id): JsonResponse
    {
        $dados = $request->validate([
            'percentual' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $socio = DB::table('profit_shares')->where('id', $id)->whereNull('ends_on')->first();
        if (! $socio) {
            return response()->json(['error' => 'Sócio não encontrado'], 404);
        }

        $totalOutros = (float) DB::table('profit_shares')
            ->whereNull('ends_on')->where('id', '!=', $id)->sum('percentual');
        if ($totalOutros + (float) $dados['percentual'] > 100) {
            return response()->json([
                'error' => 'A soma dos percentuais passaria de 100% (os outros somam '.$totalOutros.'%).',
            ], 422);
        }

        DB::transaction(function () use ($id, $socio, $dados) {
            DB::table('profit_shares')->where('id', $id)->update([
                'ends_on' => now()->toDateString(),
                'updated_at' => now(),
            ]);
            DB::table('profit_shares')->insert([
                'id' => (string) Str::uuid(),
                'nome' => $socio->nome,
                'percentual' => $dados['percentual'],
                'starts_on' => now()->toDateString(),
                'previous_share_id' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return response()->json(['ok' => true]);
    }

    public function removerSocio(string $id): JsonResponse
    {
        $afetados = DB::table('profit_shares')
            ->where('id', $id)->whereNull('ends_on')
            ->update(['ends_on' => now()->toDateString(), 'updated_at' => now()]);

        if (! $afetados) {
            return response()->json(['error' => 'Sócio não encontrado'], 404);
        }

        return response()->json(['ok' => true]);
    }

    // ---- Administradores ------------------------------------------------------

    public function listarAdmins(): JsonResponse
    {
        return response()->json([
            'admins' => Professional::where('is_admin', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    /** Promove um personal existente a admin, buscando por e-mail. */
    public function promoverAdmin(Request $request): JsonResponse
    {
        $dados = $request->validate(['email' => ['required', 'email']]);

        $professional = Professional::where('email', $dados['email'])->first();
        if (! $professional) {
            return response()->json(['error' => 'Nenhuma conta com esse e-mail.'], 404);
        }
        if ($professional->is_admin) {
            return response()->json(['error' => 'Essa conta já é administradora.'], 422);
        }

        $professional->forceFill(['is_admin' => true])->save();

        return response()->json(['ok' => true]);
    }

    public function revogarAdmin(Request $request, string $id): JsonResponse
    {
        // Trava de segurança: sem isso dá pra remover o último admin e perder o
        // acesso ao CRM de vez, sem nenhum caminho de volta pela interface.
        if (Professional::where('is_admin', true)->count() <= 1) {
            return response()->json(['error' => 'Não dá pra remover o último administrador.'], 422);
        }
        if ($id === $request->user()->id) {
            return response()->json(['error' => 'Você não pode remover seu próprio acesso.'], 422);
        }

        $professional = Professional::whereKey($id)->where('is_admin', true)->first();
        if (! $professional) {
            return response()->json(['error' => 'Administrador não encontrado'], 404);
        }

        $professional->forceFill(['is_admin' => false])->save();

        return response()->json(['ok' => true]);
    }

    /** Últimas chamadas de IA — para investigar um pico de custo. */
    public function usoIa(Request $request): JsonResponse
    {
        $limite = max(1, min(200, (int) $request->query('limite', 50)));

        return response()->json([
            'chamadas' => DB::table('ia_usage_logs as l')
                ->leftJoin('professionals as p', 'p.id', '=', 'l.professional_id')
                ->orderByDesc('l.created_at')
                ->limit($limite)
                ->get([
                    'l.id', 'l.pipeline', 'l.model', 'l.input_tokens', 'l.output_tokens',
                    'l.web_searches', 'l.custo_usd', 'l.created_at', 'p.name as professional_name',
                ]),
        ]);
    }
}
