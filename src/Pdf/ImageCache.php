<?php
declare(strict_types=1);

namespace App\Pdf;

/**
 * Copia local de imágenes remotas de producto para el PDF (mPDF no debe descargar en cada render).
 * Solo http(s), máx. 3 MB, tipos jpeg/png/gif; se guarda en storage/cache/img.
 */
final class ImageCache
{
    private const MAX_BYTES = 3 * 1048576;
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];

    public static function local(?string $url): ?string
    {
        if (!$url || !preg_match('#^https?://#i', $url)) {
            return null;
        }
        $dir = storage_path('cache/img');
        $base = $dir . '/' . sha1($url);
        foreach (self::TYPES as $ext) {
            if (is_file("{$base}.{$ext}")) {
                return "{$base}.{$ext}";
            }
        }
        if (is_file("{$base}.fail") && filemtime("{$base}.fail") > time() - 86400) {
            return null; // no reintentar URLs rotas durante un día
        }
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        $data = self::download($url);
        if ($data === null) {
            @touch("{$base}.fail");
            return null;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($data);
        if (!isset(self::TYPES[$mime]) || @getimagesizefromstring($data) === false) {
            @touch("{$base}.fail");
            return null;
        }
        $path = "{$base}." . self::TYPES[$mime];
        file_put_contents($path, $data);
        return $path;
    }

    private static function download(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_USERAGENT => 'Kotiza/1.0',
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => fn ($c, $dlTotal, $dl) => $dl > self::MAX_BYTES ? 1 : 0,
        ]);
        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return is_string($data) && $code === 200 && $data !== '' ? $data : null;
    }
}
