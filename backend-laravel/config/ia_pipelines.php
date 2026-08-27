<?php

// Kill-switch por pipeline de IA — permite desligar uma feature específica em
// produção via variável de ambiente, sem precisar de deploy, caso ela comece a
// travar ou custar mais que o esperado sob uso real. Ver App\Support\KillSwitchIa.
return [
    'analisar_forma' => env('IA_ANALISAR_FORMA_ATIVO', true),
    'academia_analise' => env('IA_ACADEMIA_ANALISE_ATIVO', true),
    'academia_recomendacao' => env('IA_ACADEMIA_RECOMENDACAO_ATIVO', true),
    'evolucao_fisica' => env('IA_EVOLUCAO_FISICA_ATIVO', true),
    'avaliacao_postural' => env('IA_AVALIACAO_POSTURAL_ATIVO', true),
    'chat_autopilot' => env('IA_CHAT_AUTOPILOT_ATIVO', true),
    'consultor_ia' => env('IA_CONSULTOR_ATIVO', true),
    'ideias_conteudo' => env('IA_CONTEUDO_ATIVO', true),

    // Teto de gasto com IA por dia, em USD. Diferente do kill-switch acima, que
    // é global e derruba a feature pra todo mundo: aqui o corte é por personal,
    // então abuso concentrado numa conta (ou num invite_token vazado, que não
    // expira nem rotaciona) para nela em vez de tirar a feature do ar pros
    // outros clientes. Chamada sem personal resolvido cai no teto global.
    // 0 ou negativo desliga a checagem.
    'teto_diario_usd_por_personal' => (float) env('IA_TETO_DIARIO_USD_POR_PERSONAL', 5.0),
    'teto_diario_usd_global' => (float) env('IA_TETO_DIARIO_USD_GLOBAL', 50.0),
];
