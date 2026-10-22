<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * What a File Manifest row does to its path (#118): makes it, changes it, or
 * removes it. Read from the row's `Change` cell by `match`.
 */
enum FileChange: string
{
    case Create = 'create';
    case Modify = 'modify';
    case Delete = 'delete';

    /**
     * @return bool Whether the path must already be a file git lists.
     */
    public function needsFile(): bool
    {
        return $this !== self::Create;
    }
}
