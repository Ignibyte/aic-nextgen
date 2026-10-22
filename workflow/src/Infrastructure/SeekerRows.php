<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Domain\Phase;
use AIC\Workflow\Domain\SeekerDispatch;
use AIC\Workflow\Domain\SeekerFinding;
use AIC\Workflow\Domain\SeekerInspection;
use AIC\Workflow\Domain\SeekerInspector;
use AIC\Workflow\Domain\SeekerLedger;
use AIC\Workflow\Domain\SeekerSeverity;
use AIC\Workflow\Domain\SeekerStatus;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_map;

/**
 * The seeker's store rows read back as values (#103): each stored word read
 * by `match`, so a row the engine did not write is refused by name.
 */
final readonly class SeekerRows
{
    /**
     * @param JsonReader       $jsonReader A `seeker_inspection` row.
     * @param list<JsonReader> $findings   Its `seeker_finding` rows.
     *
     * @throws WorkflowRefusal When a value is not one the engine writes.
     *
     * @return SeekerInspection The inspection.
     */
    public function inspection(JsonReader $jsonReader, array $findings): SeekerInspection
    {
        $seekerLedger = new SeekerLedger(
            $jsonReader->string('inspector') === 'independent' ? SeekerInspector::Independent : SeekerInspector::SelfReviewed,
            $jsonReader->string('subject_hash'),
            array_map($this->finding(...), $findings),
            $jsonReader->int('observations'),
            $jsonReader->string('ledger'),
        );

        return new SeekerInspection(
            $jsonReader->int('round'),
            $this->phase($jsonReader->string('phase')),
            $seekerLedger,
            $this->dispatch($jsonReader->string('dispatch')),
            $jsonReader->string('digest'),
            $jsonReader->string('filed_at'),
        );
    }

    /**
     * @param JsonReader $jsonReader A `seeker_finding` row.
     *
     * @throws WorkflowRefusal When a value is not one the engine writes.
     *
     * @return SeekerFinding The finding.
     */
    private function finding(JsonReader $jsonReader): SeekerFinding
    {
        return new SeekerFinding(
            $jsonReader->string('ref'),
            match ($jsonReader->string('severity')) {
                'CRITICAL' => SeekerSeverity::Critical,
                'HIGH' => SeekerSeverity::High,
                'MEDIUM' => SeekerSeverity::Medium,
                'LOW' => SeekerSeverity::Low,
                default => throw new WorkflowRefusal('a stored seeker finding has an unknown severity.'),
            },
            $jsonReader->string('item'),
            $jsonReader->string('location'),
            $jsonReader->string('finding'),
            match ($jsonReader->string('status')) {
                'open' => SeekerStatus::Open,
                'resolved' => SeekerStatus::Resolved,
                'carried' => SeekerStatus::Carried,
                default => throw new WorkflowRefusal('a stored seeker finding has an unknown status.'),
            },
            $jsonReader->string('reason'),
        );
    }

    /**
     * @param string $phase A stored phase.
     *
     * @throws WorkflowRefusal When it is not one a ledger is filed at.
     *
     * @return Phase The phase.
     */
    private function phase(string $phase): Phase
    {
        return match ($phase) {
            'code' => Phase::Code,
            'check' => Phase::Check,
            'complete' => Phase::Complete,
            default => throw new WorkflowRefusal('a stored seeker inspection has an unknown phase.'),
        };
    }

    /**
     * @param string $dispatch A stored dispatch.
     *
     * @throws WorkflowRefusal When it is not one the engine writes.
     *
     * @return SeekerDispatch The dispatch.
     */
    private function dispatch(string $dispatch): SeekerDispatch
    {
        return match ($dispatch) {
            'verified' => SeekerDispatch::Verified,
            'unverified' => SeekerDispatch::Unverified,
            'none' => SeekerDispatch::None,
            default => throw new WorkflowRefusal('a stored seeker inspection has an unknown dispatch.'),
        };
    }
}
