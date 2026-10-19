<?php

declare(strict_types=1);

namespace AIC\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

/**
 * No service is registered under the id.
 */
final class NotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
}
