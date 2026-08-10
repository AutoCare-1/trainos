<?php

namespace App\Support;

use App\Models\ProfessionalSubscription;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Métricas do negócio para o CRM interno (/admin) — faturamento, custo, lucro e
 * rateio entre sócios. Único lugar que faz essas contas; AdminCrmController só lê.
 *
 * Todo dinheiro é tratado em centavos via App\Support\Money (mesmo cuidado de
 * FinanceiroPersonal), exceto o custo de IA, que nasce em USD com 8 casas e é
 * convertido pra BRL só na saída — ver config/ia_precos.php.
 */
class Crm
{
    /** Faturamento do mês: pagamentos de assinatura aprovados. */
    public static function faturamentoCentavos(Carbon $inicio, Carbon $fim): int
    {
        $total = DB::table('professional_subscription_payments')
            ->where('status', 'aprovado')
            ->whereBetween('pago_em', [$inicio->toDateString(), $fim->toDateString()])
            ->sum('valor');

        return Money::toCents((float) $total);
    }

    /**
     * Custo de IA no período, em USD. Some direto da coluna congelada — nunca
     * recalcula a partir dos tokens, pra que mudança de tabela de preço não
     * reescreva o passado.
     */
    public static function custoIaUsd(Carbon $inicio, Carbon $fim): float
    {
        return (float) DB::table('ia_usage_logs')
            ->whereBetween('created_at', [$inicio->startOfDay(), $fim->endOfDay()])
            ->sum('custo_usd');
    }

    /** Quebra do custo de IA por pipeline — mostra qual feature está pesando. */
    public static function custoIaPorPipeline(Carbon $inicio, Carbon $fim): array
    {
        return DB::table('ia_usage_logs')
            ->whereBetween('created_at', [$inicio->startOfDay(), $fim->endOfDay()])
            ->groupBy('pipeline')
            ->orderByDesc('custo_total')
            ->get([
                'pipeline',
                DB::raw('sum(custo_usd) as custo_total'),
                DB::raw('count(*) as chamadas'),
                DB::raw('sum(input_tokens) as input_tokens'),
                DB::raw('sum(output_tokens) as output_tokens'),
            ])
            ->map(fn ($l) => [
                'pipeline' => $l->pipeline,
                'custo_usd' => round((float) $l->custo_total, 6),
                'chamadas' => (int) $l->chamadas,
                'input_tokens' => (int) $l->input_tokens,
                'output_tokens' => (int) $l->output_tokens,
            ])
            ->values()
            ->all();
    }

    /**
     * Custos de infraestrutura do mês. Mesma regra de professional_expenses:
     * recorrente conta em todo mês em que esteve vigente ao menos um dia;
     * avulsa só no mês de starts_on.
     */
    public static function custoPlataformaCentavos(Carbon $inicio, Carbon $fim): int
    {
        $recorrentes = DB::table('platform_costs')
            ->where('is_recurring', true)
            ->where('starts_on', '<=', $fim->toDateString())
            ->where(function ($q) use ($inicio) {
                $q->whereNull('ends_on')->orWhere('ends_on', '>=', $inicio->toDateString());
            })
            ->sum('amount');

        $avulsas = DB::table('platform_costs')
            ->where('is_recurring', false)
            ->whereBetween('starts_on', [$inicio->toDateString(), $fim->toDateString()])
            ->sum('amount');

        return Money::toCents((float) $recorrentes) + Money::toCents((float) $avulsas);
    }

    /**
     * Assinantes por status, e quantos estão em teste grátis.
     *
     * Quem nunca teve nenhuma linha em professional_subscriptions e já passou
     * do prazo do teste grátis não aparece em nenhum status (não tem
     * assinatura pra ter status) nem em em_teste_gratis (o teste já acabou) —
     * por isso entra num balde próprio, teste_expirado_sem_assinar. Sem isso a
     * soma dos baldes ficava menor que total_personais e esse público (quem
     * abandonou antes de assinar, o mais relevante pra follow-up) ficava
     * invisível no CRM.
     */
    public static function assinantes(): array
    {
        $porStatus = DB::table('professional_subscriptions')
            ->groupBy('status')
            ->pluck(DB::raw('count(*)'), 'status')
            ->map(fn ($v) => (int) $v)
            ->all();

        $diasTeste = (int) config('planos_assinatura.dias_teste_gratis');
        $semAssinatura = DB::table('professionals')
            ->whereNotIn('id', DB::table('professional_subscriptions')->select('professional_id'));

        $emTeste = (clone $semAssinatura)->where('created_at', '>=', now()->subDays($diasTeste))->count();
        $testeExpiradoSemAssinar = (clone $semAssinatura)->where('created_at', '<', now()->subDays($diasTeste))->count();

        return [
            'ativas' => $porStatus[ProfessionalSubscription::STATUS_ATIVA] ?? 0,
            'atrasadas' => $porStatus[ProfessionalSubscription::STATUS_ATRASADA] ?? 0,
            'bloqueadas' => $porStatus[ProfessionalSubscription::STATUS_BLOQUEADA] ?? 0,
            'canceladas' => $porStatus[ProfessionalSubscription::STATUS_CANCELADA] ?? 0,
            'pendentes' => $porStatus[ProfessionalSubscription::STATUS_PENDENTE] ?? 0,
            'em_teste_gratis' => $emTeste,
            'teste_expirado_sem_assinar' => $testeExpiradoSemAssinar,
            'total_personais' => DB::table('professionals')->count(),
        ];
    }

