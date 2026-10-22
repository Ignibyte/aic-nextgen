<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * One gate's verdict, or the whole run's (#101, Droost's `GateStatus`
 * trimmed): passed; failed; deferred, which is not a pass and does not block
 * by itself; the tool missing; or the run not reaching a verdict.
 */
enum GateStatus: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Deferred = 'deferred';
    case ErrorToolMissing = 'error_tool_missing';
    case ErrorToolFailed = 'error_tool_failed';

    /**
     * @return CheckState How the store holds it.
     */
    public function checkState(): CheckState
    {
        return match ($this) {
            self::Passed => CheckState::Satisfied,
            self::Failed => CheckState::Blocked,
            self::Deferred => CheckState::Skipped,
            self::ErrorToolMissing, self::ErrorToolFailed => CheckState::Errored,
        };
    }

    /**
     * @return Fault Whose it is: a failed gate is the agent's, a run that did not finish the environment's.
     */
    public function fault(): Fault
    {
        return match ($this) {
            self::Passed, self::Deferred => Fault::None,
            self::Failed => Fault::Agent,
            self::ErrorToolMissing, self::ErrorToolFailed => Fault::Environment,
        };
    }
}
