<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * One lookup the run recorded as `code.queried` (#106, #118): the command,
 * what it was asked, whether it answered, the files whose declarations the
 * answer printed, the areas it outlined, and whether its file list was cut.
 */
final readonly class CodeQuery
{
    /**
     * @param string       $command   `code:tree`, `code:search`, `code:refs` or an inspect command.
     * @param string       $target    What it was asked about; empty for the overview.
     * @param bool         $answered  Whether its outcome was `ok`.
     * @param list<string> $files     The files the answer printed declarations of.
     * @param list<string> $scopes    The areas it outlined: `*`, `module:<m>`, `system:<S>`, `path:<p>`.
     * @param bool         $truncated Whether `files` was cut at its cap.
     */
    public function __construct(
        public string $command,
        public string $target,
        public bool $answered,
        public array $files,
        public array $scopes,
        public bool $truncated,
    ) {
    }
}
