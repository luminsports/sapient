<?php
declare(strict_types=1);
namespace Paragonie\Sapient\Adapter\Generic;

use Paragonie\Sapient\Adapter\AdapterInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Class Guzzle
 * @package Paragonie\Sapient\Adapter
 */
class Adapter implements AdapterInterface
{
    /**
     * Adapter-specific way of converting a string into a StreamInterface
     *
     * @param string $input
     * @return StreamInterface
     * @throws \Error
     */
    public function stringToStream(string $input): StreamInterface
    {
        return Stream::fromString($input);
    }
}
