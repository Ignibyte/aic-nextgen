<?php

declare(strict_types=1);

namespace App\Ledger\Mcp;

use AIC\Database\Contracts\QueryException;
use AIC\Entity\Application\EntityStore;
use AIC\Mcp\Attribute\ResourceInfo;
use AIC\Mcp\Contracts\McpResource;
use App\Ledger\Entity\LedgerTypes;
use InvalidArgumentException;
use Override;

/**
 * A fixture MCP resource read through the ledger's store (#334): how many live
 * accounts stand in each standing, as a Markdown list. It takes services, so
 * its module's catalog is built from the context.
 */
#[ResourceInfo(
    uri: 'ledger://accounts',
    name: 'accounts',
    description: 'How many live ledger accounts are open, frozen or closed.',
    needs: 'ledger.mcp',
    mimeType: 'text/markdown',
)]
final readonly class LedgerAccountsResource implements McpResource
{
    /**
     * @param EntityStore $entityStore The rows.
     * @param LedgerTypes $ledgerTypes The ledger's entity types.
     */
    public function __construct(
        private EntityStore $entityStore,
        private LedgerTypes $ledgerTypes,
    ) {
    }

    /**
     * @throws QueryException           When the read fails.
     * @throws InvalidArgumentException When the accounts have no standing field.
     *
     * @return string One line per standing with its count, most accounts first.
     */
    #[Override]
    public function read(): string
    {
        $counts = $this->entityStore->query($this->ledgerTypes->accounts())->totals()->countBy('standing');
        $text = "# Accounts by standing\n\n";

        foreach ($counts as $standing => $count) {
            $text .= '- ' . $standing . ': ' . $count . "\n";
        }

        return $text;
    }
}
