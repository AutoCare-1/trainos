<?php

// Faixas de preço da assinatura do personal com o TrainOS, por número máximo de
// alunos ativos permitidos. Curado à mão (não é banco) — mudar preço/limite é
// editar aqui e fazer deploy, não precisa de tela de admin pra algo que muda
// raramente. limite_alunos = null significa sem limite.
return [
    'dias_teste_gratis' => (int) env('ASSINATURA_DIAS_TESTE_GRATIS', 7),
    'dias_carencia' => (int) env('ASSINATURA_DIAS_CARENCIA', 3),

    'planos' => [
        'custom' => ['nome' => 'Custom', 'limite_alunos' => 50, 'valor_mensal' => 79.90],
        'exclusive' => ['nome' => 'Exclusive', 'limite_alunos' => 100, 'valor_mensal' => 149.90],
        'plus' => ['nome' => 'Plus', 'limite_alunos' => 150, 'valor_mensal' => 199.90],
        'master' => ['nome' => 'Master', 'limite_alunos' => 250, 'valor_mensal' => 249.90],
        'top_500' => ['nome' => 'Top 500', 'limite_alunos' => 500, 'valor_mensal' => 399.90],
        'top_1000' => ['nome' => 'Top 1000', 'limite_alunos' => 1000, 'valor_mensal' => 499.00],
        'top_2000' => ['nome' => 'Top 2000', 'limite_alunos' => 2000, 'valor_mensal' => 689.00],
        'top_2500' => ['nome' => 'Top 2500', 'limite_alunos' => 2500, 'valor_mensal' => 725.00],
    ],
];
