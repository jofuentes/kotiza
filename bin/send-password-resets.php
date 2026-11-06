<?php
declare(strict_types=1);

// Envía los correos de recuperación de contraseña pendientes.
// El contenedor lo ejecuta cada 15 segundos (ver docker/entrypoint.sh).

require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Core\Env;
use App\Domain\PasswordResets;
use App\Mail\MailSettings;

Env::load(dirname(__DIR__));
date_default_timezone_set(Env::get('TZ', 'America/Mexico_City'));
$log = fn (string $m) => fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . "] correo: {$m}\n");

try {
    // Evita enviar dos veces si una ejecución anterior sigue activa
    if ((int) Database::value('SELECT GET_LOCK(?, 0)', ['cotizador_password_resets']) !== 1) {
        exit(0);
    }
    if (!Database::value("SELECT 1 FROM password_resets WHERE status = 'pending' LIMIT 1")) {
        exit(0);
    }
    $mailer = null;
    try {
        $mailer = MailSettings::mailer();
    } catch (\Throwable $e) {
        $log('no se pudo leer la cuenta de correo: ' . $e->getMessage());
    }
    $sent = PasswordResets::sendPending($mailer, app_name(), (string) Env::get('APP_URL', ''), $log);
    if ($sent > 0) {
        $log("{$sent} correo(s) de recuperación enviados");
    }
} catch (\Throwable $e) {
    // La base de datos puede no estar lista o las migraciones pendientes
    $log('error: ' . $e->getMessage());
}
