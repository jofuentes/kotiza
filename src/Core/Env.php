<?php
declare(strict_types=1);

namespace App\Core;

/** Acceso a variables de entorno (.env o variables del contenedor). */
final class Env
{
    private static bool $loaded = false;

    public static function load(string $basePath): void
    {
        if (self::$loaded) {
            return;
        }
        $file = $basePath . '/.env';
        if (is_file($file)) {
            foreach (self::parse((string) file_get_contents($file)) as $key => $value) {
                // Las variables del entorno real (contenedor) tienen prioridad sobre el archivo
                if (!array_key_exists($key, $_ENV) && !array_key_exists($key, $_SERVER) && getenv($key) === false) {
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
        self::$loaded = true;
    }

    /**
     * Interpreta el contenido de un .env: KEY=valor, `export KEY=valor`, comentarios (#) y
     * valores entre comillas simples o dobles. No admite ${VAR} ni valores de varias líneas.
     *
     * @return array<string, string>
     */
    public static function parse(string $contents): array
    {
        $vars = [];
        foreach (preg_split('/\R/', $contents) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim((string) preg_replace('/^export\s+/', '', $key));
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $key) === 1) {
                $vars[$key] = self::parseValue(ltrim($value));
            }
        }
        return $vars;
    }

    private static function parseValue(string $value): string
    {
        if (preg_match('/^"((?:[^"\\\\]|\\\\.)*)"/', $value, $m) === 1) {
            return strtr($m[1], ['\\\\' => '\\', '\\"' => '"', '\\n' => "\n", '\\r' => "\r", '\\t' => "\t"]);
        }
        if (preg_match("/^'([^']*)'/", $value, $m) === 1) {
            return $m[1];
        }
        // Sin comillas: un # precedido de espacio inicia un comentario
        return trim((string) preg_replace('/(^|\s)#.*$/', '', $value));
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return (string) $value;
    }

    public static function require(string $key): string
    {
        $value = self::get($key);
        if ($value === null) {
            throw new \RuntimeException("Falta la variable de entorno {$key}.");
        }
        return $value;
    }
}
