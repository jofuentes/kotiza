<?php
declare(strict_types=1);

namespace App\Providers\Http;

use App\Core\Crypto;
use App\Core\Database;

/** Tokens cifrados en la tabla provider_tokens */
final class DbTokenStore implements TokenStore
{
    public function __construct(private Crypto $crypto)
    {
    }

    public function get(int $accountId): ?array
    {
        $row = Database::fetch('SELECT token_enc, expires_at FROM provider_tokens WHERE provider_account_id = ?', [$accountId]);
        if (!$row) {
            return null;
        }
        try {
            return ['token' => $this->crypto->decrypt($row['token_enc']), 'expires_at' => strtotime($row['expires_at'])];
        } catch (\RuntimeException) {
            $this->forget($accountId);
            return null;
        }
    }

    public function put(int $accountId, string $token, int $expiresAt): void
    {
        Database::query(
            'INSERT INTO provider_tokens (provider_account_id, token_enc, expires_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE token_enc = VALUES(token_enc), expires_at = VALUES(expires_at), created_at = NOW()',
            [$accountId, $this->crypto->encrypt($token), date('Y-m-d H:i:s', $expiresAt)]
        );
    }

    public function forget(int $accountId): void
    {
        Database::query('DELETE FROM provider_tokens WHERE provider_account_id = ?', [$accountId]);
    }
}
