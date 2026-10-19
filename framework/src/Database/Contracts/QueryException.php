<?php

declare(strict_types=1);

namespace AIC\Database\Contracts;

use RuntimeException;

/**
 * A query failed, or returned no row where one was required. The message names
 * the SQLSTATE and never a credential.
 */
final class QueryException extends RuntimeException
{
}
