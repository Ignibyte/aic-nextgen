<?php

declare(strict_types=1);

namespace Gates\Domain;

use function array_filter;
use function array_slice;
use function array_values;
use function count;
use function explode;
use function implode;
use function mb_trim;
use function sprintf;
use function str_starts_with;

use const PHP_EOL;

/**
 * What a failed elephc build printed, sorted for a reader (#264): the error lines first, then the
 * warnings. elephc writes every warning as one `warning[<file>:<line>:<col>]: …` line, so any
 * other non-empty line (`error[…]`, `EIR backend error`, the linker) belongs to the failure. An
 * app with more warnings than the cap still shows its error.
 */
final readonly class CompilerOutput
{
    /**
     * How many error lines the report shows before it counts the rest.
     */
    private const int ERROR_CAP = 40;

    /**
     * How many warnings the report shows before it counts the rest.
     */
    private const int WARNING_CAP = 12;

    /**
     * The line prefix elephc gives every warning.
     */
    private const string WARNING = 'warning[';

    /**
     * @param string $stdout What the build wrote to stdout.
     * @param string $stderr What the build wrote to stderr.
     */
    public function __construct(
        private string $stdout,
        private string $stderr,
    ) {
    }

    /**
     * @return string The error lines, then the first warnings, each block capped with a count of what it left out.
     */
    public function report(): string
    {
        $lines = array_values(array_filter(
            explode("\n", $this->stdout . "\n" . $this->stderr),
            static fn (string $line): bool => mb_trim($line) !== '',
        ));
        $errors = array_values(array_filter($lines, static fn (string $line): bool => !str_starts_with($line, self::WARNING)));
        $warnings = array_values(array_filter($lines, static fn (string $line): bool => str_starts_with($line, self::WARNING)));

        if ($lines === []) {
            return 'the compiler failed with no output';
        }

        return implode(PHP_EOL, [
            ...$this->capped($errors, self::ERROR_CAP, '… %d more lines'),
            ...$this->capped($warnings, self::WARNING_CAP, '… %d more warnings'),
        ]);
    }

    /**
     * @param list<string> $lines  The lines of one kind.
     * @param int          $cap    How many to show.
     * @param string       $format The line that counts the rest, the count in place of `%d`.
     *
     * @return list<string> The first lines, and the count line when some were left out.
     */
    private function capped(array $lines, int $cap, string $format): array
    {
        $shown = array_slice($lines, 0, $cap);

        if (count($lines) <= $cap) {
            return $shown;
        }

        return [...$shown, sprintf($format, count($lines) - $cap)];
    }
}
