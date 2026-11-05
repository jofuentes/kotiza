<?php
declare(strict_types=1);

namespace Tests\Core;

use App\Core\Migrator;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase
{
    public function testSplitsStatementsAndIgnoresComments(): void
    {
        $sql = "-- comentario; con punto y coma\nCREATE TABLE a (x INT);\n\nINSERT INTO a VALUES (1);";
        $this->assertSame(['CREATE TABLE a (x INT)', 'INSERT INTO a VALUES (1)'], Migrator::splitStatements($sql));
    }

    public function testKeepsSemicolonsInsideStrings(): void
    {
        $sql = "INSERT INTO t (s) VALUES ('a;b'), ('c\\'d;');SELECT 1";
        $this->assertSame(["INSERT INTO t (s) VALUES ('a;b'), ('c\\'d;')", 'SELECT 1'], Migrator::splitStatements($sql));
    }

    public function testDoubleDashInsideStringIsNotAComment(): void
    {
        $this->assertSame(["SELECT '--no'"], Migrator::splitStatements("SELECT '--no';"));
    }

    public function testRealMigrationFilesParse(): void
    {
        foreach (glob(__DIR__ . '/../../database/migrations/*.sql') as $file) {
            $stmts = Migrator::splitStatements(file_get_contents($file));
            $this->assertNotEmpty($stmts, basename($file));
            foreach ($stmts as $s) {
                $this->assertMatchesRegularExpression('/^(CREATE|ALTER|INSERT|DROP|UPDATE)\b/i', $s, basename($file));
            }
        }
    }
}
