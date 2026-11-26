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

use function implode;

/**
 * A fixture MCP resource read through the ledger's store (#334): the names of
 * the categories an entry may be filed under.
 */
#[ResourceInfo(
    uri: 'ledger://categories',
    name: 'categories',
    description: 'The categories a ledger entry may be filed under, by name.',
    needs: 'ledger.mcp',
    mimeType: 'text/plain',
)]
final readonly class LedgerCategoriesResource implements McpResource
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
     * @throws InvalidArgumentException When the categories have no name field.
     *
     * @return string The names, one a line, in the names' order; empty for none.
     */
    #[Override]
    public function read(): string
    {
        return implode("\n", $this->entityStore->query($this->ledgerTypes->categories())->totals()->values('name'));
    }
}
