<?php

declare(strict_types=1);

namespace Gates\Domain;

use function count;
use function substr;

/**
 * The parser's cursor over a template's lines, each with its newline, and
 * what it counts on the way: the components met, for their keys, and the
 * loops open around the line.
 */
final class TemplateLines
{
    /**
     * The lines read so far.
     */
    private int $position = 0;

    /**
     * The components met so far.
     */
    private int $components = 0;

    /**
     * The loops open around the line read last.
     */
    private int $loops = 0;

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

    /**
     * Opens a loop.
     */
    public function enter(): void
    {
        $this->loops += 1;
    }

    /**
     * Closes the innermost loop.
     */
    public function leave(): void
    {
        $this->loops -= 1;
    }

    /**
     * @return bool Whether the line read last sits inside a loop.
     */
    public function inLoop(): bool
    {
        return $this->loops > 0;
    }

    /**
     * Counts a component met.
     *
     * @return string Its compile-time key: the template's name without `.tpl`, `.`, and its occurrence from 1,
     *                in Tessera's key grammar (#70).
     */
    public function key(): string
    {
        $this->components += 1;

        return substr($this->name, 0, -4) . '.' . $this->components;
    }
}
