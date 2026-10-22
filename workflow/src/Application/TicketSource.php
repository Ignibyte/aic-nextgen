<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\Ticket;
use AIC\Workflow\Domain\TicketStatus;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * Where a project's tickets are (#100): a run reads its ticket and writes its
 * status and phase, nothing more.
 */
interface TicketSource
{
    /**
     * @param string $id A ticket's id, its number as text.
     *
     * @throws WorkflowRefusal When there is no such ticket, or it cannot be read.
     *
     * @return Ticket The ticket.
     */
    public function get(string $id): Ticket;

    /**
     * @param Ticket       $ticket       The ticket.
     * @param TicketStatus $ticketStatus Its status from now.
     * @param string       $phase        Its phase line from now: `plan`, `code`, `check` or `docs`.
     *
     * @throws WorkflowRefusal When the file cannot be written.
     *
     * @return Ticket The ticket as written.
     */
    public function move(Ticket $ticket, TicketStatus $ticketStatus, string $phase): Ticket;
}
