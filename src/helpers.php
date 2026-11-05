<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Env;

/** Nombre visible de la aplicación (APP_NAME, por defecto «Kotiza») */
function app_name(): string
{
    return (string) Env::get('APP_NAME', 'Kotiza');
}

/** Escapa texto para HTML */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL absoluta de la app a partir de una ruta interna */
function url(string $path = '/'): string
{
    if (preg_match('#^https?://#', $path)) {
        return $path;
    }
    $base = rtrim((string) Env::get('APP_URL', ''), '/');
    $basePath = (string) parse_url($base, PHP_URL_PATH);
    return rtrim($basePath, '/') . '/' . ltrim($path, '/');
}

/** URL de un archivo en /public con versión para evitar caché vieja */
function asset(string $path): string
{
    $file = dirname(__DIR__) . '/public/' . ltrim($path, '/');
    $v = is_file($file) ? '?v=' . filemtime($file) : '';
    return url($path) . $v;
}

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function base_path(string $path = ''): string
{
    return dirname(__DIR__) . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function storage_path(string $path = ''): string
{
    return base_path('storage' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
}
