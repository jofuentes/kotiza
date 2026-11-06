<?php
declare(strict_types=1);

// Restablece la contraseña de un usuario y desbloquea sus intentos de acceso.
// Uso (dentro del contenedor):
//   docker compose exec app php bin/reset-password.php correo@empresa.com
// Pide la contraseña nueva de forma interactiva (no queda en el historial).

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;

Env::load(dirname(__DIR__));

$email = mb_strtolower(trim($argv[1] ?? ''));
if ($email === '') {
    fwrite(STDERR, "Uso: php bin/reset-password.php correo@empresa.com\n");
    exit(1);
}

$user = Database::fetch('SELECT id, name FROM users WHERE email = ?', [$email]);
if (!$user) {
    fwrite(STDERR, "No existe un usuario con el correo {$email}.\n");
    exit(1);
}

$read = function (string $prompt): string {
    echo $prompt;
    if (stream_isatty(STDIN)) {
        shell_exec('stty -echo');
    }
    $value = rtrim((string) fgets(STDIN), "\r\n");
    if (stream_isatty(STDIN)) {
        shell_exec('stty echo');
        echo "\n";
    }
    return $value;
};

$password = $read('Contraseña nueva (mínimo 10 caracteres): ');
if (mb_strlen($password) < 10) {
    fwrite(STDERR, "La contraseña debe tener al menos 10 caracteres.\n");
    exit(1);
}
if (stream_isatty(STDIN) && $read('Repite la contraseña: ') !== $password) {
    fwrite(STDERR, "Las contraseñas no coinciden.\n");
    exit(1);
}

Database::query('UPDATE users SET password_hash = ?, is_active = 1 WHERE id = ?', [
    password_hash($password, PASSWORD_DEFAULT), $user['id'],
]);
Database::query('UPDATE users SET session_version = session_version + 1 WHERE id = ?', [$user['id']]);
Database::query('DELETE FROM login_attempts WHERE email = ?', [$email]);

echo "Contraseña actualizada para {$user['name']} <{$email}>.\n";
