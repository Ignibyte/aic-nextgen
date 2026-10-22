<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * A run's phases (#100), every run walking all four in order: plan, code,
 * check, complete. Droost's `test` is `check` here, AIC's quality checks.
 */
enum Phase: string
{
    case Plan = 'plan';
    case Code = 'code';
    case Check = 'check';
    case Complete = 'complete';

    /**
     * @return self|null The phase after this one; none after complete.
     */
    public function next(): ?self
    {
        return match ($this) {
            self::Plan => self::Code,
            self::Code => self::Check,
            self::Check => self::Complete,
            self::Complete => null,
        };
    }

    /**
     * @return string The ticket's `phase` while the run is here: `complete` is the ticket's `docs`.
     */
    public function ticketPhase(): string
    {
        return match ($this) {
            self::Plan, self::Code, self::Check => $this->value,
            self::Complete => 'docs',
        };
    }
}
