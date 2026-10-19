<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use InvalidArgumentException;

/**
 * Request input that does not fit AIC's typed shapes: a field nested deeper than
 * one level, a field name used in two shapes, or a name with no base.
 *
 * The worker answers it with 400 Bad Request.
 */
final class MalformedInputException extends InvalidArgumentException
{
}
