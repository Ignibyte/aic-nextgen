<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * What one `workflow:run` did (#100): moved to the next phase, held at this
 * one, finished the run, or left the phase to the operator (#102).
 */
enum Outcome: string
{
    case Advanced = 'advanced';
    case Blocked = 'blocked';
    case Completed = 'completed';
    case Held = 'held';

    /**
     * @return int The command's exit code: 1 when held.
     */
    public function exitCode(): int
    {
        return match ($this) {
            self::Advanced, self::Completed => 0,
            self::Blocked, self::Held => 1,
        };
    }
}
