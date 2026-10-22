<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\EventType;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Domain\SeekerInspection;
use AIC\Workflow\Domain\SeekerSeverity;

use function count;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * The `seeker.filed` event (#103): what was filed, in the run's event log.
 * A line that cannot be written is a note, never a refusal.
 */
final readonly class SeekerJournal
{
    /**
     * @param EventLog $eventLog The run's event log.
     */
    public function __construct(
        private EventLog $eventLog,
    ) {
    }

    /**
     * @param RunState         $runState         The run.
     * @param SeekerInspection $seekerInspection The filed inspection.
     *
     * @return list<string> A note when the event could not be logged; none otherwise.
     */
    public function filed(RunState $runState, SeekerInspection $seekerInspection): array
    {
        $seekerLedger = $seekerInspection->seekerLedger;
        $note = $this->eventLog->append($runState, EventType::SeekerFiled, json_encode([
            'counted' => $seekerInspection->counted(),
            'counts' => [
                'critical' => $seekerLedger->open(SeekerSeverity::Critical),
                'high' => $seekerLedger->open(SeekerSeverity::High),
                'low' => $seekerLedger->open(SeekerSeverity::Low),
                'medium' => $seekerLedger->open(SeekerSeverity::Medium),
            ],
            'digest' => $seekerInspection->digest,
            'dispatch' => $seekerInspection->seekerDispatch->value,
            'holding' => count($seekerLedger->holding()),
            'inspector' => $seekerLedger->seekerInspector->value,
            'phase' => $seekerInspection->phase->value,
            'round' => $seekerInspection->round,
            'subject' => $seekerLedger->subject,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $note === ''
            ? []
            : [$note];
    }
}
