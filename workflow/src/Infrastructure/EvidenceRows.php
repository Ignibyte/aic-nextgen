<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Domain\CheckRecord;
use AIC\Workflow\Domain\CheckState;
use AIC\Workflow\Domain\Fault;
use AIC\Workflow\Exception\WorkflowRefusal;

use function in_array;

/**
 * Reads the evidence store's rows back into values (#101).
 */
final readonly class EvidenceRows
{
    /**
     * @param JsonReader $jsonReader A `check_result` row.
     *
     * @throws WorkflowRefusal When it holds a state or fault this engine does not know.
     *
     * @return CheckRecord The verdict, its transcript and capture provenance.
     */
    public function record(JsonReader $jsonReader): CheckRecord
    {
        return new CheckRecord(
            $jsonReader->string('name'),
            $this->state($jsonReader->string('state')),
            $this->fault($jsonReader->string('fault')),
            $jsonReader->string('summary'),
            $jsonReader->string('subject_hash'),
            $jsonReader->string('output'),
            $this->complete($jsonReader->int('transcript_complete')),
        );
    }

    /**
     * @param int $complete The stored capture provenance.
     *
     * @throws WorkflowRefusal When it is not a stored boolean.
     *
     * @return bool Whether every observed byte was retained.
     */
    private function complete(int $complete): bool
    {
        if (!in_array($complete, [0, 1], true)) {
            throw new WorkflowRefusal('var/workflow/evidence.sqlite holds unknown transcript completeness.');
        }

        return $complete === 1;
    }

    /**
     * @param string $state A stored state.
     *
     * @throws WorkflowRefusal When this engine does not know it.
     *
     * @return CheckState It.
     */
    private function state(string $state): CheckState
    {
        return match ($state) {
            'satisfied' => CheckState::Satisfied,
            'blocked' => CheckState::Blocked,
            'skipped' => CheckState::Skipped,
            'errored' => CheckState::Errored,
            default => throw new WorkflowRefusal('var/workflow/evidence.sqlite holds an unknown check state.'),
        };
    }

    /**
     * @param string $fault A stored fault.
     *
     * @throws WorkflowRefusal When this engine does not know it.
     *
     * @return Fault It.
     */
    private function fault(string $fault): Fault
    {
        return match ($fault) {
            'none' => Fault::None,
            'agent' => Fault::Agent,
            'environment' => Fault::Environment,
            'unknown' => Fault::Unknown,
            default => throw new WorkflowRefusal('var/workflow/evidence.sqlite holds an unknown fault.'),
        };
    }
}
