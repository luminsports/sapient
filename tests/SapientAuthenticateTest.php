<?php
namespace Paragonie\Sapient\UnitTests;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Paragonie\Sapient\Adapter\Guzzle;
use Paragonie\Sapient\Exception\InvalidMessageException;
use Paragonie\Sapient\CryptographyKeys\{
    SharedAuthenticationKey
};
use Paragonie\Sapient\Sapient;
use PHPUnit\Framework\TestCase;

/**
 * Class SapientTest
 * @package Paragonie\Sapient\UnitTests
 */
class SapientAuthenticateTest extends TestCase
{
    /** @var Sapient */
    protected $sapient;

    /** @var SharedAuthenticationKey */
    protected $sharedAuthenticationKey;

    /**
     * Setup the class properties
     * @before
     */
    public function before()
    {
        $this->sapient = new Sapient(new Guzzle());

        $this->sharedAuthenticationKey = SharedAuthenticationKey::generate();
    }

    private function getSampleObjects(): array
    {
        return [
            [],
            ['test' => 'abcdefg'],
            ['random' => Base64UrlSafe::encode(
                \random_bytes(
                    \random_int(1, 100)
                )
            )
            ],
            ['structured' => [
                'abc' => 'def',
                'o' => null,
                'ghi' => ['j', 'k', 'l'],
                'm' => 1234,
                'n' => 56.78,
                'p' => ['q' => ['r' => []]]
            ]]
        ];
    }

    /**
     * @covers \Paragonie\Sapient\Adapter\Guzzle::createSymmetricAuthenticatedJsonRequest()
     * @covers \Paragonie\Sapient\Sapient::verifySymmetricAuthenticatedRequest()
     */
    public function testSignedJsonRequest()
    {
        foreach ($this->getSampleObjects() as $obj) {
            $guzzle = new Guzzle();
            $request = $guzzle->createSymmetricAuthenticatedJsonRequest(
                'POST',
                '/',
                $obj,
                $this->sharedAuthenticationKey
            );
            $decoded = $this->sapient->verifySymmetricAuthenticatedRequest(
                $request,
                $this->sharedAuthenticationKey
            );
            $body = json_decode((string)$decoded->getBody(), true);
            $this->assertSame($obj, $body);

            // Test the unhappy path
            $bad = $obj;
            $bad['bad'] = true;
            $badRequest = $guzzle->createSymmetricAuthenticatedJsonRequest(
                'POST',
                '/',
                $obj,
                $this->sharedAuthenticationKey
            )->withBody(
                $this->sapient->getAdapter()->stringToStream((string) json_encode($bad))
            );
            try {
                $this->sapient->verifySymmetricAuthenticatedRequest($badRequest, $this->sharedAuthenticationKey);
                $this->fail('Invalid message accepted');
            } catch (InvalidMessageException $ex) {
                // Expected
                $this->assertInstanceOf(InvalidMessageException::class, $ex);
            }
        }
    }

    /**
     * @covers Sapient::createSignedJsonRequest()
     * @covers Sapient::verifySignedRequest()
     */
    public function testSignedJsonResponse()
    {
        foreach ($this->getSampleObjects() as $obj) {
            $guzzle = new Guzzle();
            $response = $guzzle->createSymmetricAuthenticatedJsonResponse(
                200,
                $obj,
                $this->sharedAuthenticationKey
            );
            $decoded = $this->sapient->verifySymmetricAuthenticatedResponse(
                $response,
                $this->sharedAuthenticationKey
            );
            $body = json_decode((string)$decoded->getBody(), true);
            $this->assertSame($obj, $body);

            // Test the unhappy path
            $bad = $obj;
            $bad['bad'] = true;
            $badResponse = $guzzle->createSymmetricAuthenticatedJsonResponse(
                200,
                $obj,
                $this->sharedAuthenticationKey
            )->withBody(
                $this->sapient->getAdapter()->stringToStream((string) json_encode($bad))
            );
            try {
                $this->sapient->verifySymmetricAuthenticatedResponse($badResponse, $this->sharedAuthenticationKey);
                $this->fail('Invalid message accepted');
            } catch (InvalidMessageException $ex) {
                // Expected
                $this->assertInstanceOf(InvalidMessageException::class, $ex);
            }
        }
    }
}
