<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * Ticket statuses by name (#100), read without an enum's static `from()`.
 */
final readonly class TicketStatuses
{
    /**
     * @param string $name A status as a ticket file writes it.
     *
     * @return TicketStatus|null The status of that name; none for any other.
     */
    public function named(string $name): ?TicketStatus
    {
        $statuses = [
            TicketStatus::Open,
            TicketStatus::InProgress,
            TicketStatus::InReview,
            TicketStatus::Done,
            TicketStatus::WontFix,
            TicketStatus::Abandoned,
            TicketStatus::Closed,
        ];

        foreach ($statuses as $status) {
            if ($status->value === $name) {
                return $status;
            }
        }

        return null;
    }
}
