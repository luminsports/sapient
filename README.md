# This is a fork of Paragonie Sapient

Dependencies have been updated, but we are not intending to maintain this package any further.

# Sapient: Secure API toolkit

[![Build Status](https://github.com/paragonie/sapient/actions/workflows/ci.yml/badge.svg)](https://github.com/paragonie/sapient/actions)
[![Latest Stable Version](https://poser.pugx.org/paragonie/sapient/v/stable)](https://packagist.org/packages/paragonie/sapient)
[![Latest Unstable Version](https://poser.pugx.org/paragonie/sapient/v/unstable)](https://packagist.org/packages/paragonie/sapient)
[![License](https://poser.pugx.org/paragonie/sapient/license)](https://packagist.org/packages/paragonie/sapient)

**Sapient** secures your PHP applications' server-to-server HTTP(S) traffic even in the wake of a
TLS security breakdown (compromised certificate authority, etc.).

Sapient allows you to quickly and easily add application-layer cryptography to your API requests
and responses. **Requires PHP 7 or newer.**

Sapient was designed and implemented by [the PHP security and cryptography team at Paragon Initiative Enterprises](https://paragonie.com).

> See [our blog post about using Sapient to harden your PHP-powered APIs](https://paragonie.com/blog/2017/06/hardening-your-php-powered-apis-with-sapient)
> for more information about its design rationale and motivation.

The cryptography is provided by [sodium_compat](https://github.com/paragonie/sodium_compat) (which,
in turn, will use the libsodium extension in PECL if it's installed).

Because sodium_compat operates on strings rather than resources (a.k.a. streams), Sapient is not
suitable for extremely large messages on systems with very low available memory.
Sapient [only encrypts or authenticates message bodies](https://github.com/paragonie/sapient/blob/master/docs/Internals/Sapient.md#important);
if you need headers to be encrypted or authenticated, that's the job of Transport-Layer Security (TLS).

## Features at a Glance

* Works with both `Request` and `Response` objects (PSR-7)
  * Includes a Guzzle adapter for HTTP clients
* Secure APIs:
  * Shared-key encryption
    * XChaCha20-Poly1305
  * Shared-key authentication
    * HMAC-SHA512-256
  * Anonymous public-key encryption
    * X25519 + BLAKE2b + XChaCha20-Poly1305
  * Public-key digital signatures
    * Ed25519
* Works with arrays
  * i.e. the methods with "Json" in the name
  * Sends/receives signed or encrypted JSON
* Works with strings
  * i.e. the methods without "Json" in the name
* Digital signatures and authentication are backwards-compatible
  with unsigned JSON API clients and servers
  * The signaure and authentication tag will go into HTTP headers,
    rather than the request/response body.

Additionally, Sapient is covered by both **unit tests** (provided by [PHPUnit](https://github.com/sebastianbergmann/phpunit)) and
**automated static analysis** (provided by [Psalm](https://github.com/vimeo/psalm)).

## Sapient Adapters

If you're looking to integrate Sapient into an existing framework:

* **Guzzle**
  * Adapter is included, but Guzzle itself is not a dependency. 
  * Add the suggested package if you want to use Guzzle (e.g. `composer require guzzlehttp/guzzle:^6`)
* [Laravel Sapient Adapter](https://github.com/mcordingley/LaravelSapient)
  * `composer require mcordingley/laravel-sapient`
* [Slim Framework Sapient Adapter](https://github.com/paragonie/slim-sapient)
  * `composer require paragonie/slim-sapient`
* [Zend Framework Diactoros Sapient Adapter](https://github.com/paragonie/zend-diactoros-sapient)
  * `composer require paragonie/zend-diactoros-sapient`
* [Symfony bundle](https://github.com/lepiaf/sapient-bundle)
  * `composer require lepiaf/sapient-bundle`

If your framework correctly implements PSR-7, you most likely do not need an adapter. However,
some adapters provide convenience methods that make rapid development easier.

To learn more about adapters, see [the documentation for `AdapterInterface`](docs/Internals/Adapter/AdapterInterface.md).

## Sapient in Other Languages

* [sapient.js](https://github.com/paragonie/sapient-js) (JavaScript, Node.js)

## Example 1: Signed PSR-7 Responses

This demonstrats a minimal implementation that adds Ed25519 signatures to your
existing PSR-7 HTTP responses.

### Server-Side: Signing an HTTP Response

```php
<?php
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Sapient\Sapient;
use ParagonIE\Sapient\CryptographyKeys\SigningSecretKey;
use Psr\Http\Message\ResponseInterface;

/**
 * @var ResponseInterface $response
 *
 * Let's assume we have a valid ResponseInterface object already.
 * (Most likely, after doing normal framework things.)  
 */

$sapient = new Sapient();
$serverSignSecret = new SigningSecretKey(
    Base64UrlSafe::decode(
        'q6KSHArUnD0sEa-KWpBCYLka805gdA6lVG2mbeM9kq82_Cwg1n7XLQXXXHF538URRov8xV7CF2AX20xh_moQTA=='
    )
);

$signedResponse = $sapient->signResponse($response, $serverSignSecret);
```

### Client-Side: Verifying the Signature

```php
<?php
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Sapient\Sapient;
use ParagonIE\Sapient\CryptographyKeys\SigningPublicKey;
use ParagonIE\Sapient\Exception\{
    HeaderMissingException,
    InvalidMessageException
};
use Psr\Http\Message\ResponseInterface;

/**
 * @var ResponseInterface $response
 *
 * Let's assume we have a valid ResponseInterface object already.
 * (Most likely the result of an HTTP request to the server.)  
 */

$sapient = new Sapient();
$serverPublicKey = new SigningPublicKey(
    Base64UrlSafe::decode(
        'NvwsINZ-1y0F11xxed_FEUaL_MVewhdgF9tMYf5qEEw='
    )
);

try {
    $verified = $sapient->verifySignedResponse($response, $serverPublicKey);
} catch (HeaderMissingException $ex) {
    /* The server didn't provide a header. Discard and log the error! */
} catch (InvalidMessageException $ex) {
    /* Invalid signature for the message. Discard and log the error! */
}
```

## Example 2: Mutually Signed JSON API with the Guzzle Adapter

This example takes advantage of an Adapter the provides the convenience methods
described in [`ConvenienceInterface`](docs/Internals/Adapter/ConvenienceInterface.md).

### Client-Side: Sending a Signed Request, Verifying the Response

```php
<?php
use GuzzleHttp\Client;
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Sapient\Adapter\Guzzle as GuzzleAdapter;
use ParagonIE\Sapient\Sapient;
use ParagonIE\Sapient\CryptographyKeys\SigningPublicKey;
use ParagonIE\Sapient\CryptographyKeys\SigningSecretKey;
use ParagonIE\Sapient\Exception\InvalidMessageException;

$http = new Client([
    'base_uri' => 'https://your-api.example.com'
]);
$sapient = new Sapient(new GuzzleAdapter($http));

// Keys
$clientSigningKey = new SigningSecretKey(
    Base64UrlSafe::decode(
        'AHxoibWhTylBMgFzJp6GGgYto24PVbQ-ognw9SPnvKppfti72R8By8XnIMTJ8HbDTks7jK5GmAnvtzaj3rbcTA=='
    )
);
$serverPublicKey = new SigningPublicKey(
    Base64UrlSafe::decode(
        'NvwsINZ-1y0F11xxed_FEUaL_MVewhdgF9tMYf5qEEw='
    )
);

// We use an array to define our message
$myMessage = [
    'date' => (new DateTime)->format(DateTime::ATOM),
    'body' => [
        'test' => 'hello world!'        
    ]
];

// Create the signed request:
$request = $sapient->createSignedJsonRequest(
    'POST',
     '/my/api/endpoint',
     $myMessage,
     $clientSigningKey
);

$response = $http->send($request);
try {
    /** @var array $verifiedResponse */
    $verifiedResponse = $sapient->decodeSignedJsonResponse(
        $response,
        $serverPublicKey
    );
} catch (InvalidMessageException $ex) {
    \http_response_code(500);
    exit;
}

```

### Server-Side: Verifying a Signed Request, Signing a Response

```php
 <?php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\ServerRequest;
use ParagonIE\ConstantTime\Base64UrlSafe;
use ParagonIE\Sapient\Adapter\Guzzle as GuzzleAdapter;
use ParagonIE\Sapient\Sapient;
use ParagonIE\Sapient\CryptographyKeys\SigningPublicKey;
use ParagonIE\Sapient\CryptographyKeys\SigningSecretKey;
use ParagonIE\Sapient\Exception\InvalidMessageException;

$http = new Client([
    'base_uri' => 'https://your-api.example.com'
]);
$sapient = new Sapient(new GuzzleAdapter($http));
 
$clientPublicKey = new SigningPublicKey(
    Base64UrlSafe::decode(
        'aX7Yu9kfAcvF5yDEyfB2w05LO4yuRpgJ77c2o9623Ew='
    )
);
$request = ServerRequest::fromGlobals();
try {
    /** @var array $decodedRequest */
    $decodedRequest = $sapient->decodeSignedJsonRequest(
        $request,
        $clientPublicKey
    );
} catch (InvalidMessageException $ex) {
    \http_response_code(500);
    exit;
}

/* Business logic goes here */

// Signing a response:
$serverSignSecret = new SigningSecretKey(
    Base64UrlSafe::decode(
        'q6KSHArUnD0sEa-KWpBCYLka805gdA6lVG2mbeM9kq82_Cwg1n7XLQXXXHF538URRov8xV7CF2AX20xh_moQTA=='
    )
);

$responseMessage = [
    'date' => (new DateTime)->format(DateTime::ATOM),
    'body' => [
        'status' => 'OK',
        'message' => 'We got your message loud and clear.'
    ]
];

$response = $sapient->createSignedJsonResponse(
    200,
    $responseMessage,
    $serverSignSecret
);
/* If your framework speaks PSR-7, just return the response object and let it
   take care of the rest. */
```
