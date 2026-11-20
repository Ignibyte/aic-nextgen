<?php

declare(strict_types=1);

namespace App\Scaffold\Command;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use App\Scaffold\Repository\ScaffoldShelvesSeedRepository;
use InvalidArgumentException;
use Override;

use function count;

/**
 * `bin/aic scaffold-shelves:seed`, made by bin/aic make:seed (#320).
 *
 * It saves the rows below into `scaffold_shelves` through
 * ScaffoldShelvesSeedRepository; run again, it updates the same rows,
 * matched by `name`. A seed writes only through repositories (gate:8
 * `aic.seedWrites`). It takes no arguments.
 */
final readonly class ScaffoldShelvesSeed implements Command
{
    /**
     * The rows it saves, each by column: replace them with the module's own.
     */
    private const array ROWS = [
        [
            'capacity' => 1,
            'name' => 'name one',
            'zone' => 'front',
        ],
    ];

    /**
     * @param ScaffoldShelvesSeedRepository $scaffoldShelvesSeedRepository Where the rows are saved.
     */
    public function __construct(
        private ScaffoldShelvesSeedRepository $scaffoldShelvesSeedRepository,
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
            return new CommandResult(['usage: bin/aic scaffold-shelves:seed'], 2);
        }

        foreach (self::ROWS as $row) {
            $this->scaffoldShelvesSeedRepository->save($row);
        }

        return new CommandResult([count(self::ROWS) . ' scaffold_shelves rows saved.'], 0);
    }
}
