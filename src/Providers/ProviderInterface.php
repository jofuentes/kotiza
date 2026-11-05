<?php
declare(strict_types=1);

namespace App\Providers;

use GuzzleHttp\Promise\PromiseInterface;

/**
 * Contrato que implementa cada mayorista. Para agregar uno nuevo basta con crear
 * src/Providers/{Nombre}/{Nombre}Provider.php que implemente esta interfaz
 * (normalmente extendiendo AbstractProvider); el ProviderRegistry lo descubre solo.
 *
 * Filtros admitidos en search(): in_stock (bool), brand (string), category (string),
 * page (int), limit (int).
 */
interface ProviderInterface
{
    /** Identificador corto y estable (ej. "syscom") */
    public static function code(): string;

    public static function name(): string;

    public static function description(): string;

    /** @return FieldDefinition[] credenciales que se guardan cifradas */
    public static function credentialFields(): array;

    /** @return FieldDefinition[] ajustes no secretos del conector */
    public static function settingFields(): array;

    public function testConnection(): ConnectionResult;

    /** @return PromiseInterface que resuelve a SearchResult (permite consultar proveedores en paralelo) */
    public function searchAsync(string $query, array $filters = []): PromiseInterface;

    public function search(string $query, array $filters = []): SearchResult;

    public function getProduct(string $id): ?Product;

    /** @return array<string, Product> por id de proveedor (precios y existencias al momento) */
    public function getPrices(array $ids): array;

    /** @return ?array{total:int, warehouses:array<string,int>} */
    public function getStock(string $id): ?array;

    /** Tipo de cambio USD→MXN del proveedor, si lo ofrece */
    public function getExchangeRate(): ?float;

    /** @return array<int, array{id:string, name:string, level:int}> */
    public function getCategories(): array;

    /** @return array<int, array{id:string, name:string}> */
    public function getBrands(): array;
}
