<?php
declare(strict_types=1);

namespace Tests\Core;

use App\Core\Crypto;
use PHPUnit\Framework\TestCase;

final class CryptoTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $c = new Crypto(random_bytes(32));
        $payload = $c->encryptArray(['client_id' => 'abc', 'client_secret' => 'sécret']);
        $this->assertStringNotContainsString('sécret', $payload);
        $this->assertSame(['client_id' => 'abc', 'client_secret' => 'sécret'], $c->decryptArray($payload));
    }

    public function testSamePlaintextProducesDifferentCiphertext(): void
    {
        $c = new Crypto(random_bytes(32));
        $this->assertNotSame($c->encrypt('x'), $c->encrypt('x'));
    }

    public function testWrongKeyFails(): void
    {
        $payload = (new Crypto(random_bytes(32)))->encrypt('secreto');
        $this->expectException(\RuntimeException::class);
        (new Crypto(random_bytes(32)))->decrypt($payload);
    }

    public function testTamperedPayloadFails(): void
    {
        $c = new Crypto(random_bytes(32));
        $payload = $c->encrypt('secreto');
        $bin = base64_decode(substr($payload, 3));
        $bin[strlen($bin) - 1] = chr(ord($bin[strlen($bin) - 1]) ^ 1);
        $this->expectException(\RuntimeException::class);
        $c->decrypt('v1:' . base64_encode($bin));
    }

    public function testRejectsShortKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Crypto('corta');
    }
}
