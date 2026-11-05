<?php
declare(strict_types=1);

// Tareas en segundo plano. El contenedor lo ejecuta cada minuto (ver docker/entrypoint.sh).
// Por ahora: sincronización del catálogo de proveedores sin búsqueda por texto (TVC).
//
// Uso manual:  docker compose exec app php bin/scheduler.php [--force]

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Providers\Catalog\CatalogSynchronizer;
use App\Providers\Catalog\DbLocalCatalog;
use App\Providers\Catalog\SyncableProvider;
use App\Providers\Catalog\SyncStatus;
use App\Providers\ProviderAccountRepository;
use App\Providers\ProviderFactory;
use App\Providers\ProviderRegistry;

Env::load(dirname(__DIR__));
date_default_timezone_set(Env::get('TZ', 'America/Mexico_City'));
$force = in_array('--force', $argv, true);
$log = fn (string $m) => fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . "] scheduler: {$m}\n");

try {
    $repo = ProviderAccountRepository::default();
    $factory = ProviderFactory::default();
    $catalog = new DbLocalCatalog();
    $rows = Database::fetchAll('SELECT * FROM provider_accounts WHERE is_active = 1 ORDER BY id');
} catch (\Throwable $e) {
    $log('sin acceso a la base de datos: ' . $e->getMessage());
    exit(0);
}

// Cotizaciones aprobadas/enviadas cuya vigencia pasó → Vencida
try {
    if ($n = \App\Domain\Quotes\QuoteService::expireOverdue()) {
        $log("{$n} cotización(es) marcadas como vencidas");
    }
} catch (\Throwable $e) {
    $log('error al vencer cotizaciones: ' . $e->getMessage());
}

foreach ($rows as $row) {
    $class = ProviderRegistry::get($row['provider_code']);
    if (!$class || !is_subclass_of($class, SyncableProvider::class)) {
        continue;
    }
    $account = $repo->hydrate($row);
    if (!$account->credentials) {
        continue;
    }
    /** @var SyncableProvider&\App\Providers\ProviderInterface $provider */
    $provider = $factory->make($account);
    if (!$force && !SyncStatus::isDue(SyncStatus::get($account->id), $provider->syncIntervalHours())) {
        continue;
    }
    // Evita dos sincronizaciones simultáneas de la misma cuenta
    if ((int) Database::value('SELECT GET_LOCK(?, 0)', ['cotizador_sync_' . $account->id]) !== 1) {
        continue;
    }
    $name = $class::name();
    $log("sincronizando {$name} (cuenta {$account->id}, empresa {$account->companyId})");
    SyncStatus::start($account->id);
    $started = microtime(true);
    try {
        $sync = new CatalogSynchronizer($catalog, function (int $page, int $last, int $items) use ($account) {
            SyncStatus::progress($account->id, $page, $last, $items);
        });
        $r = $sync->run($provider, $account->id);
        $secs = (int) round(microtime(true) - $started);
        $msg = sprintf('%s productos sincronizados en %d s%s', number_format($r['items']), $secs,
            $r['removed'] ? ", {$r['removed']} retirados" : '');
        SyncStatus::finish($account->id, true, $msg);
        $log("{$name}: {$msg}");
    } catch (\Throwable $e) {
        SyncStatus::finish($account->id, false, $e->getMessage());
        $log("{$name}: error: " . $e->getMessage());
    } finally {
        Database::value('SELECT RELEASE_LOCK(?)', ['cotizador_sync_' . $account->id]);
    }
}
