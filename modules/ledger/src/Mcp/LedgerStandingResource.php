<?php

declare(strict_types=1);

namespace App\Ledger\Mcp;

use AIC\Entity\Domain\StateMachine;
use AIC\Mcp\Attribute\ResourceInfo;
use AIC\Mcp\Contracts\McpResource;
use App\Ledger\Entity\LedgerTypes;
use InvalidArgumentException;
use Override;

use function implode;

/**
 * A fixture MCP resource over the ledger's declarations (#334): the standings
 * an account moves through and the transitions between them, read from its
 * entity type's state machine, so it reads the same on every request.
 */
#[ResourceInfo(
    uri: 'ledger://standing',
    name: 'standing',
    description: 'The standings a ledger account moves through and what moves it.',
    needs: 'ledger.mcp',
    mimeType: 'text/markdown',
)]
final readonly class LedgerStandingResource implements McpResource
{
    /**
     * The account's transitions, in the order the machine declares them.
     */
    private const array TRANSITIONS = ['freeze', 'thaw', 'close'];

    /**
     * @param LedgerTypes $ledgerTypes The ledger's entity types.
     */
    public function __construct(
        private LedgerTypes $ledgerTypes,
    ) {
    }

    /**
     * @throws InvalidArgumentException When the machine lacks a transition this lists.
     *
     * @return string The standings, then one line per transition: its name, where from and where to.
     */
    #[Override]
    public function read(): string
    {
        $stateMachine = $this->ledgerTypes->accounts()->stateMachine;

        if (!$stateMachine instanceof StateMachine) {
            return "# Standing\n\nAccounts have no standing.\n";
        }

        $text = "# Standing\n\n" . implode(', ', $stateMachine->states) . "\n\n";

        foreach (self::TRANSITIONS as $name) {
            $transition = $stateMachine->transition($name);
            $text .= '- ' . $transition->name . ': ' . implode(' or ', $transition->from) . ' to ' . $transition->into . "\n";
        }

        return $text;
    }
}
