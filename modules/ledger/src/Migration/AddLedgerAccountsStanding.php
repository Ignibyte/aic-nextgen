<?php

declare(strict_types=1);

namespace App\Ledger\Migration;

use AIC\Schema\Contracts\Migration;
use AIC\Schema\Contracts\Schema;
use InvalidArgumentException;
use Override;

/**
 * The `standing` state of `ledger_accounts`, made by bin/aic make:state (#308): a
 * choice of its states, every row, old or new, starting in `open`.
 */
final readonly class AddLedgerAccountsStanding implements Migration
{
    /**
     * @return string The version the ledger records it under.
     */
    #[Override]
    public function version(): string
    {
        return '2026_11_18_065304_add_ledger_accounts_standing';
    }

    /**
     * @param Schema $schema Where the migration describes its tables.
     *
     * @throws InvalidArgumentException When the column is declared wrongly.
     */
    #[Override]
    public function describe(Schema $schema): void
    {
        $columnTypes = $schema->types();
        $choice = $columnTypes->choice(['open', 'frozen', 'closed']);
        $term = $schema->expressions()->text('open');
        $schema->alter('ledger_accounts')->addColumn('standing', $columnTypes->defaulted($choice, $term));
    }
}
