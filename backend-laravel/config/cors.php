<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // Sem prefixo "/api" nas rotas (ver bootstrap/app.php) — libera CORS em tudo,
    // já que este backend só serve a API do TrainOS.
    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:3101'),
        // Portas extras de .claude/launch.json (trainos-frontend-prod,
        // trainos-frontend-verify) — só em local, pra testar uma instância
        // isolada do frontend sem depender do FRONTEND_URL, que é
        // compartilhado entre sessões de chat rodando no mesmo repo. Nunca
        // adicionado em produção.
        ...(env('APP_ENV') === 'local' ? ['http://localhost:3111', 'http://localhost:3112'] : []),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
