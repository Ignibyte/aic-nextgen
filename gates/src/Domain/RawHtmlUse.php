<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * One place that names RawHtml (gate:27, #99): a `new RawHtml`, or a
 * `RawHtml::` reference, with the class around it.
 */
final readonly class RawHtmlUse
{
    /**
     * @param string $path  The file, relative to the root.
     * @param int    $line  The line.
     * @param string $class The class it is in, fully qualified; empty outside a class.
     */
    public function __construct(
        public string $path,
        public int $line,
        public string $class,
    ) {
    }

    /**
     * @return string `path:line`.
     */
    public function place(): string
    {
        return $this->path . ':' . $this->line;
    }
}
