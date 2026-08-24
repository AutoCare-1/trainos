<?php

// Onde ficam os vídeos de demonstração dos exercícios.
//
// São 327 arquivos, ~340 MB, e vão crescer junto com a biblioteca. Ficaram no
// disco local até 24/08/2026, o que quebrava de duas formas: quem clonava o
// repositório não recebia nada (public/uploads está no .gitignore) e produção
// não tinha onde servir.
//
// A escolha foi armazenamento de objetos com API compatível com S3, servido
// por CDN. O critério foi EGRESS, não armazenamento: 340 MB custam centavos em
// qualquer lugar, o que pesa é a banda de cada aluno assistindo. Por isso
// Cloudflare R2, que não cobra saída. Como a API é compatível com S3, trocar
// de provedor é mexer no .env — o código não muda.
//
// Com `disco` vazio o app continua servindo do disco local, que é o modo de
// desenvolvimento de quem tem os arquivos na máquina.
return [
    // Nome de um disco de config/filesystems.php. Vazio = disco local.
    'disco' => env('DEMONSTRACOES_DISCO'),

    // Prefixo público dos arquivos no CDN, sem barra no fim.
    // Ex.: https://midia.trainos.app/exercise-demos
    'base_url' => rtrim((string) env('DEMONSTRACOES_BASE_URL'), '/'),

    // Pasta dentro do bucket.
    'prefixo' => env('DEMONSTRACOES_PREFIXO', 'exercise-demos'),
];
