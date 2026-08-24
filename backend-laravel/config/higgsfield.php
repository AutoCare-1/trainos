<?php

// Higgsfield (higgsfield.ai) — geração de imagem/vídeo de demonstração de
// exercício a partir de fotos de referência de um modelo real.
//
// Existe porque 571 dos 646 exercícios da biblioteca não têm foto: o wger.de,
// fonte dos 75 originais, só tem 66 traduções em português e 365 imagens no
// acervo inteiro. Ver database/seeders/ExercicioBibliotecaAmpliadaSeeder.php.
return [
    // Credencial de servidor. Nunca vai pro frontend e nunca é logada — o
    // header é montado dentro de App\Support\Higgsfield.
    'key_id' => env('HIGGSFIELD_KEY_ID'),
    'key_secret' => env('HIGGSFIELD_KEY_SECRET'),

    'base_url' => env('HIGGSFIELD_BASE_URL', 'https://platform.higgsfield.ai'),

    // Kill-switch no mesmo espírito de config/ia_pipelines.php: desligar a
    // geração sem precisar de deploy nem de mexer na credencial.
    'habilitado' => (bool) env('HIGGSFIELD_HABILITADO', true),

    // TESTADO DE VERDADE em 22/08/2026, com as fotos do personal. O resultado
    // muda a recomendação, então vale ler antes de mexer aqui:
    //
    // - soul/reference (imagem) NÃO serve pra demonstrar exercício. É modelo de
    //   retrato: trata a foto de referência como pose a copiar. Pedimos
    //   "agachamento búlgaro" e as 3 tentativas devolveram a pessoa PARADA EM
    //   PÉ, sem halteres e sem banco — o cenário e a postura da foto original.
    // - Modelo de VÍDEO com image_references acertou o movimento na primeira
    //   tentativa (desce até a coxa paralela e volta), mantendo a identidade.
    // - O prompt não pode nomear o exercício: o modelo não sabe o que é
    //   "agachamento búlgaro", mas sabe desenhar "pé traseiro apoiado no banco,
    //   joelho da frente a 90 graus". Descrever o CORPO, não o nome.
    // - Duração mínima de vídeo é 4s (não dá pra fazer de 3).
    //
    // Custo por unidade: imagem ~0,12 crédito, vídeo 4s ~4 créditos. Pros 571
    // exercícios sem foto isso é ~69 créditos em imagem e ~2.284 em vídeo.
    'endpoints' => [
        // Mantido só por completude da API — ver a nota acima antes de usar
        // pra demonstração de exercício, porque não funcionou pra isso.
        'imagem_referencia' => '/higgsfield-ai/soul/reference',
        // Array de fotos de referência + prompt -> vídeo curto.
        'video_referencia' => '/veo3.1/reference-to-video',
        // Anima uma imagem já existente.
        'imagem_para_video' => '/higgsfield-ai/dop/standard',
    ],

    // Quando o Soul ID for treinado pelo site, colar o id aqui habilita o
    // endpoint de melhor consistência (/higgsfield-ai/soul/character).
    'soul_id' => env('HIGGSFIELD_SOUL_ID'),

    // Fotos do modelo usadas como referência de identidade. Fica em
    // storage/app/private de propósito: é imagem de pessoa real, então não vai
    // pro git (o .gitignore de private/ ignora tudo) nem é servida pela web.
    //
    // CONSENTIMENTO: o modelo é o personal do Filipe e liberou o uso da
    // imagem dele para gerar as demonstrações por IA deste produto
    // (confirmado com ele em 24/08/2026). O registro fica aqui, junto do
    // caminho das fotos, porque é aqui que alguém tropeça na pergunta.
    // As fotos continuam fora do git mesmo com a liberação — autorização de
    // uso não é motivo pra espalhar o arquivo original.
    'dir_referencias' => env('HIGGSFIELD_DIR_REFERENCIAS', storage_path('app/private/higgsfield/referencias')),

    'padroes' => [
        'aspect_ratio' => '3:4',   // retrato, mesma proporção das fotos do modelo
        'resolution' => '720p',
        'batch_size' => 1,

        // Vídeo é cobrado por resolução: 480p = 4 créditos, 720p = 10. O
        // Filipe aprovou o 480p olhando os vídeos, então 720p aqui seria pagar
        // 2,5x por uma diferença que ele já disse não precisar — e o valor
        // estava cravado no código, onde ninguém revisa. Custo de fechar os
        // ~200 exercícios que faltam: ~790 créditos em 480p, ~1.980 em 720p.
        //
        // O formato sem o "p" é o que este endpoint (veo3.1) aceitava antes;
        // não foi exercitado com "480" contra a API real ainda. Se ele
        // recusar, a geração falha na hora com erro visível — não gera errado
        // em silêncio.
        'resolution_video' => env('HIGGSFIELD_RESOLUCAO_VIDEO', '480'),
    ],

    // O arquivo gerado fica no CDN deles por ~7 dias. Precisa ser baixado e
    // guardado por nós, senão a biblioteca fica com link quebrado depois.
    'poll' => [
        'intervalo_segundos' => 3,
        'tentativas_max' => 100,
    ],
];
