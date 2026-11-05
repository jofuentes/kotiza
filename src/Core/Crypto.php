<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Cifrado simétrico autenticado (libsodium secretbox) con la APP_KEY del .env.
 * Se usa para credenciales de proveedores y tokens.
 */
final class Crypto
{
    private const PREFIX = 'v1:';

    public function __construct(private string $key)
    {
        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \InvalidArgumentException('La APP_KEY debe ser de 32 bytes (base64).');
        }
    }

    public static function fromEnv(): self
    {
        $raw = Env::require('APP_KEY');
        if (str_starts_with($raw, 'base64:')) {
            $raw = substr($raw, 7);
        }
        $key = base64_decode($raw, true);
        if ($key === false) {
            throw new \RuntimeException('APP_KEY no es base64 válido. Genérala con: openssl rand -base64 32');
        }
        return new self($key);
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return self::PREFIX . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    public function decrypt(string $payload): string
    {
        if (!str_starts_with($payload, self::PREFIX)) {
            throw new \RuntimeException('Formato de dato cifrado desconocido.');
        }
        $bin = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        if ($bin === false || strlen($bin) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Dato cifrado corrupto.');
        }
        $nonce = substr($bin, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($bin, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key);
        if ($plain === false) {
            throw new \RuntimeException('No se pudo descifrar (¿cambió la APP_KEY?).');
        }
        return $plain;
    }

    public function encryptArray(array $data): string
    {
        return $this->encrypt(json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function decryptArray(string $payload): array
    {
        return json_decode($this->decrypt($payload), true, 512, JSON_THROW_ON_ERROR);
    }
}
