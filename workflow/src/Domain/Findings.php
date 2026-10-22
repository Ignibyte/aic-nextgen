<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

use function implode;

/**
 * What an audit found (#101): its failures, each one line. None is a
 * satisfied verdict; any is a blocked one, the agent's to fix.
 */
final readonly class Findings
{
    /**
     * @param string       $name        The check, as criteria cite it.
     * @param list<string> $failures    Each failure, one sentence.
     * @param string       $passed      Why it passed, when nothing failed.
     * @param string       $subjectHash The tree it judged.
     */
    public function __construct(
        public string $name,
        public array $failures,
        public string $passed,
        public string $subjectHash,
    ) {
    }

    /**
     * @return CheckRecord The verdict.
     */
    public function record(): CheckRecord
    {
        return $this->failures === []
            ? new CheckRecord($this->name, CheckState::Satisfied, Fault::None, $this->passed, $this->subjectHash)
            : new CheckRecord($this->name, CheckState::Blocked, Fault::Agent, implode(' ', $this->failures), $this->subjectHash);
    }
}
