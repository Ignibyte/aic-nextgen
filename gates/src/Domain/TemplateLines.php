<?php

declare(strict_types=1);

namespace Gates\Domain;

use function count;

/**
 * The parser's cursor over a template's lines, each with its newline.
 */
final class TemplateLines
{
    /**
     * The lines read so far.
     */
    private int $position = 0;

    /**
     * @param string       $name  The template's file name, for messages.
     * @param list<string> $lines The lines, each ending in its newline but perhaps the last.
     */
    public function __construct(
        private readonly string $name,
        private readonly array $lines,
    ) {
    }

    /**
     * @return string|null The next line; null at the end.
     */
    public function next(): ?string
    {
        if ($this->position >= count($this->lines)) {
            return null;
        }

        $this->position += 1;

        return $this->lines[$this->position - 1];
    }

    /**
     * @return string `Name.tpl:N`, the line read last.
     */
    public function where(): string
    {
        return $this->name . ':' . $this->position;
    }
}
