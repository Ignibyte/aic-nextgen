<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use function array_any;
use function explode;
use function in_array;
use function rtrim;
use function trim;

/**
 * The `.gitignore` line that keeps the workflow's state out of git (#105):
 * `/var/`, where runs, evidence and the code index live. Any spelling git
 * reads the same way counts as present.
 */
final readonly class IgnoreLine
{
    /**
     * The line init adds.
     */
    public const string LINE = '/var/';

    /**
     * The spellings that ignore the same directory.
     */
    private const array SPELLINGS = ['/var/', 'var/', '/var', 'var', '/var/*', 'var/*'];

    /**
     * @param string|null $text `.gitignore`'s text; null when there is none.
     *
     * @return bool Whether it ignores `var/` already.
     */
    public function present(?string $text): bool
    {
        return array_any(explode("\n", (string) $text), static fn (string $line): bool => in_array(trim($line), self::SPELLINGS, true));
    }

    /**
     * @param string|null $text `.gitignore`'s text; null when there is none.
     *
     * @return string The text with the line appended under a comment naming it.
     */
    public function add(?string $text): string
    {
        $kept = rtrim((string) $text);

        $comment = "# The workflow's runs, evidence and code index (ignibyte/aic-workflow)\n";

        return ($kept === '' ? '' : $kept . "\n\n") . $comment . self::LINE . "\n";
    }
}
