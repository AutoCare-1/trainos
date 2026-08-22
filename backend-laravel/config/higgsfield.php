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

    'endpoints' => [
        // 1 foto de referência + prompt -> imagem preservando a identidade.
        // É o caminho que funciona só com API: treinar um "Soul ID" (que daria
        // consistência melhor, via /higgsfield-ai/soul/character) NÃO tem
        // endpoint público — só dá pra criar pelo site.
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
    'dir_referencias' => env('HIGGSFIELD_DIR_REFERENCIAS', storage_path('app/private/higgsfield/referencias')),

    'padroes' => [
        'aspect_ratio' => '3:4',   // retrato, mesma proporção das fotos do modelo
        'resolution' => '720p',
        'batch_size' => 1,
    ],

    // O arquivo gerado fica no CDN deles por ~7 dias. Precisa ser baixado e
    // guardado por nós, senão a biblioteca fica com link quebrado depois.
    'poll' => [
        'intervalo_segundos' => 3,
        'tentativas_max' => 100,
    ],
];
