<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\UtcTime;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Exception\WorkflowRefusal;

use function str_replace;

/**
 * A migration's version for `make:migration` and `make:entity` (#122, #123): `--version` when given, else now in UTC
 * and the migration's snake-case name.
 */
final readonly class MakeVersions
{
    /**
     * @param UtcTime   $utcTime   Now, for a version not given.
     * @param MakeNames $makeNames The version rule.
     */
    public function __construct(
        private UtcTime $utcTime,
        private MakeNames $makeNames = new MakeNames(),
    ) {
    }

    /**
     * @param ParsedArguments $parsedArguments The line, maybe with `--version`.
     * @param string          $name            The migration's snake-case name, such as `create_notes_table`.
     *
     * @throws WorkflowRefusal When the version breaks the rule.
     *
     * @return string The version the ledger records.
     */
    public function version(ParsedArguments $parsedArguments, string $name): string
    {
        $given = $parsedArguments->flag('version');

        return $this->makeNames->version($given === '' ? $this->now() . '_' . $name : $given);
    }

    /**
     * @return string Now in UTC as a version's start, `YYYY_MM_DD_HHMMSS`.
     */
    private function now(): string
    {
        return str_replace(['-', 'T', ':', 'Z'], ['_', '_', '', ''], $this->utcTime->now());
    }
}
