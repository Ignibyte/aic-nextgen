<?php

declare(strict_types=1);

namespace Worker\Report\Job;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\JobDone;
use AIC\Controller\Domain\JobFailed;
use AIC\Storage\Application\PrivateFiles;
use InvalidArgumentException;
use Override;
use RuntimeException;
use Worker\Report\Service\StatementDocument;

use function preg_match;

/**
 * Makes the statement the statement module queued (#336) and keeps it in the
 * app's private files under the job's id, `statements/<id>.pdf`, where the
 * module's Stream sends it from. It runs interpreted, never in the compiled
 * site: FPDF does not compile.
 */
final readonly class StatementJob implements Job
{
    /**
     * @param PrivateFiles $privateFiles The app's private files.
     */
    public function __construct(
        private PrivateFiles $privateFiles,
    ) {
    }

    /**
     * @param JobInput $jobInput The order: `account` and `date`.
     *
     * @throws InvalidArgumentException When the key is not a storage key.
     * @throws RuntimeException         When FPDF makes no document or the file cannot be written.
     *
     * @return JobResponse Done once the file is kept; failed for an order without an account or a date.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        $account = $jobInput->value('account', '');
        $date = $jobInput->value('date', '');

        if ($account === '' || preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $date) !== 1) {
            return new JobFailed('A statement needs an account and a date.');
        }

        $this->privateFiles->put('statements/' . $jobInput->id . '.pdf', (new StatementDocument())->pdf($account, $date));

        return new JobDone();
    }
}
