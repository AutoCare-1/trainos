<?php

// Kill-switch por pipeline de IA — permite desligar uma feature específica em
// produção via variável de ambiente, sem precisar de deploy, caso ela comece a
// travar ou custar mais que o esperado sob uso real. Ver App\Support\KillSwitchIa.
return [
    'analisar_forma' => env('IA_ANALISAR_FORMA_ATIVO', true),
    'academia_analise' => env('IA_ACADEMIA_ANALISE_ATIVO', true),
    'academia_recomendacao' => env('IA_ACADEMIA_RECOMENDACAO_ATIVO', true),
    'evolucao_fisica' => env('IA_EVOLUCAO_FISICA_ATIVO', true),
    'chat_autopilot' => env('IA_CHAT_AUTOPILOT_ATIVO', true),
    'consultor_ia' => env('IA_CONSULTOR_ATIVO', true),
    'ideias_conteudo' => env('IA_CONTEUDO_ATIVO', true),
];
