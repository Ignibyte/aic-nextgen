<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * What one `workflow:run` did (#100): moved to the next phase, held at this
 * one, or finished the run.
 */
enum Outcome: string
{
    case Advanced = 'advanced';
    case Blocked = 'blocked';
    case Completed = 'completed';

    /**
     * @return int The command's exit code: 1 when held.
     */
    public function exitCode(): int
    {
        return match ($this) {
            self::Advanced, self::Completed => 0,
            self::Blocked => 1,
        };
    }
}
