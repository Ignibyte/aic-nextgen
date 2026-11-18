<?php

declare(strict_types=1);

namespace App\Ledger\Entity;

/**
 * The states of `ledger_accounts.standing`, made by bin/aic make:state (#308): the
 * values its Choice field holds, the first where every new row starts.
 */
enum LedgerAccountStanding: string
{
    case Open = 'open';

    case Frozen = 'frozen';

    case Closed = 'closed';
}