    /**
     * Receita recorrente mensal: soma do valor de plano de cada assinatura
     * ativa. É projeção do que entra por mês se ninguém cancelar — diferente do
     * faturamento, que é o que de fato caiu na conta.
     */
    public static function mrrCentavos(): int
    {
        $planos = config('planos_assinatura.planos');
        $porPlano = DB::table('professional_subscriptions')
            ->where('status', ProfessionalSubscription::STATUS_ATIVA)
            ->groupBy('plano_chave')
            ->pluck(DB::raw('count(*)'), 'plano_chave');

        $total = 0;
        foreach ($porPlano as $chave => $qtd) {
            $valor = $planos[$chave]['valor_mensal'] ?? 0;
            $total += Money::toCents((float) $valor) * (int) $qtd;
        }

        return $total;
    }

    /** Distribuição de assinantes ativos por plano — mostra onde está a receita. */
    public static function distribuicaoPorPlano(): array
    {
        $planos = config('planos_assinatura.planos');

        return DB::table('professional_subscriptions')
            ->where('status', ProfessionalSubscription::STATUS_ATIVA)
            ->groupBy('plano_chave')
            ->orderByDesc('assinantes')
            ->get(['plano_chave', DB::raw('count(*) as assinantes')])
            ->map(fn ($l) => [
                'plano_chave' => $l->plano_chave,
                'nome' => $planos[$l->plano_chave]['nome'] ?? $l->plano_chave,
                'assinantes' => (int) $l->assinantes,
                'mrr_centavos' => Money::toCents((float) ($planos[$l->plano_chave]['valor_mensal'] ?? 0)) * (int) $l->assinantes,
            ])
            ->values()
            ->all();
    }

    /**
     * Série mensal de faturamento, custo e lucro — alimenta o gráfico principal.
     * Calculada mês a mês em PHP (e não numa query só com date_trunc) pelo mesmo
     * motivo de App\Support\Checkins: MySQL não tem generate_series, e um mês sem
     * movimento precisa aparecer como zero em vez de sumir da série.
     */
    public static function serieMensal(int $meses = 12): array
    {
        $cotacao = (float) config('ia_precos.usd_brl');
        $serie = [];

        for ($i = $meses - 1; $i >= 0; $i--) {
            $ref = now()->startOfMonth()->subMonths($i);
            $inicio = $ref->copy()->startOfMonth();
            $fim = $ref->copy()->endOfMonth();

            $faturamento = self::faturamentoCentavos($inicio, $fim);
            $custoIa = Money::toCents(self::custoIaUsd($inicio, $fim) * $cotacao);
            $custoPlataforma = self::custoPlataformaCentavos($inicio, $fim);

            $serie[] = [
                'mes' => $ref->format('Y-m'),
                'faturamento_centavos' => $faturamento,
                'custo_ia_centavos' => $custoIa,
                'custo_plataforma_centavos' => $custoPlataforma,
                'lucro_centavos' => $faturamento - $custoIa - $custoPlataforma,
            ];
        }

        return $serie;
    }

    /** Rateio do lucro do período entre os sócios vigentes. */
    public static function rateioLucro(int $lucroCentavos, Carbon $referencia): array
    {
        $vigentes = DB::table('profit_shares')
            ->where('starts_on', '<=', $referencia->toDateString())
            ->where(function ($q) use ($referencia) {
                $q->whereNull('ends_on')->orWhere('ends_on', '>=', $referencia->toDateString());
            })
            ->orderByDesc('percentual')
            ->get(['id', 'nome', 'percentual']);

        return $vigentes->map(fn ($s) => [
            'id' => $s->id,
            'nome' => $s->nome,
            'percentual' => (float) $s->percentual,
            // Prejuízo rateia proporcionalmente também — o sócio precisa ver o
            // número negativo, não um zero que esconde o buraco.
            'valor_centavos' => (int) round($lucroCentavos * ((float) $s->percentual / 100)),
        ])->values()->all();
    }

    /**
     * Modelos que apareceram em chamadas mas não têm preço em config/ia_precos.php.
     * O custo dessas linhas entrou como zero, então o CRM avisa em vez de mostrar
     * um lucro inflado sem explicação.
     */
    public static function modelosSemPreco(): array
    {
        $comPreco = array_keys(config('ia_precos.modelos', []));

        return DB::table('ia_usage_logs')
            ->whereNotIn('model', $comPreco)
            ->groupBy('model')
            ->pluck('model')
            ->values()
            ->all();
    }
}
