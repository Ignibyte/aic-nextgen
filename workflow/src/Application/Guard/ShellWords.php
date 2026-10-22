<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Guard;

use AIC\Workflow\Domain\Guard\Invocation;

use function array_slice;
use function in_array;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function preg_replace_callback;
use function str_starts_with;

use const PREG_SET_ORDER;

/**
 * A shell line as the simple commands it runs (#102): each with its words
 * resolved, its write redirects and its heredoc. Command substitutions, the
 * string of `bash -c`/`sh -c`, and a heredoc fed to a shell are read as
 * commands too. Wrappers (`env`, `sudo`, `nice`, `timeout`, `nohup`,
 * `command`, `exec`, `xargs`) and leading assignments come off. It is a
 * speed bump, not a wall (Droost's words): a path assembled from parts gets
 * through, and the ledger records the call.
 */
final readonly class ShellWords
{
    /**
     * Programs that run what follows them.
     */
    private const array WRAPPERS = ['env', 'sudo', 'nice', 'timeout', 'nohup', 'command', 'exec', 'xargs', 'time', 'stdbuf', 'ionice'];

    /**
     * Shells whose `-c` string and heredoc are commands.
     */
    private const array SHELLS = ['bash', 'sh', 'zsh', 'dash'];

    /**
     * @param ShellTokens $shellTokens Cuts the line.
     */
    public function __construct(
        private ShellTokens $shellTokens = new ShellTokens(),
    ) {
    }

    /**
     * @param string $line  A shell line.
     * @param int    $depth How deep in substitutions it is; past 8 nothing more is read.
     *
     * @return list<Invocation> Its simple commands, nested ones after the one holding them.
     */
    public function parse(string $line, int $depth = 0): array
    {
        if ($depth > 8) {
            return [];
        }

        [$tokens, $bodies] = $this->shellTokens->cut($line);
        $invocations = [];
        $draft = new Draft();

        foreach ($tokens as [$kind, $text]) {
            [$draft, $done] = $draft->take($kind, $text, $bodies);
            $invocations = [...$invocations, ...$this->finished($done, $depth)];
        }

        return [...$invocations, ...$this->finished([$draft], $depth)];
    }

    /**
     * @param list<Draft> $drafts Commands the line finished.
     * @param int         $depth  The depth.
     *
     * @return list<Invocation> Each as an invocation, with what it runs inside it.
     */
    private function finished(array $drafts, int $depth): array
    {
        $invocations = [];

        foreach ($drafts as $draft) {
            $invocations = [...$invocations, ...$this->expanded($draft, $depth)];
        }

        return $invocations;
    }

    /**
     * @param Draft $draft A finished command.
     * @param int   $depth The depth.
     *
     * @return list<Invocation> It, then the commands its substitutions and a shell's script run; only those when it is empty.
     */
    private function expanded(Draft $draft, int $depth): array
    {
        $invocation = $this->invocation($draft);
        $nested = [];

        foreach ($draft->raws as $raw) {
            $nested = [...$nested, ...$this->substitutions($raw, $depth)];
        }

        return $invocation->words === [] && $invocation->writes === []
            ? $nested
            : [$invocation, ...$nested, ...$this->inner($invocation, $depth)];
    }

    /**
     * @param Draft $draft A finished command.
     *
     * @return Invocation It with its words resolved and its wrappers off.
     */
    private function invocation(Draft $draft): Invocation
    {
        $words = [];
        $dynamic = [];

        foreach ($draft->raws as $raw) {
            $words[] = $this->literal($raw);
            $dynamic[] = $this->shellTokens->dynamic($raw);
        }

        [$words, $dynamic] = $this->unwrapped($words, $dynamic);
        $writes = [];

        foreach ($draft->writes as $raw) {
            $writes[] = $this->literal($raw);
        }

        return new Invocation($words, $dynamic, $writes, $draft->heredoc);
    }

    /**
     * @param list<string> $words   The words.
     * @param list<bool>   $dynamic Which are dynamic.
     *
     * @return array{list<string>, list<bool>} Them without leading assignments and wrappers.
     */
    private function unwrapped(array $words, array $dynamic): array
    {
        while ($words !== [] && $this->strippable($words[0])) {
            [$words, $dynamic] = $this->unwrapOne($words, $dynamic);
        }

        return [$words, $dynamic];
    }

    /**
     * @param non-empty-list<string> $words   The words, led by an assignment or a wrapper.
     * @param list<bool>             $dynamic Which are dynamic.
     *
     * @return array{list<string>, list<bool>} Them without it, and without a wrapper's options.
     */
    private function unwrapOne(array $words, array $dynamic): array
    {
        $first = $words[0];
        $rest = [array_slice($words, 1), array_slice($dynamic, 1)];

        return in_array($first, self::WRAPPERS, true)
            ? $this->options($first, $rest[0], $rest[1])
            : $rest;
    }

    /**
     * @param string $word A leading word.
     *
     * @return bool Whether it is an assignment or a wrapper.
     */
    private function strippable(string $word): bool
    {
        return in_array($word, self::WRAPPERS, true) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*=/', $word) === 1;
    }

    /**
     * @param string       $wrapper The wrapper just taken off.
     * @param list<string> $words   The words after it.
     * @param list<bool>   $dynamic Which are dynamic.
     *
     * @return array{list<string>, list<bool>} The words after its options, and `timeout`'s duration.
     */
    private function options(string $wrapper, array $words, array $dynamic): array
    {
        $skip = 0;

        while (str_starts_with($words[$skip] ?? '', '-')) {
            $skip += in_array($words[$skip], ['-n', '-u', '-g', '-s', '-k', '-c', '-C', '-I'], true)
                ? 2
                : 1;
        }

        $skip += $wrapper === 'timeout'
            ? 1
            : 0;

        return [array_slice($words, $skip), array_slice($dynamic, $skip)];
    }

    /**
     * @param Invocation $invocation A command.
     * @param int        $depth      The depth.
     *
     * @return list<Invocation> What it runs: a shell's `-c` string or heredoc.
     */
    private function inner(Invocation $invocation, int $depth): array
    {
        if (!in_array($invocation->program(), self::SHELLS, true)) {
            return [];
        }

        $script = $invocation->heredoc;
        $words = $invocation->arguments();

        foreach ($words as $index => $word) {
            $script = preg_match('/^-[a-z]*c[a-z]*$/', $word) === 1
                ? $words[$index + 1] ?? $script
                : $script;
        }

        return $script === ''
            ? []
            : $this->parse($script, $depth + 1);
    }

    /**
     * @param string $raw   A word as written.
     * @param int    $depth The depth.
     *
     * @return list<Invocation> The commands its `$( )` and backtick substitutions run.
     */
    private function substitutions(string $raw, int $depth): array
    {
        $outside = (string) preg_replace("/'[^']*'/", '', $raw);
        preg_match_all('/\\$\\((?<sub>(?:[^()]|\\((?&sub)\\))*)\\)|`((?:\\\\.|[^`\\\\])*)`/', $outside, $matches, PREG_SET_ORDER);
        $invocations = [];

        foreach ($matches as $match) {
            $invocations = [...$invocations, ...$this->parse(($match['sub'] ?? '') . ($match[2] ?? ''), $depth + 1)];
        }

        return $invocations;
    }

    /**
     * @param string $raw A word as written.
     *
     * @return string It as the program receives it: quotes off, escapes resolved.
     */
    private function literal(string $raw): string
    {
        return (string) preg_replace_callback(
            '/\'([^\']*)\'|"((?:\\\\.|[^"\\\\])*)"|\\\\(.)/s',
            static fn (array $match): string => match (true) {
                str_starts_with($match[0], "'") => $match[1],
                str_starts_with($match[0], '"') => (string) preg_replace('/\\\\([\\\\"$`\\n])/', '$1', $match[2] ?? ''),
                default => $match[3] ?? '',
            },
            $raw,
        );
    }
}
