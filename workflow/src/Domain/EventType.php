<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * The run-event types (#100), `aic.run-event/1`'s `type`.
 */
enum EventType: string
{
    case RunStarted = 'run.started';
    case PhaseBegan = 'phase.began';
    case PhaseAttempted = 'phase.attempted';
    case PhaseEnded = 'phase.ended';
    case TicketMoved = 'ticket.moved';
    case RunCompleted = 'run.completed';
    case RunReset = 'run.reset';
}
