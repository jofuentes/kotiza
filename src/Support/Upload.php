<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Subida segura de archivos: valida tipo real (finfo), tamaño y contenido,
 * y guarda con nombre aleatorio en storage/{carpeta}/{empresa}/.
 */
final class Upload
{
    public const IMAGE = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
    public const IMAGE_SVG = self::IMAGE + ['image/svg+xml' => 'svg'];
    public const LETTERHEAD = self::IMAGE + ['application/pdf' => 'pdf'];

    /** Carpetas de storage que se sirven por /archivos */
    public const PUBLIC_DIRS = ['logos', 'letterheads', 'signatures'];

    /**
     * @param array<string,string> $allowed mime => extensión
     * @return string ruta relativa a storage/ (ej. logos/3/ab12….png)
     */
    public static function store(array $file, string $dir, int $companyId, array $allowed, int $maxBytes): string
    {
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error !== UPLOAD_ERR_OK) {
            throw new UploadException(match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo es demasiado grande.',
                UPLOAD_ERR_PARTIAL => 'El archivo se subió incompleto. Intenta de nuevo.',
                default => 'No se pudo subir el archivo.',
            });
        }
        if (!is_uploaded_file($file['tmp_name']) && !is_file($file['tmp_name'])) {
            throw new UploadException('Archivo inválido.');
        }
        if ($file['size'] > $maxBytes) {
            throw new UploadException('El archivo excede el máximo de ' . round($maxBytes / 1048576, 1) . ' MB.');
        }

        $mime = self::detectMime($file['tmp_name']);
        if (!isset($allowed[$mime])) {
            $tipos = implode(', ', array_unique(array_map('strtoupper', $allowed)));
            throw new UploadException("Tipo de archivo no permitido. Usa: {$tipos}.");
        }
        $ext = $allowed[$mime];

        if ($ext === 'svg') {
            self::assertSafeSvg((string) file_get_contents($file['tmp_name']));
        } elseif ($ext !== 'pdf' && @getimagesize($file['tmp_name']) === false) {
            throw new UploadException('La imagen está dañada o no es válida.');
        }

        $relDir = $dir . '/' . $companyId;
        $absDir = storage_path($relDir);
        if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            throw new \RuntimeException("No se pudo crear {$absDir}");
        }
        $name = bin2hex(random_bytes(12)) . '.' . $ext;
        $dest = $absDir . '/' . $name;
        $moved = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $dest)
            : rename($file['tmp_name'], $dest);
        if (!$moved) {
            throw new \RuntimeException('No se pudo guardar el archivo.');
        }
        return $relDir . '/' . $name;
    }

    public static function delete(?string $relPath): void
    {
        if ($relPath && !str_contains($relPath, '..')) {
            $abs = storage_path($relPath);
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
    }

    public static function detectMime(string $path): string
    {
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        // finfo puede reportar SVG como text/xml o text/plain
        if (in_array($mime, ['text/xml', 'application/xml', 'text/plain', 'image/svg'], true)) {
            $head = (string) file_get_contents($path, false, null, 0, 2048);
            if (preg_match('/<svg[\s>]/i', $head)) {
                return 'image/svg+xml';
            }
        }
        return $mime;
    }

    /** Rechaza SVG con scripts, manejadores de eventos o recursos externos */
    public static function assertSafeSvg(string $svg): void
    {
        $patterns = [
            '/<\s*script/i', '/\son[a-z]+\s*=/i', '/javascript\s*:/i', '/<\s*foreignObject/i',
            '/<!ENTITY/i', '/<\s*iframe/i', '/<\s*embed/i', '/<\s*object/i',
            '/(xlink:)?href\s*=\s*["\']\s*(?!#|data:image\/)/i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $svg)) {
                throw new UploadException('El SVG contiene elementos no permitidos (scripts o enlaces externos). Expórtalo como SVG simple o usa PNG.');
            }
        }
        if (!preg_match('/<svg[\s>]/i', $svg)) {
            throw new UploadException('El archivo no es un SVG válido.');
        }
    }
}
