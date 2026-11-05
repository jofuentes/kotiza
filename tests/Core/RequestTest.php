<?php
declare(strict_types=1);

namespace Tests\Core;

use App\Core\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    private function req(array $server): Request
    {
        return new Request('GET', '/', [], [], [], $server);
    }

    public function testDirectConnectionIgnoresForwardedHeaders(): void
    {
        $r = $this->req(['REMOTE_ADDR' => '201.150.10.20', 'HTTP_X_FORWARDED_FOR' => '1.2.3.4']);
        $this->assertSame('201.150.10.20', $r->ip(), 'un cliente público no puede falsificar su IP');
    }

    public function testBehindPrivateProxyUsesRealIp(): void
    {
        $this->assertSame('189.203.5.7', $this->req(['REMOTE_ADDR' => '172.17.0.5', 'HTTP_X_REAL_IP' => '189.203.5.7'])->ip());
        $this->assertSame('189.203.5.7', $this->req(['REMOTE_ADDR' => '172.17.0.5', 'HTTP_X_FORWARDED_FOR' => '189.203.5.7, 172.17.0.1'])->ip());
        $this->assertSame('172.17.0.5', $this->req(['REMOTE_ADDR' => '172.17.0.5', 'HTTP_X_FORWARDED_FOR' => 'basura'])->ip());
    }

    public function testPrivateRanges(): void
    {
        foreach (['10.0.0.1', '172.16.100.254', '192.168.1.10', '127.0.0.1'] as $ip) {
            $this->assertTrue(Request::isPrivateIp($ip), $ip);
        }
        $this->assertFalse(Request::isPrivateIp('8.8.8.8'));
    }
}
