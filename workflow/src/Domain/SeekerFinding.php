<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * One row of a seeker's ledger (#103): its reference, severity, checklist
 * item, location, text and status, with the reason a carried row gives.
 */
final readonly class SeekerFinding
{
    /**
     * @param string         $ref            `F1`, `F2`, ...
     * @param SeekerSeverity $seekerSeverity How bad it is.
     * @param string         $item           The checklist id it cites, such as `A1`.
     * @param string         $location       Where, such as `path:line`.
     * @param string         $finding        What, after the item's id.
     * @param SeekerStatus   $seekerStatus   Where it stands.
     * @param string         $reason         A carried row's reason; empty otherwise.
     */
    public function __construct(
        public string $ref,
        public SeekerSeverity $seekerSeverity,
        public string $item,
        public string $location,
        public string $finding,
        public SeekerStatus $seekerStatus,
        public string $reason = '',
    ) {
    }

    /**
     * @return bool Whether it holds the run: open, and CRITICAL or HIGH.
     */
    public function holding(): bool
    {
        return $this->seekerStatus === SeekerStatus::Open && $this->seekerSeverity->holds();
    }

    /**
     * @return string It in one line, as a row's `why` names it.
     */
    public function line(): string
    {
        return $this->ref . ' ' . $this->seekerSeverity->value . ' ' . $this->item . ' ' . $this->location . ': ' . $this->finding;
    }
}
