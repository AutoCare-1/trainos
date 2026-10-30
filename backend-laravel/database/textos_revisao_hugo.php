<?php

/*
 * Equipamento e instrução corrigidos a pedido do Hugo, sem trocar o nome.
 * Aplicado pelo `exercicios:corrigir-textos` no entrypoint (os seeders só
 * inserem exercício novo, não atualizam texto de quem já existe).
 *
 * Nome => [campo => valor]. Campos aceitos: equipment, instructions.
 */
return [
    // #474 (lista de 13/10): "no lugar de uma toalha, usar o elástico". O Hugo
    // não pediu rename; o vídeo novo mostra elástico. 30/10, decisão do Filipe.
    'Alongamento de cadeia posterior com toalha' => [
        'equipment' => 'Elástico',
        'instructions' => 'Deitado, passe o elástico na sola do pé e puxe a perna estendida em sua direção.',
    ],
];
