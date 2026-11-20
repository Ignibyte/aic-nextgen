<?php

declare(strict_types=1);

namespace AIC\Workflow\Exception;

use RuntimeException;

/**
 * A statement of a schema dump that AIC's Schema declarations cannot state (#318): its message says what, and
 * `bin/aic schema:import` lists it among the findings that stop the import.
 */
final class UnsupportedSql extends RuntimeException
{
}
