<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/** Espelha backend/src/services/gamification.ts do Node. */
class Gamification
{
    private const BADGE_DEFS = [
        ['id' => 'primeiro_treino', 'emoji' => '🥉', 'label' => 'Primeiro treino'],
        ['id' => 'dez_treinos', 'emoji' => '💪', 'label' => '10 treinos'],
        ['id' => 'trinta_treinos', 'emoji' => '🏆', 'label' => '30 treinos'],
        ['id' => 'sequencia_7', 'emoji' => '🔥', 'label' => 'Sequência de 7 dias'],
        ['id' => 'sequencia_30', 'emoji' => '⚡', 'label' => 'Sequência de 30 dias'],
    ];

    /**
     * Sequência atual de dias consecutivos (até hoje ou ontem) com pelo menos
     * um treino concluído.
     *
     * @param  \DateTimeInterface[]  $datasConcluidas
     */
    public static function calcularStreak(array $datasConcluidas): int
    {
        if (count($datasConcluidas) === 0) {
            return 0;
        }

        $dias = array_flip(array_map(
            fn ($d) => CarbonImmutable::parse($d)->toDateString(),
            $datasConcluidas
        ));

        $hoje = CarbonImmutable::now()->startOfDay();
        $ontem = $hoje->subDay();

        if (isset($dias[$hoje->toDateString()])) {
            $cursor = $hoje;
        } elseif (isset($dias[$ontem->toDateString()])) {
            $cursor = $ontem;
        } else {
            return 0;
        }

        $streak = 0;
        while (isset($dias[$cursor->toDateString()])) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    /** @return array{id: string, emoji: string, label: string}[] */
    public static function calcularBadges(int $totalSessoes, int $streak): array
    {
        $checks = [
            'primeiro_treino' => fn () => $totalSessoes >= 1,
            'dez_treinos' => fn () => $totalSessoes >= 10,
            'trinta_treinos' => fn () => $totalSessoes >= 30,
            'sequencia_7' => fn () => $streak >= 7,
            'sequencia_30' => fn () => $streak >= 30,
        ];

        return array_values(array_filter(
            self::BADGE_DEFS,
            fn ($badge) => $checks[$badge['id']]()
        ));
    }
}
