<?php

namespace Tests\Unit;

use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Item 7 de uma segunda revisão externa: dedup diário, teto de notificações
 * por dia, o horário de sem_treinar_hoje e os dias de semana das regras
 * semanais dependem de "que dia/hora é agora" bater com o relógio de parede
 * do Brasil — travado aqui pra nunca mais voltar pra UTC sem ninguém notar.
 */
class TimezoneTest extends TestCase
{
    public function test_timezone_da_aplicacao_e_brasil_nao_utc(): void
    {
        $this->assertNotSame('UTC', config('app.timezone'));
        $this->assertSame('America/Sao_Paulo', config('app.timezone'));
    }

    public function test_now_reflete_o_fuso_configurado(): void
    {
        $this->assertSame('America/Sao_Paulo', now()->timezoneName);
        $this->assertSame(-3 * 3600, Carbon::now()->getOffset(), 'offset deveria ser UTC-3 (Brasil), não UTC');
    }
}
