<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Import;

/**
 * How an imported column makes its own value as a key (#318).
 */
enum ColumnKey
{
    case None;
    case Identity;
    case IdentityByDefault;
    case Serial;
    case UuidKey;
}
