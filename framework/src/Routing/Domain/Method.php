<?php

declare(strict_types=1);

namespace AIC\Routing\Domain;

use function strtoupper;

/**
 * The HTTP methods a route can be declared for: GET (with HEAD answered on it)
 * and POST so far; the others join with their first declaring method (the
 * dead-code rule, plan.md).
 */
enum Method: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case HEAD = 'HEAD';

    /**
     * Whether a route declared for this method answers a request with the given
     * one: the same method, or HEAD on a GET route (RFC 9110 section 9.3.2).
     *
     * @param string $request The request's method, in any case.
     *
     * @return bool Whether the route answers it.
     */
    public function answers(string $request): bool
    {
        $method = strtoupper($request);

        return $method === $this->value || ($this === self::GET && $method === self::HEAD->value);
    }

    /**
     * @return list<string> The methods a route declared for this one answers.
     */
    public function answered(): array
    {
        return $this === self::GET
            ? [self::GET->value, self::HEAD->value]
            : [$this->value];
    }
}
