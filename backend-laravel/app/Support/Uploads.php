<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Espelha backend/src/middleware/upload.ts do Node: dois padrões de upload,
 * um público (servido direto por URL) e um privado por chave (só acessível
 * via rota autenticada que confere o dono do dado antes de ler do disco).
 */
class Uploads
{
    /**
     * Extensão segura derivada do MIME real do arquivo (detectado por conteúdo,
     * via finfo) — nunca do nome original enviado pelo cliente. Um arquivo
     * "foto.jpg" com payload PHP embutido (polyglot) ainda seria detectado como
     * image/jpeg aqui, mas salva com extensão .jpg (inerte), não .php.
     */
    private static function extensaoSegura(UploadedFile $file): string
    {
        $extensoesPorMime = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
            'video/webm' => 'webm',
            'video/x-msvideo' => 'avi',
        ];

        $extensao = $extensoesPorMime[$file->getMimeType()] ?? null;
        if ($extensao === null) {
            throw new InvalidArgumentException('Tipo de arquivo não suportado');
        }

        return $extensao;
    }

    /** Raiz dos arquivos sensíveis — nunca exposta via rota estática pública. */
    public static function privateRoot(): string
    {
        return storage_path('app/private-uploads');
    }

    /** Raiz dos arquivos públicos, dentro do webroot (public/uploads). */
    public static function publicRoot(): string
    {
        return public_path('uploads');
    }

    /**
     * Salva em public/uploads/<subdir>, nome de arquivo aleatório.
     * Retorna a URL pública relativa (ex: "/uploads/exercise-videos/abc123.mp4").
     */
    public static function storePublic(UploadedFile $file, string $subdir): string
    {
        $dir = self::publicRoot().'/'.$subdir;
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = Str::random(20).'.'.self::extensaoSegura($file);
        $file->move($dir, $filename);

        return "/uploads/{$subdir}/{$filename}";
    }

    /**
     * Salva em storage/app/private-uploads/<subdir>/<chave>/, onde <chave> é o
     * token do aluno (ou outro identificador) vindo direto de um param de rota.
     *
     * A chave roda ANTES de qualquer handler validar o dono do dado — nunca
     * confiar nela sem checar o formato, senão um valor com ".." escaparia de
     * privateRoot() via concatenação de path (mesmo fix de path traversal já
     * aplicado uma vez no Node em criarUploaderPrivadoPorChave).
     *
     * Retorna o file_path relativo (ex: "checkins/<chave>/abc123.jpg"), no
     * mesmo formato salvo na coluna file_path das tabelas checkins/body_photos.
     */
    public static function storePrivate(UploadedFile $file, string $subdir, string $chave): string
    {
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $chave)) {
            throw new InvalidArgumentException('Identificador inválido');
        }

        $dir = self::privateRoot()."/{$subdir}/{$chave}";
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = Str::random(20).'.'.self::extensaoSegura($file);
        $file->move($dir, $filename);

        return "{$subdir}/{$chave}/{$filename}";
    }

    /** Caminho absoluto de um arquivo privado, a partir do file_path salvo no banco. */
    public static function privateAbsolutePath(string $relativeFilePath): string
    {
        $path = self::privateRoot().'/'.ltrim($relativeFilePath, '/');
        $real = realpath($path);

        // Defesa em profundidade: mesmo com a chave já validada na escrita, confere
        // de novo na leitura que o caminho resolvido continua dentro de privateRoot().
        if ($real === false || ! str_starts_with($real, realpath(self::privateRoot()))) {
            throw new RuntimeException('Arquivo não encontrado');
        }

        return $real;
    }

    /** Remove um arquivo privado (ex: check-in do dia substituído), sem travar se já não existir. */
    public static function deletePrivateQuietly(string $relativeFilePath): void
    {
        try {
            $path = self::privateAbsolutePath($relativeFilePath);
            @unlink($path);
        } catch (RuntimeException) {
            // arquivo já não existe — nada a fazer
        }
    }
}
