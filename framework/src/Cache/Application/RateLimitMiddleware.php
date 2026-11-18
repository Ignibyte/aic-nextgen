<?php

declare(strict_types=1);

namespace AIC\Cache\Application;

use AIC\Cache\Domain\RateLimits;
use AIC\Database\Contracts\QueryException;
use AIC\Http\Application\ResponseBuilder;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function array_key_exists;
use function is_string;

/**
 * Limits how often a client reaches a path (#312): each `RateLimit` whose
 * prefix the path starts with counts the hit, and past one the answer is 429
 * with `Retry-After`, plain text in every kind. A module lists the middleware
 * and gives the limits from its factory (`RateLimits`). The client is the
 * peer's address; behind a proxy that is the proxy.
 */
final readonly class RateLimitMiddleware implements MiddlewareInterface
{
    /**
     * @param RateLimiter     $rateLimiter     Counts each hit.
     * @param RateLimits      $rateLimits      The app's limits.
     * @param ResponseBuilder $responseBuilder Makes the 429.
     */
    public function __construct(
        private RateLimiter $rateLimiter,
        private RateLimits $rateLimits,
        private ResponseBuilder $responseBuilder,
    ) {
    }

    /**
     * @param ServerRequestInterface  $request The request.
     * @param RequestHandlerInterface $handler The rest of the stack.
     *
     * @throws InvalidArgumentException When a limit's count is malformed.
     * @throws QueryException           When a hit cannot be counted.
     *
     * @return ResponseInterface The handler's response, or 429 once a limit is passed.
     */
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $client = self::client($request);

        foreach ($this->rateLimits->guarding($request->getUri()->getPath()) as $rateLimit) {
            $rateDecision = $this->rateLimiter->hit($rateLimit->name->value, $client, $rateLimit->limit, $rateLimit->seconds);

            if (!$rateDecision->allowed) {
                $response = $this->responseBuilder->create(
                    429,
                    'text/plain; charset=utf-8',
                    'Too many requests: try again in ' . $rateDecision->retryAfter . ' seconds.',
                );

                return $this->responseBuilder->withHeader($response, 'Retry-After', (string) $rateDecision->retryAfter);
            }
        }

        return $handler->handle($request);
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     *
     * @return string The peer's address, or `unknown` when the server gave none.
     */
    private static function client(ServerRequestInterface $serverRequest): string
    {
        $serverParams = $serverRequest->getServerParams();

        if (!array_key_exists('REMOTE_ADDR', $serverParams) || !is_string($serverParams['REMOTE_ADDR'])) {
            return 'unknown';
        }

        return $serverParams['REMOTE_ADDR'] === ''
            ? 'unknown'
            : $serverParams['REMOTE_ADDR'];
    }
}
