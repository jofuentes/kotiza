<?php
declare(strict_types=1);

namespace App\Providers\Catalog;

/**
 * Proveedor cuyo catálogo se descarga completo y se busca localmente
 * (su API no permite buscar por texto). La sincronización corre en segundo plano.
 */
interface SyncableProvider
{
    /** Una página del catálogo completo */
    public function fetchCatalogPage(int $page): CatalogPage;

    /** Pausa entre páginas (ms) para respetar el límite de peticiones del proveedor */
    public function syncDelayMs(): int;

    /** Horas entre sincronizaciones automáticas */
    public function syncIntervalHours(): int;
}
