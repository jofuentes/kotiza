<?php
declare(strict_types=1);

namespace Tests\Core;

use App\Core\Env;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    public function testParsesTheShippedExampleFile(): void
    {
        $vars = Env::parse((string) file_get_contents(dirname(__DIR__, 2) . '/.env.example'));

        $this->assertSame('Kotiza', $vars['APP_NAME']);
        $this->assertSame('http://localhost:8080', $vars['APP_URL']);
        $this->assertSame('', $vars['APP_KEY']);
        $this->assertSame('cambia-esta-contraseña', $vars['DB_PASS']);
        $this->assertArrayNotHasKey('DNS_PRIMARY', $vars, 'Las líneas comentadas se ignoran');
    }

    public function testKeepsBase64PaddingAndEqualsSigns(): void
    {
        $this->assertSame(['APP_KEY' => 'abc+/d=='], Env::parse('APP_KEY=abc+/d=='));
    }

    public function testQuotedValuesAndEscapes(): void
    {
        $vars = Env::parse(<<<'ENV'
            A="con espacios # no es comentario"
            B='literal \n $x'
            C="comilla \" y barra \\ y\nsalto"
            D="" # comentario
            ENV);

        $this->assertSame('con espacios # no es comentario', $vars['A']);
        $this->assertSame('literal \n $x', $vars['B']);
        $this->assertSame("comilla \" y barra \\ y\nsalto", $vars['C']);
        $this->assertSame('', $vars['D']);
    }

    public function testUnquotedValuesOnlyTreatSpacedHashAsComment(): void
    {
        $vars = Env::parse("A=valor # comentario\nB=pass#word\nC= # solo comentario");

        $this->assertSame('valor', $vars['A']);
        $this->assertSame('pass#word', $vars['B']);
        $this->assertSame('', $vars['C']);
    }

    public function testExportPrefixWindowsLineEndingsAndInvalidLines(): void
    {
        $vars = Env::parse("export A=1\r\nB=2\r\n\r\n# C=3\r\nsin igual\r\n1X=4\r\n");

        $this->assertSame(['A' => '1', 'B' => '2'], $vars);
    }

    public function testLoadDoesNotOverrideRealEnvironment(): void
    {
        $dir = sys_get_temp_dir() . '/env-test-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/.env', "ENV_TEST_NEW=archivo\nENV_TEST_EXISTING=archivo\n");
        $_ENV['ENV_TEST_EXISTING'] = 'contenedor';
        $loaded = new \ReflectionProperty(Env::class, 'loaded');
        $previous = $loaded->getValue();
        $loaded->setValue(null, false);

        try {
            Env::load($dir);

            $this->assertSame('archivo', Env::get('ENV_TEST_NEW'));
            $this->assertSame('contenedor', Env::get('ENV_TEST_EXISTING'));
        } finally {
            $loaded->setValue(null, $previous);
            unset($_ENV['ENV_TEST_NEW'], $_SERVER['ENV_TEST_NEW'], $_ENV['ENV_TEST_EXISTING']);
            unlink($dir . '/.env');
            rmdir($dir);
        }
    }
}
