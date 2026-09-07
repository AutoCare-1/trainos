<?php

namespace App\Support;

use App\Models\MealLog;
use Illuminate\Support\Collection;

/**
 * Soma de caloria e macros de refeições do diário.
 *
 * Fica num lugar só porque as duas telas (aluno e personal) precisam do mesmo
 * cálculo, e porque as regras de "o que não dá pra somar" são fáceis de errar:
 *
 * - Item sem `quantidade_g` (o aluno escolheu o alimento mas não disse quanto)
 *   não entra na soma — a gente conta quantos ficaram de fora, pra tela poder
 *   dizer que o total é parcial.
 * - Macro nulo no alimento é "não analisado" (o NA da POF), não zero: soma só
 *   o campo que existe.
 * - Refeição só de foto ou só de texto não tem item nenhum e não contribui —
 *   é contada à parte pra tela deixar claro que o número não cobre o dia todo.
 *
 * O total é sempre APROXIMADO e a tela precisa dizer isso. Não é meta nem
 * prescrição (ver App\Support\Nutricao pra a fronteira legal).
 */
class NutricaoTotais
{
    /**
     * Totais de um conjunto de refeições (um dia, ou vários — quem agrupa é
     * quem chama). As refeições precisam vir com `itens.food` carregado.
     *
     * @param  Collection<int, MealLog>|iterable<MealLog>  $refeicoes
     * @return array{kcal:int, proteina_g:int, carboidrato_g:int, lipideos_g:int, itens_contados:int, itens_sem_quantidade:int, refeicoes_sem_itens:int}
     */
    public static function somar(iterable $refeicoes): array
    {
        $kcal = $prot = $carb = $lip = 0.0;
        $contados = 0;
        $semQtd = 0;
        $refeicoesSemItens = 0;

        foreach ($refeicoes as $refeicao) {
            $itens = $refeicao->itens;
            if ($itens->isEmpty()) {
                $refeicoesSemItens++;

                continue;
            }

            foreach ($itens as $item) {
                if ($item->quantidade_g === null) {
                    $semQtd++;

                    continue;
                }

                $food = $item->food;
                if (! $food) {
                    continue;
                }

                $fator = $item->quantidade_g / 100;
                if ($food->kcal !== null) {
                    $kcal += $food->kcal * $fator;
                }
                if ($food->proteina_g !== null) {
                    $prot += $food->proteina_g * $fator;
                }
                if ($food->carboidrato_g !== null) {
                    $carb += $food->carboidrato_g * $fator;
                }
                if ($food->lipideos_g !== null) {
                    $lip += $food->lipideos_g * $fator;
                }
                $contados++;
            }
        }

        return [
            'kcal' => (int) round($kcal),
            'proteina_g' => (int) round($prot),
            'carboidrato_g' => (int) round($carb),
            'lipideos_g' => (int) round($lip),
            'itens_contados' => $contados,
            'itens_sem_quantidade' => $semQtd,
            'refeicoes_sem_itens' => $refeicoesSemItens,
        ];
    }

    /**
     * Totais de UMA refeição — atalho pro caso comum (a linha embaixo de cada
     * card no diário).
     *
     * @return array{kcal:int, proteina_g:int, carboidrato_g:int, lipideos_g:int, itens_contados:int, itens_sem_quantidade:int, refeicoes_sem_itens:int}
     */
    public static function daRefeicao(MealLog $refeicao): array
    {
        return self::somar([$refeicao]);
    }

    /**
     * Resumo de um período pro personal: média de kcal por dia e quantos dias
     * do período tiveram algum item somável. A média divide pelos dias COM
     * registro, não pelo período inteiro — senão a média despenca por dia em
     * branco e vira ruído.
     *
     * @param  Collection<int, MealLog>  $refeicoes
     * @return array{dias_com_registro:int, media_kcal_dia:int|null, media_proteina_dia:int|null, total_por_dia:array<int, array<string, mixed>>}
     */
    public static function resumoDoPeriodo(Collection $refeicoes): array
    {
        $porDia = $refeicoes
            ->groupBy(fn (MealLog $r) => $r->data->toDateString())
            ->map(fn (Collection $doDia, string $data) => [
                'data' => $data,
                ...self::somar($doDia),
            ])
            ->sortKeysDesc()
            ->values();

        $comKcal = $porDia->filter(fn ($d) => $d['itens_contados'] > 0);

        return [
            'dias_com_registro' => $porDia->count(),
            'media_kcal_dia' => $comKcal->isNotEmpty() ? (int) round($comKcal->avg('kcal')) : null,
            'media_proteina_dia' => $comKcal->isNotEmpty() ? (int) round($comKcal->avg('proteina_g')) : null,
            'total_por_dia' => $porDia->all(),
        ];
    }
}
