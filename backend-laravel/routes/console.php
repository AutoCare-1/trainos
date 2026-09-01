<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Em produção isso só roda de verdade se houver um cron real chamando
// `php artisan schedule:run` a cada minuto (ver README/deploy) — ainda não
// configurado porque o app não foi implantado ainda.
Schedule::command('notifications:process')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('assinatura:verificar-carencia')
    ->daily()
    ->withoutOverlapping();

// Sem isso o disco cresce pra sempre: a foto de uma refeição pesa até 8 MB, e
// o diário não tem prazo de validade. A poda tira só a imagem e mantém o
// texto — ver PodarFotosDeRefeicao.
Schedule::command('nutricao:podar-fotos')
    ->daily()
    ->withoutOverlapping();
