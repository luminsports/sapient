<?php
declare(strict_types=1);
namespace LuminSports\Sapient\CryptographyKeys;

use LuminSports\Sapient\CryptographyKey;

/**
 * Class SharedAuthenticationKey
 * @package LuminSports\Sapient
 */
class SharedAuthenticationKey extends CryptographyKey
{
    /**
     * SharedAuthenticationKey constructor.
     * @param string $key
     * @throws \RangeException
     */
    public function __construct(string $key)
    {
        if (\ParagonIE_Sodium_Core_Util::strlen($key) !== SODIUM_CRYPTO_AUTH_KEYBYTES) {
            throw new \RangeException('Key is not the correct size');
        }
        $this->key = $key;
    }

    /**
     * @return SharedAuthenticationKey
     */
    public static function generate(): SharedAuthenticationKey
    {
        return new SharedAuthenticationKey(
            \random_bytes(SODIUM_CRYPTO_AUTH_KEYBYTES)
        );
    }
}
