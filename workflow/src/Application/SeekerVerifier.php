<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Domain\SeekerDispatch;
use AIC\Workflow\Domain\SeekerInspector;
use AIC\Workflow\Domain\SeekerLedger;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_diff;
use function array_values;
use function in_array;

/**
 * What the guard's record says of a filed ledger (#103, Droost's
 * `seekerDispatch`): with no return hook wired it is unverified; an
 * independent ledger needs an `aic-seeker` return since the last filing
 * whose digest is its own and is not filed already, or it is refused; a
 * self-reviewed one is none.
 */
final readonly class SeekerVerifier
{
    /**
     * Why an independent ledger with no return behind it is refused.
     */
    private const string NOT_DISPATCHED = 'no aic-seeker has returned since the last ledger, '
        . 'so this independent ledger was not dispatched: run the aic-seeker agent and file what it returns, '
        . 'or file it as "Inspector: self-reviewed", which counts as no inspection.';

    /**
     * Why an independent ledger that no seeker returned is refused.
     */
    private const string NOT_THE_SEEKERS = 'this ledger is not the one the aic-seeker returned: '
        . 'file its section unchanged, or dispatch it again.';

    /**
     * @param SeekerReturns $seekerReturns The guard's record.
     * @param SeekerLog     $seekerLog     When the last ledger was filed.
     */
    public function __construct(
        private SeekerReturns $seekerReturns,
        private SeekerLog $seekerLog,
    ) {
    }

    /**
     * @param string       $runId        The run.
     * @param SeekerLedger $seekerLedger The ledger.
     * @param string       $digest       Its digest.
     *
     * @throws WorkflowRefusal When an independent ledger has no return behind it, or not this one.
     *
     * @return SeekerDispatch Verified, unverified or none.
     */
    public function dispatch(string $runId, SeekerLedger $seekerLedger, string $digest): SeekerDispatch
    {
        if (!$this->seekerReturns->wired()) {
            return SeekerDispatch::Unverified;
        }

        $digests = array_values(array_diff(
            $this->seekerReturns->digests($runId, $this->seekerLog->lastFiledAt($runId)),
            $this->seekerLog->filedDigests($runId),
        ));
        $independent = $seekerLedger->seekerInspector === SeekerInspector::Independent;

        return match (true) {
            in_array($digest, $digests, true) => SeekerDispatch::Verified,
            !$independent => SeekerDispatch::None,
            $digests === [] => throw new WorkflowRefusal(self::NOT_DISPATCHED),
            default => throw new WorkflowRefusal(self::NOT_THE_SEEKERS),
        };
    }
}
