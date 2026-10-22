<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CodeIndexReport;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The code index, kept current (#106): every query refreshes it first, and
 * a refresh re-parses only the files whose content changed.
 */
interface CodeIndex
{
    /**
     * @param bool $full Whether to drop what it holds and build it whole.
     *
     * @throws WorkflowRefusal When it cannot be built: no git repository, a file unreadable, the store unwritable.
     *
     * @return CodeIndexReport What the refresh saw and did.
     */
    public function refresh(bool $full): CodeIndexReport;
}
