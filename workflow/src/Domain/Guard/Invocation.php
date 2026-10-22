<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Guard;

use function array_slice;
use function in_array;
use function strrpos;
use function substr;

/**
 * One simple command of a shell line (#102): its words as the shell would
 * pass them, which of them it could not know (a substitution or a variable),
 * the files its redirects write, and the body a heredoc feeds it.
 */
final readonly class Invocation
{
    /**
     * @param list<string> $words   The words, quotes and escapes resolved, wrappers (`env`, `sudo`, …) removed.
     * @param list<bool>   $dynamic For each word, whether it holds `$(`, a backtick, `${` or `$NAME`.
     * @param list<string> $writes  The targets of its `>`, `>>`, `&>` and `>|` redirects.
     * @param string       $heredoc The body a heredoc feeds it; empty when none does.
     */
    public function __construct(
        public array $words,
        public array $dynamic,
        public array $writes,
        public string $heredoc = '',
    ) {
    }

    /**
     * @return string Its program as written, without a directory: `aic` for `bin/aic`; empty for a bare redirect.
     */
    public function program(): string
    {
        $first = $this->words[0] ?? '';
        $slash = strrpos($first, '/');

        return $slash === false
            ? $first
            : substr($first, $slash + 1);
    }

    /**
     * @return list<string> Its words after the program.
     */
    public function arguments(): array
    {
        return array_slice($this->words, 1);
    }

    /**
     * @return bool Whether any word holds what the shell fills in at run time.
     */
    public function anyDynamic(): bool
    {
        return in_array(true, $this->dynamic, true);
    }
}
