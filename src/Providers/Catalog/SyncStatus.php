<?php
declare(strict_types=1);

namespace App\Providers\Catalog;

use App\Core\Database;

/** Estado de sincronización del catálogo por cuenta (tabla provider_sync) */
final class SyncStatus
{
    /** Una sincronización "running" sin latido en este tiempo se considera abandonada */
    public const STALE_MINUTES = 10;

    public static function get(int $accountId): ?array
    {
        return Database::fetch('SELECT * FROM provider_sync WHERE provider_account_id = ?', [$accountId]);
    }

    /** Pide una sincronización; el programador la toma en menos de un minuto */
    public static function request(int $accountId): void
    {
        Database::query(
            "INSERT INTO provider_sync (provider_account_id, status, requested_at, message) VALUES (?, 'queued', NOW(), 'En cola')
             ON DUPLICATE KEY UPDATE requested_at = NOW(),
                status = IF(status = 'running' AND heartbeat_at > NOW() - INTERVAL " . self::STALE_MINUTES . " MINUTE, status, 'queued'),
                message = IF(status = 'running', message, 'En cola')",
            [$accountId]
        );
    }

    public static function start(int $accountId): void
    {
        Database::query(
            "INSERT INTO provider_sync (provider_account_id, status, started_at, heartbeat_at, page, last_page, items, message)
             VALUES (?, 'running', NOW(), NOW(), 0, 0, 0, 'Iniciando…')
             ON DUPLICATE KEY UPDATE status = 'running', started_at = NOW(), heartbeat_at = NOW(), finished_at = NULL,
                requested_at = NULL, page = 0, last_page = 0, items = 0, message = 'Iniciando…'",
            [$accountId]
        );
    }

    public static function progress(int $accountId, int $page, int $lastPage, int $items): void
    {
        Database::query(
            "UPDATE provider_sync SET heartbeat_at = NOW(), page = ?, last_page = ?, items = ?, message = ? WHERE provider_account_id = ?",
            [$page, $lastPage, $items, "Página {$page} de {$lastPage}", $accountId]
        );
    }

    public static function finish(int $accountId, bool $ok, string $message): void
    {
        Database::query(
            "UPDATE provider_sync SET status = ?, finished_at = NOW(), heartbeat_at = NOW(), message = ? WHERE provider_account_id = ?",
            [$ok ? 'done' : 'error', mb_substr($message, 0, 500), $accountId]
        );
    }

    /** ¿Toca sincronizar esta cuenta? */
    public static function isDue(?array $row, int $intervalHours): bool
    {
        if ($row === null) {
            return true;
        }
        $now = time();
        return match ($row['status']) {
            'queued' => true,
            'running' => !$row['heartbeat_at'] || strtotime($row['heartbeat_at']) < $now - self::STALE_MINUTES * 60,
            // Tras un error se reintenta en 30 min; si se pidió manualmente, de inmediato
            'error' => $row['requested_at'] !== null || strtotime((string) $row['finished_at']) < $now - 1800,
            default => $row['requested_at'] !== null || strtotime((string) $row['finished_at']) < $now - $intervalHours * 3600,
        };
    }
}
