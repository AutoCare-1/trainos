<?php

namespace Tests\Unit;

use App\Support\Gamification;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class GamificationTest extends TestCase
{
    public function test_streak_nao_colapsa_sessao_de_23h_com_sessao_de_08h_do_dia_seguinte(): void
    {
        // Duas sessões em dias de calendário BRASILEIRO consecutivos, mas que
        // caem no MESMO dia em UTC (23h de Brasília = 02h UTC do dia seguinte;
        // 08h de Brasília do dia seguinte = 11h UTC do mesmo dia seguinte).
        // Antes do fix, calcularStreak convertia pra UTC e via as duas datas
        // como o mesmo dia UTC, quebrando a contagem de sequência.
        $ontem23h = CarbonImmutable::now()->subDay()->setTime(23, 0);
        $hoje08h = CarbonImmutable::now()->setTime(8, 0);

        $streak = Gamification::calcularStreak([$ontem23h, $hoje08h]);

        $this->assertSame(2, $streak);
    }

    public function test_streak_conta_a_partir_de_ontem_se_ainda_nao_treinou_hoje(): void
    {
        $ontem = CarbonImmutable::now()->subDay();
        $anteontem = CarbonImmutable::now()->subDays(2);

        $streak = Gamification::calcularStreak([$anteontem, $ontem]);

        $this->assertSame(2, $streak);
    }

    public function test_streak_zero_quando_ultima_sessao_foi_ha_mais_de_um_dia(): void
    {
        $streak = Gamification::calcularStreak([CarbonImmutable::now()->subDays(3)]);

        $this->assertSame(0, $streak);
    }
}
