<?php

// Como as pessoas chamam o alimento, quando não é como o IBGE chama.
//
// A busca compara com o nome do catálogo, e o catálogo usa o nome que a POF
// registrou. Só que ninguém no Sudeste digita "pão de sal": digita "pão
// francês". Busca que não acha pão francês faz o aluno concluir que o app não
// tem pão — e ele tem.
//
// A chave é o código do alimento na POF, e vale pra todos os preparos dele. Os
// termos entram SÓ no campo de busca, nunca na tela: sinônimo errado atrapalha
// uma busca, não mente sobre o que a pessoa comeu.
//
// Regra pra entrar aqui: tem que ser o MESMO alimento com outro nome, não um
// alimento parecido. Por isso tangerina, mexerica e bergamota ficam de fora —
// a POF mediu as três separado, com valores diferentes, e juntar seria decidir
// por ela.
return [
    // Pão francês, de sal, cacetinho, pãozinho, filão: o mesmo pão.
    8000105 => 'pão francês pãozinho cacetinho filão',
    // Mandioca, aipim e macaxeira são a mesma raiz. A POF separou pelo nome que
    // o entrevistado usou, então cada uma ficou com preparos diferentes — quem
    // procura "macaxeira cozida" precisa achar a linha que ficou em "aipim".
    6400601 => 'aipim macaxeira',
    6400609 => 'mandioca macaxeira',
    6400610 => 'mandioca aipim',
];
