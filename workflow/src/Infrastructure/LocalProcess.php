<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\ProcessRunner;
use AIC\Workflow\Domain\ProcessResult;
use Override;

use function hrtime;

/** Runs project commands with one deadline for startup, input and execution. */
final readonly class LocalProcess implements ProcessRunner
{
    /** @param string $root The project's working directory. */
    public function __construct(private string $root)
    {
    }

    /**
     * @param list<string> $argv    The command and its literal arguments.
     * @param int          $timeout Seconds allowed before owned process-group teardown.
     * @param string       $input   Standard input, written without blocking output reads.
     *
     * @return ProcessResult Actual exit and merged output; 124 on timeout, 127 on launch failure.
     */
    #[Override]
    public function run(array $argv, int $timeout, string $input = ''): ProcessResult
    {
        $deadline = (float) hrtime(true) / 1_000_000_000.0 + (float) $timeout;
        $session = (new ProcessLaunch())->open($this->root, $argv, $input);

        if ($session === null) {
            return new ProcessResult(127, '');
        }

        return $session->run($deadline);
    }
}
