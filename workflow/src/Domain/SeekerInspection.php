<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * One filed ledger (#103): the run's round, the phase it was filed at, the
 * ledger, what the guard's record says of it, and when. Only an independent
 * ledger an `aic-seeker` returned counts.
 */
final readonly class SeekerInspection
{
    /**
     * The subagent type whose returns count.
     */
    public const string AGENT = 'aic-seeker';

    /**
     * @param int            $round          Which filing of the run, from 1.
     * @param Phase          $phase          The phase it was filed at.
     * @param SeekerLedger   $seekerLedger   The ledger.
     * @param SeekerDispatch $seekerDispatch What the guard's record says.
     * @param string         $digest         The ledger's digest.
     * @param string         $filedAt        When, UTC.
     */
    public function __construct(
        public int $round,
        public Phase $phase,
        public SeekerLedger $seekerLedger,
        public SeekerDispatch $seekerDispatch,
        public string $digest,
        public string $filedAt,
    ) {
    }

    /**
     * @return bool Whether it counts: independent, and returned by an `aic-seeker`.
     */
    public function counted(): bool
    {
        return $this->seekerLedger->seekerInspector === SeekerInspector::Independent
            && $this->seekerDispatch === SeekerDispatch::Verified;
    }
}
