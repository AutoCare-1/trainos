<?php

namespace App\Support;

use App\Models\ProfessionalExpense;
use App\Models\StudentBillingPlan;
use Illuminate\Support\Carbon;

/**
 * Receita/despesa do personal, sempre calculada no backend (nunca trazendo todas
 * as linhas pro frontend somar). $referencia default é hoje (mês corrente, única
 * versão pedida por enquanto) — mas o cálculo funciona pra qualquer data passada
 * também, então dá pra reaproveitar num futuro seletor de mês sem reescrever nada
 * (pra mês fechado no passado, chamar com o último dia daquele mês).
 */
class FinanceiroPersonal
{
    /** @return array{total: float, por_tipo: array<string, float>} */
    public static function calcularReceitaMes(string $professionalId, ?Carbon $referencia = null): array
    {
        $data = ($referencia ?? Carbon::now())->toDateString();

        $planos = StudentBillingPlan::where('professional_id', $professionalId)
            ->where('starts_on', '<=', $data)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $data))
            ->get();

        // No dia exato em que um plano muda (fecha hoje + abre hoje), as duas
        // linhas têm o MESMO starts_on — não dá pra desempatar por data. Usa o
        // id (UUID ordenado por tempo, gerado por HasUuids) como critério, que
        // reflete a ordem real de criação mesmo quando starts_on empata
        // (decisão: só o valor mais novo conta no mês de transição).
        $porAluno = $planos->groupBy('student_id')->map(
            fn ($grupo) => $grupo->sortByDesc('id')->first()
        );

        $centavosPorTipo = [];
        foreach ($porAluno as $plano) {
            $centavosPorTipo[$plano->billing_type] = ($centavosPorTipo[$plano->billing_type] ?? 0) + Money::toCents($plano->monthly_value);
        }

        return [
            'total' => Money::fromCents(array_sum($centavosPorTipo)),
            'por_tipo' => array_map(fn ($centavos) => Money::fromCents($centavos), $centavosPorTipo),
        ];
    }

    /** @return array{total: float} */
    public static function calcularDespesasMes(string $professionalId, ?Carbon $referencia = null): array
    {
        $referencia ??= Carbon::now();
        $data = $referencia->toDateString();
        $inicioMes = $referencia->copy()->startOfMonth()->toDateString();
        $fimMes = $referencia->copy()->endOfMonth()->toDateString();

        $recorrentes = ProfessionalExpense::where('professional_id', $professionalId)
            ->where('is_recurring', true)
            ->where('starts_on', '<=', $data)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $data))
            ->get();

        // Mesmo problema de transição das billing plans, mas despesas não têm uma
        // chave natural tipo student_id — usa previous_expense_id pra excluir a
        // linha antiga quando a nova (que a substituiu) também está no conjunto.
        $idsSubstituidos = $recorrentes->pluck('previous_expense_id')->filter()->all();
        $recorrentes = $recorrentes->reject(fn ($e) => in_array($e->id, $idsSubstituidos, true));

        $avulsas = ProfessionalExpense::where('professional_id', $professionalId)
            ->where('is_recurring', false)
            ->whereBetween('starts_on', [$inicioMes, $fimMes])
            ->get();

        $centavos = 0;
        foreach ($recorrentes->concat($avulsas) as $despesa) {
            $centavos += Money::toCents($despesa->amount);
        }

        return ['total' => Money::fromCents($centavos)];
    }
}
