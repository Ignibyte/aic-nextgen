<?php

declare(strict_types=1);

namespace App\Ledger\Mcp;

use AIC\Mcp\Attribute\ResourceInfo;
use AIC\Mcp\Contracts\McpResource;
use App\Ledger\Enum\LedgerCurrency;
use Override;

/**
 * The fixture's MCP resource (#316): the currencies `ledger.split` takes, as a
 * Markdown list.
 */
#[ResourceInfo(
    uri: 'ledger://currencies',
    name: 'currencies',
    description: 'The currencies the ledger splits amounts in.',
    needs: 'ledger.mcp',
    mimeType: 'text/markdown',
)]
final readonly class LedgerCurrenciesResource implements McpResource
{
    /**
     * @return string One line per currency, by its ISO code.
     */
    #[Override]
    public function read(): string
    {
        $lines = "# Currencies\n\n";

        foreach ([LedgerCurrency::Usd, LedgerCurrency::Eur, LedgerCurrency::Gbp] as $ledgerCurrency) {
            $lines .= '- ' . $ledgerCurrency->value . "\n";
        }

        return $lines;
    }
}
