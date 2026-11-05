<?php
declare(strict_types=1);

namespace App\Pdf;

/** Cantidad con letra en español (formato de facturas/cotizaciones en México) */
final class NumberToWords
{
    private const UNITS = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ',
        'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE',
        'VEINTIUNO', 'VEINTIDÓS', 'VEINTITRÉS', 'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];
    private const TENS = ['', '', '', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
    private const HUNDREDS = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
        'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

    /** Ej. 6825.88, MXN → "SEIS MIL OCHOCIENTOS VEINTICINCO PESOS 88/100 M.N." */
    public static function money(float $amount, string $currency = 'MXN'): string
    {
        $amount = round(abs($amount), 2);
        $int = (int) floor($amount);
        $cents = (int) round(($amount - $int) * 100);
        if ($cents === 100) {
            $int++;
            $cents = 0;
        }
        $words = $int === 0 ? 'CERO' : self::spell($int, true);
        // "UN MILLÓN DE PESOS", "DOS MILLONES DE PESOS"
        if ($int >= 1000000 && $int % 1000000 === 0) {
            $words .= ' DE';
        }
        [$one, $many, $suffix] = $currency === 'USD' ? ['DÓLAR', 'DÓLARES', 'USD'] : ['PESO', 'PESOS', 'M.N.'];
        return sprintf('%s %s %02d/100 %s', $words, $int === 1 ? $one : $many, $cents, $suffix);
    }

    /** @param bool $apocope "UN"/"VEINTIÚN" al final (antes de un sustantivo) */
    public static function spell(int $n, bool $apocope = false): string
    {
        if ($n < 0) {
            return 'MENOS ' . self::spell(-$n, $apocope);
        }
        if ($n === 0) {
            return 'CERO';
        }
        $parts = [];
        $millions = intdiv($n, 1000000);
        $rest = $n % 1000000;
        if ($millions > 0) {
            $parts[] = $millions === 1 ? 'UN MILLÓN' : self::upTo999999($millions, true) . ' MILLONES';
        }
        if ($rest > 0) {
            $parts[] = self::upTo999999($rest, $apocope);
        }
        return implode(' ', $parts);
    }

    private static function upTo999999(int $n, bool $apocope): string
    {
        $thousands = intdiv($n, 1000);
        $rest = $n % 1000;
        $parts = [];
        if ($thousands > 0) {
            $parts[] = $thousands === 1 ? 'MIL' : self::upTo999($thousands, true) . ' MIL';
        }
        if ($rest > 0) {
            $parts[] = self::upTo999($rest, $apocope);
        }
        return implode(' ', $parts);
    }

    private static function upTo999(int $n, bool $apocope): string
    {
        if ($n === 100) {
            return 'CIEN';
        }
        $h = intdiv($n, 100);
        $rest = $n % 100;
        $parts = [];
        if ($h > 0) {
            $parts[] = self::HUNDREDS[$h];
        }
        if ($rest > 0) {
            $parts[] = self::upTo99($rest, $apocope);
        }
        return implode(' ', $parts);
    }

    private static function upTo99(int $n, bool $apocope): string
    {
        if ($n < 30) {
            $w = self::UNITS[$n];
            if ($apocope && $n === 1) {
                return 'UN';
            }
            if ($apocope && $n === 21) {
                return 'VEINTIÚN';
            }
            return $w;
        }
        $t = intdiv($n, 10);
        $u = $n % 10;
        if ($u === 0) {
            return self::TENS[$t];
        }
        return self::TENS[$t] . ' Y ' . ($apocope && $u === 1 ? 'UN' : self::UNITS[$u]);
    }
}
