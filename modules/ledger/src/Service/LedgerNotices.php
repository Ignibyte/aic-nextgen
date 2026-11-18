<?php

declare(strict_types=1);

namespace App\Ledger\Service;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityNotFoundException;
use AIC\Entity\Application\EntityStore;
use App\Ledger\Entity\LedgerTypes;
use InvalidArgumentException;

/**
 * GET /ledger/notices' work (#309): the notices the account listener's job
 * wrote, oldest first, each deleted once read, so the next pass starts empty.
 */
final readonly class LedgerNotices
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
     * @throws InvalidArgumentException When the query is declared wrongly.
     * @throws EntityNotFoundException  When a notice is gone before it is deleted.
     * @throws QueryException           When a query or a delete fails.
     *
     * @return LedgerLines The notices.
     */
    public function body(): LedgerLines
    {
        $notices = $this->ledgerTypes->notices();
        $lines = [];

        foreach ($this->entityStore->query($notices)->orderBy('id')->rows() as $row) {
            $lines[] = $row->string('line');
            $this->entityStore->delete($notices, $row->int('id'));
        }

        return new LedgerLines('notices', $lines);
    }
}
