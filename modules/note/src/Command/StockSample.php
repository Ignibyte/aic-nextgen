<?php

declare(strict_types=1);

namespace App\Note\Command;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use App\Note\Form\StockEntry;
use App\Note\Repository\StockRepository;
use InvalidArgumentException;
use Override;

/**
 * `bin/aic stock:sample` (#184): clears the sold-out items that have a SKU,
 * then adds the three sample items unless an item already holds the SKU, so
 * a rerun adds nothing.
 */
final readonly class StockSample implements Command
{
    /**
     * @param StockRepository $stockRepository The stock items.
     */
    public function __construct(
        private StockRepository $stockRepository,
    ) {
    }

    /**
     * @param CommandInput $commandInput Unused: the samples are fixed.
     *
     * @throws QueryException           When a write fails.
     * @throws InvalidArgumentException When a constructed value is invalid.
     *
     * @return CommandResponse How many items it removed and added.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        [$removed, $added] = $this->stockRepository->sample([
            new StockEntry('Brass desk lamp', '89.00', '2026-10-01', true, 'LAMP-BRASS', '4'),
            new StockEntry('Oak side table', '240.00', '2026-10-02', false, 'TABLE-OAK', '2'),
            new StockEntry('Linen throw', '35.50', '2026-10-03', true, 'THROW-LINEN', '12'),
        ]);

        return new CommandResult(['stock:sample: removed ' . $removed . ' sold-out, added ' . $added . '.'], 0);
    }
}
