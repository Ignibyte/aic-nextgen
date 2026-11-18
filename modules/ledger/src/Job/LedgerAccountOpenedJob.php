<?php

declare(strict_types=1);

namespace App\Ledger\Job;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityStore;
use App\Ledger\Entity\LedgerTypes;
use InvalidArgumentException;
use Override;

/**
 * The `ledger-account-opened` job, made by bin/aic make:job (#122): the
 * listener on each new ledger account (#309), queued in the insert's
 * transaction. It writes a notice of what its payload names, never the key,
 * so the notices read the same on every pass.
 */
final readonly class LedgerAccountOpenedJob implements Job
{
    /**
     * @param EntityStore $entityStore The entity store.
     * @param LedgerTypes $ledgerTypes The ledger's entity types.
     */
    public function __construct(
        private EntityStore $entityStore,
        private LedgerTypes $ledgerTypes,
    ) {
    }

    /**
     * @param JobInput $jobInput The payload: `entity`, `key`, `event` and `fields`.
     *
     * @throws InvalidArgumentException When the notice does not fit its field.
     * @throws QueryException           When the notice cannot be written.
     *
     * @return JobResponse Done.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        $line = $jobInput->value('entity', '') . ' ' . $jobInput->value('event', '') . ': ' . $jobInput->value('fields', '');
        $this->entityStore->insert($this->ledgerTypes->notices(), ['line' => $line]);

        return new JobDone();
    }
}
