<?php

declare(strict_types=1);

namespace Gates\Domain;

/** One piece of markup gate:32 found under `modules/` (#156). */
final readonly class MarkupFinding
{
    /**
     * @param string $path What holds it, relative to the root.
     * @param int    $line Its line; 0 for the file as a whole.
     * @param string $what What it is, such as `a string holds the HTML tag "<li"`.
     */
    public function __construct(
        public string $path,
        public int $line,
        public string $what,
    ) {
    }

    /**
     * @return string The finding as gate:32 reports it, with what to do instead.
     */
    public function message(): string
    {
        return $this->path . ($this->line > 0 ? ':' . $this->line : '') . ': ' . $this->what
            . '; a module holds no markup: build a component under themes/<name>/components/ and hand it to the Renderer.';
    }
}
