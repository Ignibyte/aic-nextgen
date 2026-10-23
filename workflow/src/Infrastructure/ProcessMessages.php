<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use function preg_match;
use function str_starts_with;
use function substr;

/** Reads the private helper protocol, separate from command output. */
final class ProcessMessages
{
    private string $buffer = '';

    /** @param string $bytes Newly read control bytes. */
    public function append(string $bytes): void
    {
        $this->buffer .= $bytes;
    }

    /** @return bool Whether session creation succeeded and the helper is waiting for ACK. */
    public function ready(): bool
    {
        return str_starts_with($this->buffer, "R\n");
    }

    /** @return int|null The actual child exit, only after its terminal RESULT arrives. */
    public function result(): ?int
    {
        if (preg_match('/\\AR\\nE-?[0-9]+\\n\\z/', $this->buffer) !== 1) {
            return null;
        }

        return (int) substr($this->buffer, 3, -1);
    }
}
