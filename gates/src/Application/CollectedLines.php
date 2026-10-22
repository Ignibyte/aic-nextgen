<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Controller\Contracts\CommandWriter;
use Override;

/**
 * A command's output, kept in memory (#122), so gate:31 can show what a make
 * said when it did not exit 0.
 */
final class CollectedLines implements CommandWriter
{
    /**
     * @var list<string> The lines so far.
     */
    private array $lines = [];

    /**
     * @param string $text One line.
     */
    #[Override]
    public function line(string $text): void
    {
        $this->lines[] = $text;
    }

    /**
     * @return list<string> The lines, in order.
     */
    public function lines(): array
    {
        return $this->lines;
    }
}
