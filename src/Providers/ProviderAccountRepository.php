<?php
declare(strict_types=1);

namespace App\Providers;

use App\Core\Crypto;
use App\Core\Database;

/** Lectura/escritura de provider_accounts con credenciales cifradas */
final class ProviderAccountRepository
{
    public function __construct(private Crypto $crypto)
    {
    }

    public static function default(): self
    {
        return new self(Crypto::fromEnv());
    }

    public function row(int $companyId, string $code): ?array
    {
        return Database::fetch('SELECT * FROM provider_accounts WHERE company_id = ? AND provider_code = ?', [$companyId, $code]);
    }

    /** @return array<string, array> código => fila */
    public function rows(int $companyId): array
    {
        $out = [];
        foreach (Database::fetchAll('SELECT * FROM provider_accounts WHERE company_id = ?', [$companyId]) as $r) {
            $out[$r['provider_code']] = $r;
        }
        return $out;
    }

    public function find(int $companyId, string $code): ?ProviderAccount
    {
        $row = $this->row($companyId, $code);
        return $row ? $this->hydrate($row) : null;
    }

    /** @return ProviderAccount[] cuentas activas con conector instalado, por prioridad */
    public function active(int $companyId): array
    {
        $rows = Database::fetchAll(
            'SELECT * FROM provider_accounts WHERE company_id = ? AND is_active = 1 ORDER BY priority, provider_code',
            [$companyId]
        );
        return array_values(array_filter(
            array_map(fn ($r) => $this->hydrate($r), $rows),
            fn (ProviderAccount $a) => ProviderRegistry::get($a->providerCode) !== null
        ));
    }

    public function hydrate(array $row): ProviderAccount
    {
        $credentials = [];
        if (!empty($row['credentials_enc'])) {
            try {
                $credentials = $this->crypto->decryptArray($row['credentials_enc']);
            } catch (\RuntimeException) {
                $credentials = []; // APP_KEY cambió: hay que volver a capturarlas
            }
        }
        return new ProviderAccount(
            id: (int) $row['id'],
            companyId: (int) $row['company_id'],
            providerCode: (string) $row['provider_code'],
            credentials: $credentials,
            settings: json_decode((string) ($row['settings'] ?? '{}'), true) ?: [],
            isActive: (bool) $row['is_active'],
            priority: (int) $row['priority'],
            defaultMargin: $row['default_margin'] !== null ? (float) $row['default_margin'] : null,
        );
    }

    /** Nombres de las credenciales guardadas (sin sus valores) */
    public function storedCredentialKeys(?array $row): array
    {
        if (!$row || empty($row['credentials_enc'])) {
            return [];
        }
        try {
            return array_keys(array_filter($this->crypto->decryptArray($row['credentials_enc']), fn ($v) => $v !== '' && $v !== null));
        } catch (\RuntimeException) {
            return [];
        }
    }

    /**
     * Crea o actualiza la cuenta. Las credenciales nuevas se combinan con las guardadas:
     * un campo vacío conserva el valor anterior.
     */
    public function save(int $companyId, string $code, array $data, array $newCredentials): int
    {
        $row = $this->row($companyId, $code);
        $current = $row ? $this->hydrate($row)->credentials : [];
        $merged = $current;
        foreach ($newCredentials as $k => $v) {
            if (is_string($v) && trim($v) !== '') {
                $merged[$k] = trim($v);
            }
        }
        $fields = [
            'is_active' => (int) $data['is_active'],
            'priority' => (int) $data['priority'],
            'default_margin' => $data['default_margin'],
            'settings' => json_encode($data['settings'], JSON_UNESCAPED_UNICODE),
            'credentials_enc' => $merged ? $this->crypto->encryptArray($merged) : null,
        ];
        if ($row) {
            $sets = implode(', ', array_map(fn ($c) => "`{$c}` = :{$c}", array_keys($fields)));
            Database::query("UPDATE provider_accounts SET {$sets} WHERE id = :id", $fields + ['id' => $row['id']]);
            if ($merged !== $current) {
                // Credenciales distintas: el token anterior ya no sirve
                Database::query('DELETE FROM provider_tokens WHERE provider_account_id = ?', [$row['id']]);
            }
            return (int) $row['id'];
        }
        return Database::insert('provider_accounts', $fields + ['company_id' => $companyId, 'provider_code' => $code]);
    }

    public function recordTest(int $accountId, ConnectionResult $r): void
    {
        Database::query(
            'UPDATE provider_accounts SET last_test_at = NOW(), last_test_ok = ?, last_test_message = ? WHERE id = ?',
            [(int) $r->ok, mb_substr($r->message, 0, 500), $accountId]
        );
    }
}
