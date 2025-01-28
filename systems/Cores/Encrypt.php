<?php

namespace Il4mb\Simvc\Systems\Cores;

use RuntimeException;

class Encrypt
{
    private const METHOD = 'AES-256-CBC';
    private const KEY = 'f8c3d55a6bc9f92446bdf58f20c4e299';
    private const IV = '27d3f6b25cb6b427';

    /**
     * Encrypt a string.
     *
     * @param string $data Data to be encrypted.
     * @return string The encrypted string (base64 encoded).
     */
    public static function encrypt(string $data): string
    {
        $encrypted = openssl_encrypt(
            $data,
            self::METHOD,
            self::KEY,
            0,
            self::IV
        );

        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return base64_encode($encrypted);
    }

    /**
     * Decrypt a string.
     *
     * @param string $encryptedData The encrypted string (base64 encoded).
     * @return string The decrypted string.
     */
    public static function decrypt(string $encryptedData): string| false
    {
        $decodedData = base64_decode($encryptedData, true);

        if ($decodedData === false) {
            return false;
        }

        $decrypted = openssl_decrypt(
            $decodedData,
            self::METHOD,
            self::KEY,
            0,
            self::IV
        );

        return $decrypted ?? false;
    }
}
