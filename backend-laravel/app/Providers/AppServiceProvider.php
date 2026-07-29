<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Item 8 de uma segunda revisão externa: throttle:10,1 (padrão) chaveia
        // por IP — penalizaria uma academia/escola inteira atrás do mesmo IP,
        // e não impediria abuso concentrado num único token de portal vazado
        // (vindo de IPs diferentes). Chaveado pelo token do portal em vez de IP.
        RateLimiter::for('push-portal', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->route('token'));
        });

        // Terceira varredura de bugs: /portal/{token}/postural chama Claude Haiku
        // com até 6 imagens por request — sem limite, um token vazado gera custo de
        // IA e gravação em disco em loop. Chaveado pelo token (mesmo motivo do
        // push-portal), limite mais apertado por ser bem mais caro que um subscribe.
        RateLimiter::for('postural-portal', function (Request $request) {
            return Limit::perHour(5)->by((string) $request->route('token'));
        });
    }
}
