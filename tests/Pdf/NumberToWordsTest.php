<?php
declare(strict_types=1);

namespace Tests\Pdf;

use App\Pdf\NumberToWords;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NumberToWordsTest extends TestCase
{
    #[DataProvider('numbers')]
    public function testSpell(int $n, string $expected): void
    {
        $this->assertSame($expected, NumberToWords::spell($n));
    }

    public static function numbers(): array
    {
        return [
            [0, 'CERO'], [1, 'UNO'], [15, 'QUINCE'], [16, 'DIECISÉIS'], [21, 'VEINTIUNO'], [22, 'VEINTIDÓS'],
            [30, 'TREINTA'], [31, 'TREINTA Y UNO'], [99, 'NOVENTA Y NUEVE'], [100, 'CIEN'], [101, 'CIENTO UNO'],
            [115, 'CIENTO QUINCE'], [200, 'DOSCIENTOS'], [500, 'QUINIENTOS'], [999, 'NOVECIENTOS NOVENTA Y NUEVE'],
            [1000, 'MIL'], [1001, 'MIL UNO'], [2021, 'DOS MIL VEINTIUNO'], [21000, 'VEINTIÚN MIL'],
            [31000, 'TREINTA Y UN MIL'], [100000, 'CIEN MIL'], [101000, 'CIENTO UN MIL'],
            [1000000, 'UN MILLÓN'], [1000001, 'UN MILLÓN UNO'], [2000000, 'DOS MILLONES'],
            [21500300, 'VEINTIÚN MILLONES QUINIENTOS MIL TRESCIENTOS'],
        ];
    }

    public function testMoney(): void
    {
        $this->assertSame('OCHO MIL DOSCIENTOS VEINTICINCO PESOS 88/100 M.N.', NumberToWords::money(8225.88));
        $this->assertSame('UN PESO 00/100 M.N.', NumberToWords::money(1));
        $this->assertSame('VEINTIÚN PESOS 50/100 M.N.', NumberToWords::money(21.5));
        $this->assertSame('CERO PESOS 05/100 M.N.', NumberToWords::money(0.05));
        $this->assertSame('UN MILLÓN DE PESOS 00/100 M.N.', NumberToWords::money(1000000));
        $this->assertSame('TREINTA Y UN DÓLARES 99/100 USD', NumberToWords::money(31.99, 'USD'));
        $this->assertSame('UN DÓLAR 00/100 USD', NumberToWords::money(1, 'USD'));
        $this->assertSame('MIL PESOS 00/100 M.N.', NumberToWords::money(999.999));
    }
}
