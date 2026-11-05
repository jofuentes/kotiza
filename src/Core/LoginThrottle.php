<?php
declare(strict_types=1);

namespace App\Core;

/** Límite de intentos de inicio de sesión por correo y por IP. */
final class LoginThrottle
{
    public const WINDOW_MINUTES = 15;
    public const MAX_PER_EMAIL = 5;
    public const MAX_PER_IP = 20;

    /** Minutos que faltan para poder reintentar, o 0 si no está bloqueado */
    public static function lockedMinutes(string $email, string $ip): int
    {
        $since = date('Y-m-d H:i:s', time() - self::WINDOW_MINUTES * 60);
        $byEmail = (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts WHERE email = ? AND success = 0 AND attempted_at >= ?',
            [$email, $since]
        );
        $byIp = (int) Database::value(
            'SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND success = 0 AND attempted_at >= ?',
            [$ip, $since]
        );
        if ($byEmail < self::MAX_PER_EMAIL && $byIp < self::MAX_PER_IP) {
            return 0;
        }
        $oldest = (string) Database::value(
            'SELECT MIN(attempted_at) FROM login_attempts
              WHERE (email = ? OR ip = ?) AND success = 0 AND attempted_at >= ?',
            [$email, $ip, $since]
        );
        $unlock = strtotime($oldest) + self::WINDOW_MINUTES * 60;
        return max(1, (int) ceil(($unlock - time()) / 60));
    }

    public static function record(string $email, string $ip, bool $success): void
    {
        Database::insert('login_attempts', ['email' => $email, 'ip' => $ip, 'success' => (int) $success]);
        if ($success) {
            // Un acceso correcto limpia los fallos de ese correo
            Database::query('DELETE FROM login_attempts WHERE email = ? AND success = 0', [$email]);
        }
        // Limpieza ocasional de registros viejos
        if (random_int(1, 50) === 1) {
            Database::query('DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 30 DAY');
        }
    }
}
