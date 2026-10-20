<?php

declare(strict_types=1);

namespace AIC\Form\Application;

use Psr\Http\Message\ServerRequestInterface;

use function is_array;
use function is_string;

/**
 * Reads one posted form field as a string (#9): the one place controllers and
 * the form guard read the parsed body.
 */
final readonly class PostedFields
{
    /**
     * @param ServerRequestInterface $serverRequest A posted form.
     * @param string                 $name          A field.
     *
     * @return string Its value; empty when absent or not a string.
     */
    public function value(ServerRequestInterface $serverRequest, string $name): string
    {
        $body = $serverRequest->getParsedBody();
        $value = is_array($body)
            ? $body[$name] ?? ''
            : '';

        return is_string($value)
            ? $value
            : '';
    }
}
