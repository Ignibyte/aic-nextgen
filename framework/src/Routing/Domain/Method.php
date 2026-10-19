<?php

declare(strict_types=1);

namespace AIC\Routing\Domain;

/**
 * The HTTP request methods the router serves.
 *
 * A backed string enum, so request paths stay typed. Each value is the
 * canonical uppercase wire form PSR-7's `ServerRequestInterface::getMethod()`
 * returns. The set is closed: a route registered for "any" method expands to
 * one registration per case rather than a wildcard value.
 *
 * Ported from old AIC's ward-routing (`e0778896`). Its `fromRequestMethod()`
 * returns with the router that calls it (#4).
 */
enum Method: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
    case OPTIONS = 'OPTIONS';
    case HEAD = 'HEAD';
}
