<?php
declare(strict_types=1);

// Uso: php bin/migrate.php [--wait]
//   --wait  reintenta la conexión a la BD hasta 60 s (útil al arrancar el contenedor)

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Core\Migrator;

Env::load(dirname(__DIR__));
date_default_timezone_set(Env::get('TZ', 'America/Mexico_City'));

$wait = in_array('--wait', $argv, true);
$deadline = time() + ($wait ? 60 : 0);

while (true) {
    try {
        $pdo = Database::connect();
        break;
    } catch (\PDOException $e) {
        if (time() >= $deadline) {
            fwrite(STDERR, "No se pudo conectar a la base de datos: {$e->getMessage()}\n");
            exit(1);
        }
        echo "Esperando a la base de datos...\n";
        sleep(2);
    }
}

$applied = (new Migrator($pdo, dirname(__DIR__) . '/database/migrations'))
    ->run(fn (string $m) => print($m . "\n"));

echo $applied ? 'Migraciones aplicadas: ' . count($applied) . "\n" : "Base de datos al día.\n";
