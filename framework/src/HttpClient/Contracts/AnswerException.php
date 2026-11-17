<?php

declare(strict_types=1);

namespace AIC\HttpClient\Contracts;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

/**
 * An upstream answered with something that is not an HTTP response (#305): no
 * status line, or a status outside 100 to 599.
 */
final class AnswerException extends RuntimeException implements ClientExceptionInterface
{
}
