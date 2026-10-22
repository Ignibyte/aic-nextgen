<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * A ticket's status, as AIC's ticket files spell it (#100). A run may work a
 * ticket that is open or underway; the terminal ones are delivery's.
 */
enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in-progress';
    case InReview = 'in-review';
    case Done = 'done';
    case WontFix = 'won-t-fix';
    case Abandoned = 'abandoned';
    case Closed = 'closed';

    /**
     * @return bool Whether the ticket is finished: no run may work it.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Open, self::InProgress, self::InReview => false,
            self::Done, self::WontFix, self::Abandoned, self::Closed => true,
        };
    }
}
