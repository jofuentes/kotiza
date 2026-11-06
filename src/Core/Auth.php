<?php
declare(strict_types=1);

namespace App\Core;

/** Usuario autenticado y empresa activa en la sesión. */
final class Auth
{
    private static ?array $user = null;
    private static ?array $membership = null;

    public static function login(array $user): void
    {
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::set('session_version', (int) ($user['session_version'] ?? 0));
        Session::forget('company_id');
        self::$user = null;
        self::$membership = null;
        Database::query('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
    }

    public static function logout(): void
    {
        self::$user = null;
        self::$membership = null;
        Session::destroy();
    }

    public static function id(): ?int
    {
        $id = Session::get('user_id');
        return $id ? (int) $id : null;
    }

    public static function user(): ?array
    {
        if (self::$user === null && self::id()) {
            $user = Database::fetch(
                'SELECT id, name, email, is_active, last_login_at, session_version FROM users WHERE id = ? AND is_active = 1',
                [self::id()]
            );
            // Una sesión abierta antes de cambiar la contraseña deja de ser válida
            if ($user && (int) $user['session_version'] === (int) Session::get('session_version', 0)) {
                self::$user = $user;
            }
        }
        return self::$user;
    }

    /** Cierra las demás sesiones del usuario tras cambiar su contraseña; conserva la actual si es la suya */
    public static function invalidateSessions(int $userId): void
    {
        Database::query('UPDATE users SET session_version = session_version + 1 WHERE id = ?', [$userId]);
        if (self::id() === $userId) {
            Session::set('session_version', (int) Database::value('SELECT session_version FROM users WHERE id = ?', [$userId]));
        }
        self::$user = null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** Empresas a las que pertenece el usuario */
    public static function companies(): array
    {
        if (!self::id()) {
            return [];
        }
        return Database::fetchAll(
            'SELECT c.id, c.trade_name, c.legal_name, c.logo_path, cu.role
               FROM company_user cu JOIN companies c ON c.id = cu.company_id
              WHERE cu.user_id = ? AND c.is_active = 1 AND cu.is_active = 1
           ORDER BY c.trade_name',
            [self::id()]
        );
    }

    public static function selectCompany(int $companyId): bool
    {
        $m = Database::fetch(
            'SELECT cu.* FROM company_user cu JOIN companies c ON c.id = cu.company_id
              WHERE cu.user_id = ? AND cu.company_id = ? AND c.is_active = 1 AND cu.is_active = 1',
            [self::id(), $companyId]
        );
        if (!$m) {
            return false;
        }
        Session::set('company_id', $companyId);
        self::$membership = null;
        return true;
    }

    public static function companyId(): ?int
    {
        $id = Session::get('company_id');
        return $id ? (int) $id : null;
    }

    /** Fila de la empresa activa */
    public static function company(): ?array
    {
        return self::companyId()
            ? Database::fetch('SELECT * FROM companies WHERE id = ?', [self::companyId()])
            : null;
    }

    /** Pertenencia (rol, descuento máx., firma) en la empresa activa */
    public static function membership(): ?array
    {
        if (self::$membership === null && self::id() && self::companyId()) {
            self::$membership = Database::fetch(
                'SELECT * FROM company_user WHERE user_id = ? AND company_id = ? AND is_active = 1',
                [self::id(), self::companyId()]
            );
        }
        return self::$membership;
    }

    /** Olvida la caché de la petición (tras editar el propio usuario o la membresía) */
    public static function refresh(): void
    {
        self::$user = null;
        self::$membership = null;
    }

    public static function isAdmin(): bool
    {
        return (self::membership()['role'] ?? null) === 'admin';
    }

    /**
     * Administrador de la primera empresa. Solo estos usuarios configuran lo que afecta a toda la
     * instalación: quien controla el correo podría interceptar enlaces de usuarios de otras empresas.
     */
    public static function isInstallationAdmin(): bool
    {
        return self::id() !== null && (bool) Database::value(
            "SELECT 1 FROM company_user
              WHERE user_id = ? AND role = 'admin' AND is_active = 1
                AND company_id = (SELECT MIN(id) FROM companies WHERE is_active = 1)",
            [self::id()]
        );
    }
}
