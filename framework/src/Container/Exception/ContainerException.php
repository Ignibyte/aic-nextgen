<?php

declare(strict_types=1);

namespace AIC\Container\Exception;

use Psr\Container\ContainerExceptionInterface;
use RuntimeException;

/**
 * A service could not be made: its factory threw, its dependencies form a
 * cycle, or the container was changed after it was frozen.
 */
final class ContainerException extends RuntimeException implements ContainerExceptionInterface
{
}
