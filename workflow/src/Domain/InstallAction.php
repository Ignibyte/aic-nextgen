<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * What `workflow:init` did with one path (#105): wrote it, found it current,
 * kept the project's own version, or removed a file the pack retired.
 */
enum InstallAction: string
{
    case Wrote = 'wrote';
    case Current = 'current';
    case Kept = 'kept';
    case Removed = 'removed';
}
