<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Console;

/**
 * What an inspect command answered (#109): its text lines, its data already
 * encoded as JSON for the envelope, whether it found what was asked, and the
 * files and scopes the answer showed, for the run's `code.queried` record.
 */
final readonly class InspectAnswer
{
    /**
     * @param list<string> $lines  The text answer, a header line first; one line that says so when nothing matched.
     * @param string       $data   The answer's data as one JSON value, keys in order.
     * @param bool         $found  Whether it found what was asked.
     * @param list<string> $files  The project files the answer showed.
     * @param list<string> $scopes The areas it outlined, such as `module:note`.
     */
    public function __construct(
        public array $lines,
        public string $data,
        public bool $found = true,
        public array $files = [],
        public array $scopes = [],
    ) {
    }
}
