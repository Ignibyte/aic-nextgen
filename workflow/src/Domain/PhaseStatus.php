<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * Where one phase of a run stands (#100): not reached, current, or passed.
 * #101 adds `failed` with its retry budget.
 */
enum PhaseStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Passed = 'passed';
}
