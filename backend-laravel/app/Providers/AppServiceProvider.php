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

        // Chat do aluno: cada POST dispara uma chamada Haiku com até 4000
        // caracteres mais 30 mensagens de histórico. Chaveado pelo token pelo
        // mesmo motivo dos dois de cima — o invite_token não expira nem
        // rotaciona, e circula por WhatsApp. Mais folgado que o postural
        // porque conversa de verdade tem rajada (o aluno manda 3, 4 seguidas).
        RateLimiter::for('chat-ia-portal', function (Request $request) {
            return Limit::perMinute(20)->by((string) $request->route('token'));
        });

        // Orientação de pré/pós-treino: um botão que o aluno aperta e que chama
        // a Anthropic. Mais apertado que o chat porque não há conversa aqui —
        // pedir 6 vezes na mesma hora não traz resposta melhor, só custo.
        RateLimiter::for('nutricao-portal', function (Request $request) {
            return Limit::perHour(6)->by((string) $request->route('token'));
        });

        // Lado do personal: já é rota autenticada, então chaveia pelo dono do
        // JWT — o teto de gasto diário (KillSwitchIa) cuida do custo, aqui é só
        // pra não deixar um script fazer rajada.
        RateLimiter::for('chat-ia-personal', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });
    }
}
