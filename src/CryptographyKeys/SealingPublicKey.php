<?php
declare(strict_types=1);
namespace Paragonie\Sapient\CryptographyKeys;

use Paragonie\Sapient\CryptographyKey;

/**
 * Class SealingPublicKey
 * @package Paragonie\Sapient
 */
class SealingPublicKey extends CryptographyKey
{
    /**
     * SealingPublicKey constructor.
     * @param string $key
     * @throws \RangeException
     */
    public function __construct(string $key)
    {
        if (\ParagonIE_Sodium_Core_Util::strlen($key) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) {
            throw new \RangeException('Key is not the correct size');
        }
        $this->key = $key;
    }
}
