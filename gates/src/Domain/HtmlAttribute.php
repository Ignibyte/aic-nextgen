<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * One attribute of a start tag as it was written (#170): what the HTML lint
 * reads to judge inline style and script, quoting and spacing.
 */
final readonly class HtmlAttribute
{
    /**
     * @param string      $name         Its name, lower case.
     * @param string|null $value        Its value; null when it has none.
     * @param string      $quote        The quote around the value: `"`, `'`, or empty when unquoted or bare.
     * @param string      $gap          The whitespace written before its name.
     * @param bool        $spacedEquals Whether whitespace is written on either side of its `=`.
     * @param int         $line         The line its name starts on.
     */
    public function __construct(
        public string $name,
        public ?string $value,
        public string $quote,
        public string $gap,
        public bool $spacedEquals,
        public int $line,
    ) {
    }
}
