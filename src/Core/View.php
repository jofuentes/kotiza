<?php
declare(strict_types=1);

namespace App\Core;

/** Plantillas PHP simples. Toda salida dinámica debe pasar por e(). */
final class View
{
    private static string $dir = '';
    private static array $shared = [];

    public static function setDirectory(string $dir): void
    {
        self::$dir = rtrim($dir, '/');
    }

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::renderFile($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::renderFile($layout, array_merge($data, ['content' => $content]));
    }

    public static function partial(string $template, array $data = []): string
    {
        return self::renderFile($template, $data);
    }

    private static function renderFile(string $template, array $data): string
    {
        $file = self::$dir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Plantilla no encontrada: {$template}");
        }
        extract(array_merge(self::$shared, $data), EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
