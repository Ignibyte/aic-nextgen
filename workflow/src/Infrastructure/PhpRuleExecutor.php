<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\ProcessRunner;
use AIC\Workflow\Application\RuleExecutor;
use AIC\Workflow\Domain\HouseRule;
use AIC\Workflow\Domain\ProcessResult;
use Override;

use function is_file;

use const PHP_BINARY;

/**
 * Runs a house rule (#104): `PHP_BINARY <file>` in the project, its input on
 * stdin, stopped after its timeout. A rule file that is not there reads as
 * exit 127 without starting PHP.
 */
final readonly class PhpRuleExecutor implements RuleExecutor
{
    /**
     * @param string        $root          The project's root.
     * @param ProcessRunner $processRunner Runs PHP.
     */
    public function __construct(
        private string $root,
        private ProcessRunner $processRunner,
    ) {
    }

    /**
     * @param HouseRule $houseRule The rule.
     * @param string    $input     Its input.
     *
     * @return ProcessResult Its exit and output.
     */
    #[Override]
    public function run(HouseRule $houseRule, string $input): ProcessResult
    {
        $file = $this->root . '/' . $houseRule->run;

        return is_file($file)
            ? $this->processRunner->run([PHP_BINARY, $file], $houseRule->timeout, $input)
            : new ProcessResult(127, $houseRule->run . ' is not there.');
    }
}
