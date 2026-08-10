<?php

use App\Http\Middleware\AdminOnly;
use App\Http\Middleware\JwtAuthenticate;
use App\Http\Middleware\NormalizeTimestampsToIso8601;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Sem prefixo "/api" — o backend Node monta as rotas direto na raiz
        // (/auth, /alunos, /exercicios...) e o frontend espera esse mesmo formato.
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // App 100% API (sem tela de login web) — nunca redireciona convidado
        // não autenticado, deixa a AuthenticationException virar 401 JSON.
        $middleware->redirectGuestsTo(fn () => null);

        // Normaliza timestamps de qualquer resposta JSON pro mesmo formato ISO 8601
        // que o driver pg do Node sempre entrega — ver a classe pra contexto completo.
        $middleware->append(NormalizeTimestampsToIso8601::class);

        // Substitui o guard padrão do jwt-auth (auth:api) nas rotas do profissional —
        // ver JwtAuthenticate pra entender por que (consulta ao banco a mais que o
        // Node nunca fez, e que virava "não autenticado" quando o banco soluçava).
        $middleware->alias([
            'auth.jwt' => JwtAuthenticate::class,
            // CRM interno do produto — sempre encadeado depois de auth.jwt.
            'admin' => AdminOnly::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sem prefixo "/api" nas rotas (ver withRouting acima) — este backend só
        // serve API, então toda resposta de erro é JSON.
        $exceptions->shouldRenderJsonWhen(fn () => true);

        // Espelha o handler do Node pro erro 22P02 do Postgres (invalid_text_representation
        // — ex: id/token passado na URL que não é um UUID válido).
        $exceptions->render(function (\Illuminate\Database\QueryException $e, Request $request) {
            if ($e->getCode() === '22P02') {
                return response()->json(['error' => 'Identificador inválido'], 400);
            }
            return null;
        });

        // Mesmo formato de erro do requireAuth() do Node ({ error: '...' }, não o
        // { message: '...' } padrão do Laravel).
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            return response()->json(['error' => 'Não autenticado'], 401);
        });
    })->create();
