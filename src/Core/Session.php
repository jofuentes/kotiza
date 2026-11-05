<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    /** Cierre por inactividad (segundos) */
    private const IDLE_TIMEOUT = 8 * 3600;

    public static function start(string $savePath): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = str_starts_with((string) Env::get('APP_URL', ''), 'https://');
        if (is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
        }
        session_name('cotizador_sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.gc_maxlifetime', (string) self::IDLE_TIMEOUT);
        session_start();

        $now = time();
        if (isset($_SESSION['_last']) && $now - $_SESSION['_last'] > self::IDLE_TIMEOUT) {
            self::destroy();
            session_start();
        }
        $_SESSION['_last'] = $now;

        // Los mensajes flash viven exactamente una petición
        $_SESSION['_flash_now'] = $_SESSION['_flash'] ?? [];
        $_SESSION['_flash'] = [];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000, 'path' => $p['path'], 'secure' => $p['secure'],
                'httponly' => $p['httponly'], 'samesite' => $p['samesite'],
            ]);
        }
        session_destroy();
    }

    /** @param 'success'|'error'|'info' $type */
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function flashes(): array
    {
        return $_SESSION['_flash_now'] ?? [];
    }
}
