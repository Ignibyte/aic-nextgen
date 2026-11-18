<?php

declare(strict_types=1);

namespace App\Ledger\Service;

use JsonSerializable;
use Override;

/**
 * The ledger's Api bodies, first made by bin/aic make:api (#122): a list of
 * lines under one name, each step of a round trip and what came of it
 * (#310, #309), or the notices a job wrote.
 */
final readonly class LedgerLines implements JsonSerializable
{
    /**
     * @param string       $name  What the lines are, the JSON object's one key.
     * @param list<string> $lines The lines, in order.
     */
    public function __construct(
        private string $name,
        private array $lines,
    ) {
    }

    /**
     * @return array<string, list<string>> The JSON object.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return [$this->name => $this->lines];
    }
}
