<?php

declare(strict_types=1);

namespace CRM\Shared\Helpers;

/**
 * Crypto — criptografia AES-256-CBC para credenciais de gateway
 *
 * As api_keys ficam cifradas no banco.
 * A chave mestra vem de GATEWAY_ENCRYPTION_KEY no .env.
 */
class Crypto
{
    private const CIPHER = 'AES-256-CBC';

    public static function encrypt(string $value): string
    {
        $key  = self::key();
        $iv   = random_bytes(openssl_cipher_iv_length(self::CIPHER));
        $data = openssl_encrypt($value, self::CIPHER, $key, 0, $iv);

        return base64_encode($iv . $data);
    }

    public static function decrypt(string $encrypted): string
    {
        $key    = self::key();
        $raw    = base64_decode($encrypted);
        $ivLen  = openssl_cipher_iv_length(self::CIPHER);
        $iv     = substr($raw, 0, $ivLen);
        $data   = substr($raw, $ivLen);
        $result = openssl_decrypt($data, self::CIPHER, $key, 0, $iv);

        if ($result === false) {
            throw new \RuntimeException('Falha ao descriptografar credencial. Verifique GATEWAY_ENCRYPTION_KEY.');
        }

        return $result;
    }

    private static function key(): string
    {
        $key = $_ENV['GATEWAY_ENCRYPTION_KEY'] ?? '';

        if (strlen($key) < 32) {
            throw new \RuntimeException('GATEWAY_ENCRYPTION_KEY inválida. Mínimo 32 caracteres.');
        }

        return substr(hash('sha256', $key, true), 0, 32);
    }
}
