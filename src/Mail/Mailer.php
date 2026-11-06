<?php
declare(strict_types=1);

namespace App\Mail;

/** Envío de correos del sistema. */
interface Mailer
{
    /** Comprueba la conexión y las credenciales. @throws \RuntimeException si fallan */
    public function verify(): void;

    /** @throws \RuntimeException si no se pudo enviar */
    public function send(string $toEmail, string $toName, MailMessage $message): void;
}
