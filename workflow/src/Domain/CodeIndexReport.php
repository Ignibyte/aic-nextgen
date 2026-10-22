<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

use function sprintf;

/**
 * One refresh of the code index (#106): what it saw and did.
 */
final readonly class CodeIndexReport
{
    /**
     * @param int          $files   The files indexed.
     * @param int          $parsed  The PHP files parsed this time.
     * @param int          $removed The files dropped since the last refresh.
     * @param int          $symbols The symbols the index holds.
     * @param int          $edges   The edges it holds.
     * @param float        $seconds How long it took.
     * @param list<string> $broken  The PHP files that did not parse.
     */
    public function __construct(
        public int $files,
        public int $parsed,
        public int $removed,
        public int $symbols,
        public int $edges,
        public float $seconds,
        public array $broken,
    ) {
    }

    /**
     * @return list<string> Its lines for `code:index`.
     */
    public function lines(): array
    {
        $lines = [sprintf(
            'code index: %d files, %d parsed, %d removed, %d symbols, %d edges, %.2f s.',
            $this->files,
            $this->parsed,
            $this->removed,
            $this->symbols,
            $this->edges,
            $this->seconds,
        )];

        foreach ($this->broken as $file) {
            $lines[] = 'did not parse: ' . $file;
        }

        return $lines;
    }
}
