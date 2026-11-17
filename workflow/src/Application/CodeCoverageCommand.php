<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Application\Console\ConsoleOutput;
use AIC\Workflow\Application\Coverage\CodeCoverage;
use AIC\Workflow\Application\Coverage\CoverageLines;
use AIC\Workflow\Application\Coverage\MadeCoverage;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function in_array;

/**
 * `bin/aic code:coverage [--json] [--missing|--made]` (#232): every
 * framework surface an app can use and the app files that use each, from
 * the code index, refreshed first; with `--made` (#302), per module and
 * theme, how many files a scaffold made. A report, not a gate: exit 0
 * whatever is unused; 1 when the index, the Tessera page or the made ledger
 * cannot be read, 2 for a line it does not take.
 */
final readonly class CodeCoverageCommand implements Command
{
    /**
     * The command and what it takes.
     */
    private const string USAGE = 'code:coverage [--json] [--missing|--made]';

    /**
     * @param CodeIndex     $codeIndex     The index, refreshed first.
     * @param CodeCoverage  $codeCoverage  The report.
     * @param MadeCoverage  $madeCoverage  The made report (#302).
     * @param CoverageLines $coverageLines Its answer.
     */
    public function __construct(
        private CodeIndex $codeIndex,
        private CodeCoverage $codeCoverage,
        private MadeCoverage $madeCoverage,
        private CoverageLines $coverageLines = new CoverageLines(),
    ) {
    }

    /**
     * @param CommandInput $commandInput `--json`, and `--missing` or `--made`.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResponse The report.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $json = in_array('--json', $commandInput->arguments(), true);
        $missing = in_array('--missing', $commandInput->arguments(), true);

        try {
            (new ConsoleArguments())->parse($commandInput, new CommandUsage('code:coverage', self::USAGE, ['json', 'missing', 'made']));
            $this->codeIndex->refresh(false);
            $lines = in_array('--made', $commandInput->arguments(), true)
                ? $this->made($json)
                : $this->surfaces($json, $missing);
        } catch (WorkflowRefusal $workflowRefusal) {
            return self::refused($workflowRefusal, $json);
        }

        return new CommandResult($lines, 0);
    }

    /**
     * @param bool $json    Whether `--json` was given.
     * @param bool $missing Whether `--missing` was given.
     *
     * @throws WorkflowRefusal When the index or the Tessera page cannot be read.
     *
     * @return list<string> The surfaces report.
     */
    private function surfaces(bool $json, bool $missing): array
    {
        $surfaces = $this->codeCoverage->surfaces();

        return $json
            ? [$this->coverageLines->json($surfaces, $missing)]
            : $this->coverageLines->text($surfaces, $missing);
    }

    /**
     * @param bool $json Whether `--json` was given.
     *
     * @throws WorkflowRefusal When the index or the ledger cannot be read.
     *
     * @return list<string> The made report.
     */
    private function made(bool $json): array
    {
        return $json
            ? [$this->madeCoverage->json()]
            : $this->madeCoverage->text();
    }

    /**
     * @param WorkflowRefusal $workflowRefusal Why it did not answer.
     * @param bool            $json            Whether `--json` was given.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResult The refusal: exit 2 for a line it does not take, 1 otherwise.
     */
    private static function refused(WorkflowRefusal $workflowRefusal, bool $json): CommandResult
    {
        $exit = $workflowRefusal->getCode() === ConsoleArguments::USAGE
            ? 2
            : 1;
        $line = $json
            ? (new ConsoleOutput())->error('code:coverage', $workflowRefusal->getMessage(), $exit)
            : 'code:coverage — ' . $workflowRefusal->getMessage();

        return new CommandResult([$line], $exit);
    }
}
