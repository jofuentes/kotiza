<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/** Aplica en orden los archivos .sql de database/migrations que aún no se han ejecutado. */
final class Migrator
{
    public function __construct(private PDO $pdo, private string $dir)
    {
    }

    /** @return string[] migraciones aplicadas en esta ejecución */
    public function run(?callable $log = null): array
    {
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS migrations (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(190) NOT NULL UNIQUE,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $done = $this->pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        $files = glob(rtrim($this->dir, '/') . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        $applied = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $done, true)) {
                continue;
            }
            $log && $log("Aplicando {$name}...");
            foreach (self::splitStatements((string) file_get_contents($file)) as $sql) {
                $this->pdo->exec($sql);
            }
            $this->pdo->prepare('INSERT INTO migrations (migration) VALUES (?)')->execute([$name]);
            $applied[] = $name;
        }
        return $applied;
    }

    /**
     * Divide un script SQL en sentencias. Respeta cadenas entre comillas y elimina comentarios "--".
     * @return string[]
     */
    public static function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            if ($quote !== null) {
                $buffer .= $ch;
                if ($ch === '\\' && $i + 1 < $len) {
                    $buffer .= $sql[++$i];
                } elseif ($ch === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $quote = $ch;
                $buffer .= $ch;
                continue;
            }
            if ($ch === '-' && ($sql[$i + 1] ?? '') === '-') {
                $nl = strpos($sql, "\n", $i);
                $i = $nl === false ? $len : $nl;
                $buffer .= "\n";
                continue;
            }
            if ($ch === ';') {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';
                continue;
            }
            $buffer .= $ch;
        }
        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }
        return $statements;
    }
}
