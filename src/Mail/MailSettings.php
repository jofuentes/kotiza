<?php
declare(strict_types=1);

namespace App\Mail;

use App\Core\Crypto;
use App\Core\Database;
use App\Core\Env;

/** Cuenta de correo de la instalación. La contraseña se guarda cifrada con APP_KEY. */
final class MailSettings
{
    private const KEY = 'mail';

    /** @return ?array{account:string, sender:string} datos que se pueden mostrar */
    public static function current(): ?array
    {
        $raw = Database::value('SELECT setting_value FROM app_settings WHERE setting_key = ?', [self::KEY]);
        if (!$raw) {
            return null;
        }
        $data = json_decode((string) $raw, true);
        return is_array($data) ? ['account' => (string) $data['account'], 'sender' => (string) $data['sender']] : null;
    }

    public static function available(): bool
    {
        return self::current() !== null;
    }

    public static function save(string $account, string $sender, string $password): void
    {
        $value = json_encode([
            'account' => $account,
            'sender' => $sender,
            'password' => Crypto::fromEnv()->encrypt($password),
        ], JSON_THROW_ON_ERROR);
        Database::query(
            'INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            [self::KEY, $value]
        );
    }

    public static function delete(): void
    {
        Database::query('DELETE FROM app_settings WHERE setting_key = ?', [self::KEY]);
    }

    /** Contraseña guardada, para conservarla al cambiar solo la cuenta o el remitente */
    public static function storedPassword(): ?string
    {
        $raw = Database::value('SELECT setting_value FROM app_settings WHERE setting_key = ?', [self::KEY]);
        $data = $raw ? json_decode((string) $raw, true) : null;
        return is_array($data) ? Crypto::fromEnv()->decrypt((string) $data['password']) : null;
    }

    public static function mailerFor(string $account, string $sender, string $password): Mailer
    {
        return new SmtpMailer(
            $account,
            $password,
            $sender,
            (string) Env::get('MAIL_HOST', 'smtp.gmail.com'),
            (int) Env::get('MAIL_PORT', '465'),
            (string) Env::get('MAIL_ENCRYPTION', 'ssl'),
        );
    }

    /** Servicio de envío con la cuenta guardada, o null si no hay ninguna */
    public static function mailer(): ?Mailer
    {
        $current = self::current();
        return $current === null ? null : self::mailerFor($current['account'], $current['sender'], (string) self::storedPassword());
    }
}
