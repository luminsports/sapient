<?php
declare(strict_types=1);
namespace Paragonie\Sapient\CryptographyKeys;

use Paragonie\Sapient\CryptographyKey;

/**
 * Class SigningPublicKey
 * @package Paragonie\Sapient
 */
class SigningPublicKey extends CryptographyKey
{
    /**
     * SigningPublicKey constructor.
     * @param string $key
     * @throws \RangeException
     */
    public function __construct(string $key)
    {
        if (\ParagonIE_Sodium_Core_Util::strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new \RangeException('Key is not the correct size');
        }
        $this->key = $key;
    }
}
