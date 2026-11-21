<?php

declare(strict_types=1);

namespace {namespace}\Job;

use AIC\Controller\Contracts\Job;
use AIC\Controller\Contracts\JobInput;
use AIC\Controller\Contracts\JobResponse;
use AIC\Controller\Domain\CsvDocument;
use AIC\Controller\Domain\JobDone;
use AIC\Controller\Domain\JobFailed;
use AIC\Storage\Application\PrivateFiles;
use {namespace}\Repository\{repository};
use InvalidArgumentException;
use Override;
use RuntimeException;

/**
 * The job `{job}`, made by bin/aic make:export (#323): writes one
 * export as CSV to the private files, where its download finds it. The
 * header and the rows stand in for what the module reads: replace them
 * with a repository's query.
 */
final readonly class {class} implements Job
{
    /**
     * The CSV's first line.
     */
    private const array HEADER = ['name', 'count'];

    /**
     * The CSV's rows, each in the header's order.
     */
    private const array ROWS = [['apples', '3'], ['pears', '5']];

    /**
{params}     */
    public function __construct(
        private {repository} ${repositoryVariable},
        private PrivateFiles $privateFiles,
    ) {
    }

    /**
     * @param JobInput $jobInput The payload: `export`, the export's id.
     *
     * @throws InvalidArgumentException When a type or the key is invalid.
     * @throws RuntimeException         When the export cannot be read (`QueryException`) or the file written; the
     *                                  queue retries.
     *
     * @return JobResponse Done; failed for a payload that names no export within its days.
     */
    #[Override]
    public function run(JobInput $jobInput): JobResponse
    {
        $id = $jobInput->value('export', '');

        if (!$this->{repositoryVariable}->live($id)) {
            return new JobFailed('The {job} payload names no export within its days: "' . $id . '".');
        }

        $this->privateFiles->put($this->{repositoryVariable}->key($id), (new CsvDocument(self::HEADER, self::ROWS))->text());

        return new JobDone();
    }
}
