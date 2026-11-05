<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\Database;

/**
 * Folios de cotización por empresa: PREFIJO-AAAA-0001 (o PREFIJO-0001 sin año).
 * El consecutivo puede reiniciar cada año y la empresa puede fijar el siguiente número.
 */
final class FolioService
{
    public static function format(string $prefix, bool $includeYear, int $year, int $number, int $padding): string
    {
        $num = str_pad((string) $number, max(1, $padding), '0', STR_PAD_LEFT);
        $parts = array_filter([$prefix, $includeYear ? (string) $year : null, $num], fn ($p) => $p !== null && $p !== '');
        return implode('-', $parts);
    }

    /**
     * Número y año que corresponden al siguiente folio.
     * @return array{0:int,1:int} [número, año]
     */
    public static function resolve(array $company, int $currentYear): array
    {
        $number = max(1, (int) $company['folio_next']);
        $year = (int) ($company['folio_year'] ?: $currentYear);
        if ((int) $company['folio_reset_yearly'] === 1 && $year !== $currentYear) {
            return [1, $currentYear];
        }
        return [$number, $currentYear];
    }

    /** Vista previa del siguiente folio sin consumirlo */
    public static function preview(array $company, ?int $currentYear = null): string
    {
        $currentYear ??= (int) date('Y');
        [$number, $year] = self::resolve($company, $currentYear);
        return self::format(
            (string) $company['folio_prefix'], (bool) $company['folio_include_year'], $year, $number, (int) $company['folio_padding']
        );
    }

    /** Consume y devuelve el siguiente folio (bloquea la fila para evitar duplicados) */
    public static function next(int $companyId): string
    {
        return Database::transaction(function () use ($companyId) {
            $c = Database::fetch('SELECT * FROM companies WHERE id = ? FOR UPDATE', [$companyId])
                ?? throw new \RuntimeException('Empresa no encontrada.');
            [$number, $year] = self::resolve($c, (int) date('Y'));
            Database::query('UPDATE companies SET folio_next = ?, folio_year = ? WHERE id = ?', [$number + 1, $year, $companyId]);
            return self::format((string) $c['folio_prefix'], (bool) $c['folio_include_year'], $year, $number, (int) $c['folio_padding']);
        });
    }

    /** Normaliza el prefijo: solo letras, números y guiones, en mayúsculas */
    public static function normalizePrefix(string $prefix): string
    {
        return trim(mb_strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', $prefix)), '-');
    }
}
