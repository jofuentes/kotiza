<?php
declare(strict_types=1);

namespace App\Providers\Http;

use App\Core\Database;

final class DbCallLogger implements CallLogger
{
    public function log(int $accountId, string $method, string $endpoint, ?int $status, int $ms, ?string $error): void
    {
        try {
            Database::insert('provider_logs', [
                'provider_account_id' => $accountId,
                'method' => $method,
                'endpoint' => mb_substr($endpoint, 0, 500),
                'http_code' => $status,
                'duration_ms' => $ms,
                'error' => $error !== null ? mb_substr($error, 0, 500) : null,
            ]);
            if (random_int(1, 200) === 1) {
                Database::query('DELETE FROM provider_logs WHERE created_at < NOW() - INTERVAL 30 DAY');
            }
        } catch (\Throwable $e) {
            error_log('No se pudo registrar la llamada al proveedor: ' . $e->getMessage());
        }
    }
}
