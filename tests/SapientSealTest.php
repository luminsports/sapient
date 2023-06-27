<?php
namespace ParagonIE\Sapient\UnitTests;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Sapient\Adapter\Guzzle;
use ParagonIE\Sapient\CryptographyKeys\{
    SealingPublicKey,
    SealingSecretKey
};
use ParagonIE\Sapient\Sapient;
use PHPUnit\Framework\TestCase;

/**
 * Class SapientTest
 * @package ParagonIE\Sapient\UnitTests
 */
class SapientSealTest extends TestCase
{
    /** @var Sapient */
    protected $sapient;

    /** @var SealingSecretKey */
    protected $clientSealSecret;

    /** @var SealingPublicKey */
    protected $clientSealPublic;

    /** @var SealingSecretKey */
    protected $serverSealSecret;

    /** @var SealingPublicKey */
    protected $serverSealPublic;

    /**
     * Setup the class properties
     * @before
     */
    public function before()
    {
        $this->sapient = new Sapient(new Guzzle());

        $this->clientSealSecret = SealingSecretKey::generate();
        $this->clientSealPublic = $this->clientSealSecret->getPublickey();

        $this->serverSealSecret = SealingSecretKey::generate();
        $this->serverSealPublic = $this->serverSealSecret->getPublickey();
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
            ['structued' => [
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
     * @covers Sapient::createSignedJsonRequest()
     * @covers Sapient::verifySignedRequest()
     */
    public function testSignedJsonRequest()
    {
        $sampleObjects = $this->getSampleObjects();

        foreach ($sampleObjects as $obj) {
            $guzzle = new Guzzle();
            $request = $guzzle->createSealedJsonRequest(
                'POST',
                '/',
                $obj,
                $this->clientSealPublic
            );
            $decoded = $this->sapient->unsealJsonRequest(
                $request,
                $this->clientSealSecret
            );
            $this->assertSame($obj, $decoded);

            /* We expect an exception: */
            try {
                $this->sapient->unsealJsonRequest(
                    $request,
                    $this->serverSealSecret
                );
                $this->fail('Bad message signature');
            } catch (\Throwable $ex) {
            }

            $invalid = $request->withBody(
                $this->sapient->stringToStream(
                    Base64UrlSafe::encode('invalid message goes here for verifying the failure of crypto_box_seal')
                )
            );
            /* We expect an exception: */
            try {
                $this->sapient->unsealJsonRequest(
                    $invalid,
                    $this->clientSealSecret
                );
                $this->fail('Bad message accepted');
            } catch (\Throwable $ex) {
            }
        }
    }

    /**
     * @covers Sapient::createSignedRequest()
     * @covers Sapient::verifySignedRequest()
     */
    public function testSignedRequest()
    {
        $randomMessage = Base64UrlSafe::encode(
            \random_bytes(
                \random_int(101, 200)
            )
        );
        $request = $this->sapient->createSealedRequest(
            'POST',
            '/',
            $randomMessage,
            $this->clientSealPublic
        );
        $decoded = $this->sapient->unsealRequest(
            $request,
            $this->clientSealSecret
        );
        $this->assertInstanceOf(Request::class, $decoded);
        $this->assertSame($randomMessage, (string) $decoded->getBody());

        /* Test bad public key */
        try {
            $this->sapient->unsealRequest(
                $request,
                $this->serverSealSecret
            );
            $this->fail('Bad message signature');
        } catch (\Throwable $ex) {
        }

        $invalid = $request->withBody(
            $this->sapient->stringToStream(
                Base64UrlSafe::encode('invalid message goes here for verifying the failure of crypto_box_seal')
            )
        );

        /* Test bad message */
        try {
            $this->sapient->unsealRequest(
                $invalid,
                $this->serverSealSecret
            );
            $this->fail('Bad message accepted');
        } catch (\Throwable $ex) {
        }
    }
    /**
     * @covers Sapient::createSignedJsonResponse()
     * @covers Sapient::verifySignedResponse()
     */
    public function testSignedJsonResponse()
    {
        $sampleObjects = $this->getSampleObjects();

        foreach ($sampleObjects as $obj) {
            $guzzle = new Guzzle();
            $response = $guzzle->createSealedJsonResponse(
                200,
                $obj,
                $this->serverSealPublic
            );
            $responseRaw = $this->sapient->unsealResponse(
                $response,
                $this->serverSealSecret
            );
            $this->assertInstanceOf(Response::class, $responseRaw);

            $decoded = $this->sapient->unsealJsonResponse($response, $this->serverSealSecret);
            $this->assertSame($obj, $decoded);

            /* Test bad public key */
            try {
                $this->sapient->unsealResponse(
                    $response,
                    $this->clientSealSecret
                );
                $this->fail('Bad message accepted');
            } catch (\Throwable $ex) {
            }

            $invalid = $response->withBody(
                $this->sapient->stringToStream(
                    Base64UrlSafe::encode('invalid message goes here for verifying the failure of crypto_box_seal')
                )
            );
            /* Test bad message */
            try {
                $this->sapient->unsealResponse(
                    $invalid,
                    $this->serverSealSecret
                );
                $this->fail('Bad message accepted');
            } catch (\Throwable $ex) {
            }
        }
    }

    /**
     * @covers Sapient::createSignedResponse()
     * @covers Sapient::verifySignedResponse()
     */
    public function testSealedResponse()
    {
        $randomMessage = Base64UrlSafe::encode(
            \random_bytes(
                \random_int(101, 200)
            )
        );
        $response = $this->sapient->createSealedResponse(
            200,
            $randomMessage,
            $this->serverSealPublic
        );
        $responseRaw = $this->sapient->unsealResponse(
            $response,
            $this->serverSealSecret
        );
        $this->assertInstanceOf(Response::class, $responseRaw);

        $decoded = $this->sapient->unsealResponse($response, $this->serverSealSecret);
        $this->assertSame($randomMessage, (string) $decoded->getBody());

        /* Test bad public key */
        try {
            $this->sapient->unsealResponse(
                $response,
                $this->clientSealSecret
            );
            $this->fail('Bad message accepted');
        } catch (\Throwable $ex) {
        }

        $invalid = $response->withBody(
            $this->sapient->stringToStream(
                Base64UrlSafe::encode('invalid message goes here for verifying the failure of crypto_box_seal')
            )
        );
        /* Test bad message */
        try {
            $this->sapient->unsealResponse(
                $invalid,
                $this->serverSealSecret
            );
            $this->fail('Bad message accepted');
        } catch (\Throwable $ex) {
        }
    }

    /**
     * @covers Sapient::signRequest()
     * @covers Sapient::signResponse()
     */
    public function testPsr7()
    {
        $randomMessage = Base64UrlSafe::encode(
            \random_bytes(
                \random_int(101, 200)
            )
        );

        $request = new Request('POST', '/test', [], $randomMessage);
        $signedRequest = $this->sapient->sealRequest($request, $this->clientSealPublic);
        try {
            $unsealed = $this->sapient->unsealRequest(
                $signedRequest,
                $this->clientSealSecret
            );
            $this->assertSame(
                $randomMessage,
                (string) $unsealed->getBody()
            );
        } catch (\Throwable $ex) {
            $this->fail('Error decrypting message');
        }

        $response = new Response(200, [], $randomMessage);
        $signedResponse = $this->sapient->sealResponse($response, $this->clientSealPublic);
        try {
            $unsealed = $this->sapient->unsealResponse(
                $signedResponse,
                $this->clientSealSecret
            );
            $this->assertSame(
                $randomMessage,
                (string) $unsealed->getBody()
            );
        } catch (\Throwable $ex) {
            $this->fail('Error decrypting message');
        }
    }
}
