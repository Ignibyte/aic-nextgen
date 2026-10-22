<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * How `workflow:init` holds a pack file (#105): owned, upgraded while the
 * lock shows it unedited; written once and never again; or a region between
 * markers in a file the project owns.
 */
enum PackRole: string
{
    case Owned = 'owned';
    case WriteOnce = 'write-once';
    case Region = 'region';
}
