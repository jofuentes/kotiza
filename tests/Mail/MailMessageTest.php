<?php
declare(strict_types=1);

namespace Tests\Mail;

use App\Domain\PasswordResets;
use App\Mail\MailMessage;
use PHPUnit\Framework\TestCase;

final class MailMessageTest extends TestCase
{
    public function testPasswordResetIncludesLinkInBothVersions(): void
    {
        $link = 'https://kotiza.example/contrasena/restablecer?token=abc';
        $m = MailMessage::passwordReset('Kotiza', 'Ana', $link, 30);

        $this->assertSame('Restablece tu contraseña · Kotiza', $m->subject);
        $this->assertStringContainsString($link, $m->text);
        $this->assertStringContainsString('href="' . $link . '"', $m->html);
        $this->assertStringContainsString('30 minutos', $m->text);
    }

    public function testHtmlEscapesUserData(): void
    {
        $m = MailMessage::passwordReset('Kotiza', '<script>alert(1)</script>', 'https://x.example/?a=1&b="2"', 30);

        $this->assertStringNotContainsString('<script>', $m->html);
        $this->assertStringContainsString('&lt;script&gt;', $m->html);
        $this->assertStringContainsString('a=1&amp;b=&quot;2&quot;', $m->html);
    }

    public function testLinkUsesConfiguredUrlWithoutDoubleSlash(): void
    {
        $this->assertSame(
            'https://kotiza.example/app/contrasena/restablecer?token=abc',
            PasswordResets::link('https://kotiza.example/app/', 'abc')
        );
    }

    public function testTokenFormatAndHashing(): void
    {
        $token = str_repeat('a1', 32);

        $this->assertTrue(PasswordResets::isWellFormed($token));
        $this->assertFalse(PasswordResets::isWellFormed('corto'));
        $this->assertFalse(PasswordResets::isWellFormed(str_repeat('Z', 64)));
        $this->assertFalse(PasswordResets::isWellFormed($token . "\n"));
        $this->assertNotSame($token, PasswordResets::hash($token));
        $this->assertSame(64, strlen(PasswordResets::hash($token)));
    }
}
