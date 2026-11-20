<?php

declare(strict_types=1);

namespace {namespace}\Command;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use {namespace}\Repository\{repository};
use InvalidArgumentException;
use Override;

use function count;

/**
 * `bin/aic {name}`, made by bin/aic make:seed (#320).
 *
 * It saves the rows below into `{table}` through
 * {repository}; run again, it updates the same rows,
 * matched by `{unique}`. A seed writes only through repositories (gate:8
 * `aic.seedWrites`). It takes no arguments.
 */
final readonly class {class} implements Command
{
    /**
     * The rows it saves, each by column: replace them with the module's own.
     */
    private const array ROWS = [
{rows}
    ];

    /**
     * @param {repository} ${variable} Where the rows are saved.
     */
    public function __construct(
        private {repository} ${variable},
    ) {
    }

    /**
     * @param CommandInput $commandInput Its arguments, which must be none.
     *
     * @throws QueryException           When a row cannot be saved.
     * @throws InvalidArgumentException When a value does not fit its field, or the result's exit code is invalid.
     *
     * @return CommandResponse How many rows were saved, or the usage.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        if (!$commandInput->isEmpty()) {
            return new CommandResult(['usage: bin/aic {name}'], 2);
        }

        foreach (self::ROWS as $row) {
            $this->{variable}->save($row);
        }

        return new CommandResult([count(self::ROWS) . ' {table} rows saved.'], 0);
    }
}
