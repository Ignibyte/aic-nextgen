<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\CodeQuery;
use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The lookups a run recorded (#118): its `code.queried` events at one phase,
 * oldest first. Only the engine and the `code:*` commands write the log, and
 * the guard walls it.
 */
interface CodeQueryLedger
{
    /**
     * @param string $runId The run.
     * @param Phase  $phase The phase the lookups were made at.
     *
     * @throws WorkflowRefusal When the log exists and cannot be read.
     *
     * @return list<CodeQuery> Its lookups at the phase, oldest first; a line that does not parse is skipped.
     */
    public function queries(string $runId, Phase $phase): array;
}
