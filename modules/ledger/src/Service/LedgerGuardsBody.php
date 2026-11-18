<?php

declare(strict_types=1);

namespace App\Ledger\Service;

use JsonSerializable;
use Override;

/**
 * The body of LedgerGuardsApi, made by bin/aic make:api (#122): each step of
 * the guards' round trip and what came of it (#310).
 */
final readonly class LedgerGuardsBody implements JsonSerializable
{
    /**
     * @param list<string> $steps Each step and its outcome, in order.
     */
    public function __construct(
        private array $steps,
    ) {
    }

    /**
     * @return array<string, list<string>> The JSON object.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['steps' => $this->steps];
    }
}
