<?php
declare(strict_types=1);
namespace Paragonie\Sapient\CryptographyKeys;

use Paragonie\Sapient\CryptographyKey;

/**
 * Class SharedEncryptionKey
 * @package Paragonie\Sapient
 */
class SharedEncryptionKey extends CryptographyKey
{
    /**
     * SharedEncryptionKey constructor.
     * @param string $key
     * @throws \RangeException
     */
    public function __construct(string $key)
    {
        if (\ParagonIE_Sodium_Core_Util::strlen($key) !== SODIUM_CRYPTO_AEAD_CHACHA20POLY1305_KEYBYTES) {
            throw new \RangeException('Key is not the correct size');
        }
        $this->key = $key;
    }

    /**
     * @return SharedEncryptionKey
     */
    public static function generate(): SharedEncryptionKey
    {
        return new SharedEncryptionKey(
            \random_bytes(SODIUM_CRYPTO_AEAD_CHACHA20POLY1305_KEYBYTES)
        );
    }
}
