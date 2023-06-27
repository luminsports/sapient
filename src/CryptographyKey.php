<?php
declare(strict_types=1);
namespace LuminSports\Sapient;

use ParagonIE\ConstantTime\Base64UrlSafe;

/**
 * Class CryptographyKey
 * @package LuminSports\Sapient
 */
class CryptographyKey
{
    /**
     * @var string
     */
    protected $key = '';

    public function __debugInfo()
    {
        return [
            'key' => '****',
            'hint' => 'Use $key->getString(), do not just var_dump($key)'
        ];
    }

    /**
     * @param bool $raw
     * @return string
     */
    public function getString(bool $raw = false): string
    {
        if ($raw) {
            return $this->key;
        }
        return Base64UrlSafe::encode($this->key);
    }
}
