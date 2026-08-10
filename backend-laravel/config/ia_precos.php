<?php

// Preços da API da Anthropic, em USD por MILHÃO de tokens. Curado à mão (mesmo
// critério de config/planos_assinatura.php): muda raramente e não vale uma tela
// de admin. Fonte: tabela pública de preços da Anthropic.
//
// IMPORTANTE: mudar um preço aqui NÃO reescreve o histórico — App\Support\IaUsage
// congela o custo em USD na hora da chamada. Esta tabela só vale pra chamadas
// novas, que é o comportamento certo pra um relatório financeiro.
return [
    // Cotação usada só para EXIBIR o custo em reais no CRM (o gasto real é em
    // USD). Não busca cotação em API externa de propósito: uma dependência de
    // rede no meio do dashboard financeiro quebraria a tela quando a API caísse,
    // e a precisão de centavo não muda nenhuma decisão aqui. Ajustar quando o
    // câmbio andar muito.
    'usd_brl' => (float) env('IA_COTACAO_USD_BRL', 5.40),

    'modelos' => [
        'claude-haiku-4-5-20251001' => [
            'input' => 1.00,
            'output' => 5.00,
            // Escrita de cache custa 1,25x a entrada; leitura custa 0,1x.
            'cache_write' => 1.25,
            'cache_read' => 0.10,
        ],
    ],

    // Preço por busca da tool nativa web_search (cobrada por busca, não por
    // token). Usada só pelo pipeline de ideias de conteúdo.
    'web_search_por_busca' => 0.01,

    // Usado quando aparece um modelo sem preço cadastrado: o custo entra como 0
    // e o CRM avisa na tela, em vez de estimar errado silenciosamente.
    'modelo_padrao' => 'claude-haiku-4-5-20251001',
];
