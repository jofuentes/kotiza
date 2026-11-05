<?php
declare(strict_types=1);

namespace App\Pricing;

use App\Core\Database;
use App\Providers\ProviderAccountRepository;
use App\Providers\ProviderFactory;
use App\Providers\ProviderRegistry;

/**
 * Tipo de cambio (MXN por USD) para una empresa según su configuración:
 *   provider        → el del proveedor activo de mayor prioridad que lo ofrezca
 *   provider_buffer → el del proveedor + colchón
 *   manual          → el capturado por la empresa
 * El del proveedor se guarda en exchange_rates y se reutiliza 1 hora.
 */
final class ExchangeRateService
{
    private const CACHE_MINUTES = 60;

    /** @return array{rate: ?float, source: string} */
    public static function current(array $company): array
    {
        $mode = (string) ($company['exchange_mode'] ?? 'provider');
        $manual = $company['exchange_manual'] !== null ? (float) $company['exchange_manual'] : null;
        $buffer = (float) ($company['exchange_buffer'] ?? 0);

        if ($mode === 'manual' && $manual) {
            return ['rate' => $manual, 'source' => 'Manual'];
        }
        $provider = self::providerRate((int) $company['id']);
        if ($provider === null) {
            return $manual ? ['rate' => $manual, 'source' => 'Manual (el proveedor no respondió)'] : ['rate' => null, 'source' => 'Sin tipo de cambio'];
        }
        return self::applyBuffer($provider['rate'], $provider['source'], $mode === 'provider_buffer' ? $buffer : 0.0);
    }

    /** @return array{rate: float, source: string} */
    public static function applyBuffer(float $rate, string $source, float $buffer): array
    {
        if ($buffer == 0.0) {
            return ['rate' => round($rate, 4), 'source' => $source];
        }
        return ['rate' => round($rate + $buffer, 4), 'source' => sprintf('%s %s %s', $source, $buffer > 0 ? '+' : '−', number_format(abs($buffer), 2))];
    }

    /** @return ?array{rate: float, source: string} */
    private static function providerRate(int $companyId): ?array
    {
        foreach (ProviderAccountRepository::default()->active($companyId) as $acc) {
            $class = ProviderRegistry::get($acc->providerCode);
            $source = $class::name();
            $cached = Database::fetch(
                'SELECT rate, fetched_at FROM exchange_rates WHERE source = ? AND fetched_at >= NOW() - INTERVAL ' . self::CACHE_MINUTES . ' MINUTE
                 ORDER BY fetched_at DESC LIMIT 1',
                [$acc->providerCode . ':' . $acc->id]
            );
            if ($cached) {
                return ['rate' => (float) $cached['rate'], 'source' => $source];
            }
            try {
                $rate = ProviderFactory::default()->make($acc)->getExchangeRate();
            } catch (\Throwable) {
                $rate = null;
            }
            if ($rate) {
                Database::insert('exchange_rates', ['source' => $acc->providerCode . ':' . $acc->id, 'rate' => $rate]);
                return ['rate' => $rate, 'source' => $source];
            }
            // Último conocido, aunque sea viejo
            $old = Database::fetch('SELECT rate, fetched_at FROM exchange_rates WHERE source = ? ORDER BY fetched_at DESC LIMIT 1',
                [$acc->providerCode . ':' . $acc->id]);
            if ($old) {
                return ['rate' => (float) $old['rate'], 'source' => $source . ' (del ' . date('d/m H:i', strtotime($old['fetched_at'])) . ')'];
            }
        }
        return null;
    }
}
