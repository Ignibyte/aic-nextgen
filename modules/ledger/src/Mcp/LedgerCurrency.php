<?php

declare(strict_types=1);

namespace App\Ledger\Mcp;

/**
 * A currency the ledger's MCP tool splits amounts in (#316), by its ISO code.
 */
enum LedgerCurrency: string
{
    case Usd = 'usd';
    case Eur = 'eur';
    case Gbp = 'gbp';
}
