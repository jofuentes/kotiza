<?php
declare(strict_types=1);

namespace Tests\Support;

use App\Support\Upload;
use App\Support\UploadException;
use PHPUnit\Framework\TestCase;

final class UploadTest extends TestCase
{
    public function testAcceptsPlainSvg(): void
    {
        Upload::assertSafeSvg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><use href="#a"/><rect width="10" height="10"/></svg>');
        $this->addToAssertionCount(1);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('maliciousSvgs')]
    public function testRejectsDangerousSvg(string $svg): void
    {
        $this->expectException(UploadException::class);
        Upload::assertSafeSvg($svg);
    }

    public static function maliciousSvgs(): array
    {
        return [
            'script' => ['<svg><script>alert(1)</script></svg>'],
            'onload' => ['<svg onload="alert(1)"></svg>'],
            'javascript href' => ['<svg><a href="javascript:alert(1)">x</a></svg>'],
            'external image' => ['<svg><image href="https://evil.example/x.png"/></svg>'],
            'foreignObject' => ['<svg><foreignObject><div/></foreignObject></svg>'],
            'xxe' => ['<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg>&x;</svg>'],
            'not svg' => ['<html></html>'],
        ];
    }

    public function testDetectsSvgReportedAsXml(): void
    {
        $f = tempnam(sys_get_temp_dir(), 'svg');
        file_put_contents($f, '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $this->assertSame('image/svg+xml', Upload::detectMime($f));
        unlink($f);
    }
}
