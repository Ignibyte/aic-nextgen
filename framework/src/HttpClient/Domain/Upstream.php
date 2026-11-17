<?php

declare(strict_types=1);

namespace AIC\HttpClient\Domain;

use InvalidArgumentException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;

use function ltrim;
use function rtrim;

/**
 * One upstream API an app calls (#305), from `config/app.php`: where it is,
 * and the token it takes, which its requests carry as a bearer header.
 */
final readonly class Upstream
{
    /**
     * @param string $baseUrl Its base URL, `https://api.example.com/v2`.
     * @param string $token   Its credential, read from the environment in config; empty for none.
     */
    public function __construct(
        public string $baseUrl,
        public string $token = '',
    ) {
    }

    /**
     * @param string $path A path under the base URL, `/status`.
     *
     * @return string The full URL.
     */
    public function url(string $path): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * @param RequestFactoryInterface $requestFactory Makes the request.
     * @param string                  $method         The request method.
     * @param string                  $path           A path under the base URL.
     *
     * @throws InvalidArgumentException When the method, the URL or the token is not one a request can hold.
     *
     * @return RequestInterface A request to it, with the token as a bearer header when there is one.
     */
    public function request(RequestFactoryInterface $requestFactory, string $method, string $path): RequestInterface
    {
        $request = $requestFactory->createRequest($method, $this->url($path));

        return $this->token === ''
            ? $request
            : self::signed($request->withHeader('Authorization', 'Bearer ' . $this->token));
    }

    /**
     * @param MessageInterface $message A request's copy, which a PSR-7 wither types as the message it is.
     *
     * @throws InvalidArgumentException When it is not a request.
     *
     * @return RequestInterface The copy, as a request.
     */
    private static function signed(MessageInterface $message): RequestInterface
    {
        if (!$message instanceof RequestInterface) {
            throw new InvalidArgumentException('A request wither must return a request.');
        }

        return $message;
    }
}
