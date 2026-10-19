<?php

declare(strict_types=1);

namespace AIC\Entity\Application;

use RuntimeException;

/**
 * No row of the entity type has the key, or no row matches the query.
 */
final class EntityNotFoundException extends RuntimeException
{
}
