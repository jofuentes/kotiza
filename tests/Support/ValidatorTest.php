<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Support\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testRequiredAndEmail(): void
    {
        $v = Validator::make(['email' => 'no-es-correo', 'name' => ''], ['email' => 'required|email', 'name' => 'required'], ['name' => 'Nombre']);
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('email', $v->errors());
        $this->assertSame('El campo «Nombre» es obligatorio.', $v->errors()['name']);
    }

    public function testOptionalFieldsSkipRulesWhenEmpty(): void
    {
        $this->assertFalse(Validator::make(['rfc' => ''], ['rfc' => 'rfc'])->fails());
    }

    public function testRfc(): void
    {
        $this->assertFalse(Validator::make(['r' => 'XAXX010101000'], ['r' => 'rfc'])->fails());
        $this->assertFalse(Validator::make(['r' => 'abc010101a1b'], ['r' => 'rfc'])->fails());
        $this->assertTrue(Validator::make(['r' => 'XAXX01'], ['r' => 'rfc'])->fails());
    }

    public function testSameBetweenAndInteger(): void
    {
        $v = Validator::make(
            ['p' => 'secreto123', 'c' => 'otro', 'iva' => '120', 'n' => '1.5'],
            ['c' => 'same:p', 'iva' => 'numeric|between:0,100', 'n' => 'integer']
        );
        $this->assertSame(['c', 'iva', 'n'], array_keys($v->errors()));
    }
}
