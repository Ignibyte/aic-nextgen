<?php

declare(strict_types=1);

namespace AIC\OAuth\Application;

use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function is_array;
use function is_string;

/**
 * Reads an authorization request's named fields (#331): from the query of a
 * GET, from the body of the consent form's post. A field that is missing or
 * is not a string reads as empty.
 */
final readonly class RequestFields
{
    /**
     * @param ServerRequestInterface $serverRequest The request.
     * @param list<string>           $names         The fields to read.
     *
     * @return array<string, string> Each named field's value.
     */
    public function read(ServerRequestInterface $serverRequest, array $names): array
    {
        $fields = [];

        foreach ($names as $name) {
            $fields[$name] = self::value($serverRequest, $name);
        }

        return $fields;
    }

    /**
     * @param ServerRequestInterface $serverRequest The request.
     * @param string                 $name          A field's name.
     *
     * @return string Its value; empty when it is missing or not a string.
     */
    private static function value(ServerRequestInterface $serverRequest, string $name): string
    {
        $parameters = $serverRequest->getMethod() === 'GET'
            ? $serverRequest->getQueryParams()
            : $serverRequest->getParsedBody();

        return is_array($parameters) && array_key_exists($name, $parameters) && is_string($parameters[$name])
            ? $parameters[$name]
            : '';
    }
}
