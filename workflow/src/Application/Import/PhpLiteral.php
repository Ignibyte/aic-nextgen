<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use function str_contains;
use function str_replace;
use function strpbrk;
use function substr;

/**
 * A string as the PHP literal a gate-clean file holds (#318): single quotes, or double quotes for text with an
 * apostrophe and nothing PHP would read inside them, as Rector prefers.
 */
final readonly class PhpLiteral
{
    /**
     * @param string $text The text.
     *
     * @return string Its literal.
     */
    public function string(string $text): string
    {
        if (str_contains($text, "'") && strpbrk($text, '"$\\') === false) {
            return '"' . $text . '"';
        }

        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $text) . "'";
    }

    /**
     * @param string $php A literal this class wrote.
     *
     * @return string The text it holds.
     */
    public function text(string $php): string
    {
        $inner = substr($php, 1, -1);

        return $php[0] === '"' ? $inner : str_replace(["\\'", '\\\\'], ["'", '\\'], $inner);
    }
}
