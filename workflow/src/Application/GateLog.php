<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\GateLogEntry;
use AIC\Workflow\Domain\GateLogQuery;
use AIC\Workflow\Exception\WorkflowRefusal;

/** A read-only boundary to one actual stored gate result and its transcript. */
interface GateLog
{
    /**
     * @param GateLogQuery $gateLogQuery The validated gate, phase and attempt selectors.
     * @param string       $runId        The selected run, resolved before lookup.
     *
     * @throws WorkflowRefusal When the evidence cannot be read or is malformed.
     *
     * @return GateLogEntry|null The latest matching stored record, or null when none matches.
     */
    public function find(GateLogQuery $gateLogQuery, string $runId): ?GateLogEntry;
}
