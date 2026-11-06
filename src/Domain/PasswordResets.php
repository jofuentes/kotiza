<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\Auth;
use App\Core\Database;
use App\Mail\MailMessage;
use App\Mail\Mailer;

/**
 * Recuperación de contraseña con enlaces de un solo uso.
 *
 * La petición web solo registra la solicitud y el envío ocurre en segundo plano
 * (bin/send-password-resets.php), para que el tiempo de respuesta no revele si el correo existe.
 */
final class PasswordResets
{
    public const LINK_MINUTES = 30;
    public const MAX_PER_USER_PER_HOUR = 3;
    public const MAX_PER_IP_PER_HOUR = 10;
    /** Una solicitud sin enviar después de este tiempo se descarta */
    private const PENDING_MINUTES = 10;

    /** Registra una solicitud. No indica si el correo existe. */
    public static function request(string $email, string $ip): void
    {
        $byIp = (int) Database::value(
            'SELECT COUNT(*) FROM password_resets WHERE ip = ? AND created_at >= NOW() - INTERVAL 1 HOUR',
            [$ip]
        );
        if ($byIp >= self::MAX_PER_IP_PER_HOUR) {
            return;
        }

        $userId = Database::value('SELECT id FROM users WHERE email = ? AND is_active = 1', [$email]);
        if ($userId) {
            $byUser = (int) Database::value(
                "SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND status <> 'ignored' AND created_at >= NOW() - INTERVAL 1 HOUR",
                [$userId]
            );
            if ($byUser >= self::MAX_PER_USER_PER_HOUR) {
                $userId = null;
            }
        }

        Database::query(
            'INSERT INTO password_resets (user_id, ip, status, expires_at)
             VALUES (?, ?, ?, NOW() + INTERVAL ' . self::LINK_MINUTES . ' MINUTE)',
            [$userId ?: null, $ip, $userId ? 'pending' : 'ignored']
        );
    }

    /**
     * Envía los correos pendientes.
     * @param callable(string):void $log
     * @return int correos enviados
     */
    public static function sendPending(?Mailer $mailer, string $appName, string $appUrl, callable $log): int
    {
        Database::query(
            "UPDATE password_resets SET status = 'failed'
              WHERE status = 'pending' AND created_at < NOW() - INTERVAL " . self::PENDING_MINUTES . ' MINUTE'
        );
        $rows = Database::fetchAll(
            "SELECT r.id, r.user_id, u.name, u.email
               FROM password_resets r JOIN users u ON u.id = r.user_id
              WHERE r.status = 'pending' AND u.is_active = 1 ORDER BY r.id"
        );
        $sent = 0;
        foreach ($rows as $row) {
            if ($mailer === null) {
                Database::query("UPDATE password_resets SET status = 'failed' WHERE id = ?", [$row['id']]);
                $log("solicitud {$row['id']}: no hay cuenta de correo configurada");
                continue;
            }
            $token = bin2hex(random_bytes(32));
            // Solo el enlace más reciente sigue vigente
            Database::query(
                "UPDATE password_resets SET status = 'cancelled', token_hash = NULL WHERE user_id = ? AND status = 'sent'",
                [$row['user_id']]
            );
            Database::query(
                "UPDATE password_resets SET status = 'sent', token_hash = ?, expires_at = NOW() + INTERVAL " . self::LINK_MINUTES . ' MINUTE WHERE id = ?',
                [self::hash($token), $row['id']]
            );
            try {
                $mailer->send((string) $row['email'], (string) $row['name'], MailMessage::passwordReset(
                    $appName, (string) $row['name'], self::link($appUrl, $token), self::LINK_MINUTES
                ));
                $sent++;
            } catch (\Throwable $e) {
                Database::query("UPDATE password_resets SET status = 'failed', token_hash = NULL WHERE id = ?", [$row['id']]);
                $log("solicitud {$row['id']}: no se pudo enviar: " . $e->getMessage());
            }
        }
        Database::query('DELETE FROM password_resets WHERE created_at < NOW() - INTERVAL 7 DAY');
        return $sent;
    }

    /** ¿El enlace sigue vigente? */
    public static function isValid(string $token): bool
    {
        return self::isWellFormed($token) && Database::value(
            "SELECT 1 FROM password_resets r JOIN users u ON u.id = r.user_id
              WHERE r.token_hash = ? AND r.status = 'sent' AND r.expires_at > NOW() AND u.is_active = 1",
            [self::hash($token)]
        );
    }

    /** Cambia la contraseña y consume el enlace. @return bool false si el enlace ya no es válido */
    public static function complete(string $token, string $password): bool
    {
        if (!self::isWellFormed($token)) {
            return false;
        }
        return (bool) Database::transaction(function () use ($token, $password) {
            $row = Database::fetch(
                "SELECT r.id, r.user_id, u.email
                   FROM password_resets r JOIN users u ON u.id = r.user_id
                  WHERE r.token_hash = ? AND r.status = 'sent' AND r.expires_at > NOW() AND u.is_active = 1
                    FOR UPDATE",
                [self::hash($token)]
            );
            if (!$row) {
                return false;
            }
            Database::query("UPDATE password_resets SET status = 'used', token_hash = NULL WHERE id = ?", [$row['id']]);
            Database::query('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $row['user_id']]);
            Auth::invalidateSessions((int) $row['user_id']);
            Database::query('DELETE FROM login_attempts WHERE email = ?', [$row['email']]);
            return true;
        });
    }

    public static function link(string $appUrl, string $token): string
    {
        return rtrim($appUrl, '/') . '/contrasena/restablecer?token=' . $token;
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function isWellFormed(string $token): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/', $token) === 1;
    }
}
