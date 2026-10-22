<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Guard;

use AIC\Workflow\Domain\Guard\Invocation;

use function array_any;
use function array_filter;
use function array_slice;
use function array_values;
use function count;
use function in_array;
use function preg_match;
use function str_contains;
use function str_starts_with;
use function substr;

/**
 * What a simple command writes and names (#102, Droost's
 * `shell_phase_guard` and `protected_path_shell_guard` trimmed): its write
 * targets (redirects, `tee`, `cp`/`mv`/`install`/`ln` destinations, `touch`,
 * `truncate`, `rm`, `mkdir`, `sed -i`, `dd of=`), whether it removes or
 * re-owns what it names, and whether it only reads.
 */
final readonly class ShellWrites
{
    /**
     * Programs that only read what they name (their redirects are still writes).
     */
    private const array READERS = [
        'cat', 'less', 'more', 'head', 'tail', 'grep', 'egrep', 'fgrep', 'rg', 'ag', 'ls', 'wc', 'stat', 'file', 'diff', 'cmp',
        'md5sum', 'sha1sum', 'sha256sum', 'jq', 'sort', 'uniq', 'cut', 'tree', 'du', 'df', 'readlink', 'realpath', 'basename',
        'dirname', 'echo', 'printf', 'test', '[', 'true', 'false', 'pwd', 'which', 'type', 'cd', 'nl', 'od', 'xxd', 'hexdump',
        ':', 'ack', 'md5', 'shasum',
    ];

    /**
     * The git subcommands that only read (#117, Droost's `isReader` without `add` and `commit`).
     */
    private const array GIT_READS = [
        'status', 'diff', 'log', 'show', 'blame', 'grep', 'check-ignore', 'check-attr', 'ls-files', 'ls-tree', 'rev-parse', 'cat-file',
        'describe', 'rev-list', 'merge-base',
    ];

    /**
     * The git options before the subcommand that change nothing it writes; `-C` takes the next word.
     */
    private const array GIT_GLOBALS = ['--no-pager', '-P', '--no-optional-locks'];

    /**
     * A sed script that only prints a line or a range: `1p`, `1,20p`, `$p`.
     */
    private const string SED_PRINT = '/^(\\d+|\\$)(,(\\d+|\\$))?p$/';

    /**
     * Programs whose every named path is a write target.
     */
    private const array WRITERS = ['tee', 'touch', 'truncate', 'rm', 'rmdir', 'mkdir', 'shred', 'unlink'];

    /**
     * Programs that write their last path: the destination.
     */
    private const array COPIERS = ['cp', 'mv', 'install', 'ln', 'rsync'];

    /**
     * Programs that remove, move or re-own what they name.
     */
    private const array DESTROYERS = ['rm', 'rmdir', 'mv', 'chmod', 'chown', 'chgrp', 'ln', 'shred', 'unlink'];

    /**
     * @param Invocation $invocation A command.
     *
     * @return bool Whether it only reads what it names.
     */
    public function reads(Invocation $invocation): bool
    {
        $program = $invocation->program();

        return match ($program) {
            'find' => !$this->findWrites($invocation),
            'git' => $this->gitReads($invocation),
            'sed' => $this->sedPrints($invocation),
            default => in_array($program, self::READERS, true),
        };
    }

    /**
     * @param Invocation $invocation A command.
     *
     * @return list<string> The paths it writes, as written: its redirects' targets and its writing arguments.
     */
    public function targets(Invocation $invocation): array
    {
        $program = $invocation->program();
        $paths = $this->paths($invocation);

        return [...$invocation->writes, ...match (true) {
            in_array($program, self::WRITERS, true) => $paths,
            in_array($program, self::COPIERS, true) => $program === 'mv' ? $paths : array_slice($paths, -1),
            $program === 'sed' && $this->inPlace($invocation) => array_slice($paths, 1),
            $program === 'dd' => $this->ddOutput($invocation),
            default => [],
        }];
    }

    /**
     * @param Invocation $invocation A command.
     *
     * @return bool Whether it removes, moves or re-owns what it names, so naming a directory reaches what it holds.
     */
    public function destroys(Invocation $invocation): bool
    {
        $program = $invocation->program();

        return in_array($program, self::DESTROYERS, true) || ($program === 'find' && $this->findWrites($invocation));
    }

    /**
     * @param Invocation $invocation A command.
     *
     * @return bool Whether it is `git clean` with `-x` or `-X`, which removes what git ignores: the run's state.
     */
    public function cleansIgnored(Invocation $invocation): bool
    {
        if ($invocation->program() !== 'git' || ($invocation->arguments()[0] ?? '') !== 'clean') {
            return false;
        }

        return array_any(
            $invocation->words,
            static fn (string $word): bool => str_starts_with($word, '-') && !str_starts_with($word, '--')
                && (str_contains($word, 'x') || str_contains($word, 'X')),
        );
    }

    /**
     * @param Invocation $invocation A command.
     *
     * @return list<string> Its arguments that are not options.
     */
    public function paths(Invocation $invocation): array
    {
        $paths = [];

        foreach ($invocation->arguments() as $word) {
            if ($word === '' || str_starts_with($word, '-')) {
                continue;
            }

            $paths[] = $word;
        }

        return $paths;
    }

    /**
     * @param Invocation $invocation A `find`.
     *
     * @return bool Whether it deletes or runs something on what it finds.
     */
    private function findWrites(Invocation $invocation): bool
    {
        return array_any(
            ['-delete', '-exec', '-execdir', '-ok', '-okdir', '-fprint', '-fprint0', '-fprintf', '-fls'],
            static fn (string $action): bool => in_array($action, $invocation->words, true),
        );
    }

    /**
     * @param Invocation $invocation A `git`.
     *
     * @return bool Whether its subcommand only reads, with no `-c`, no unknown option before it and no output file or pager.
     */
    private function gitReads(Invocation $invocation): bool
    {
        $words = $invocation->arguments();
        $index = 0;

        while (in_array($words[$index] ?? '', [...self::GIT_GLOBALS, '-C'], true)) {
            $index += $words[$index] === '-C'
                ? 2
                : 1;
        }

        return in_array($words[$index] ?? '', self::GIT_READS, true)
            && !array_any($words, static fn (string $word): bool => str_starts_with($word, '--output')
                || $word === '-O' || str_starts_with($word, '--open-files-in-pager'));
    }

    /**
     * @param Invocation $invocation A `sed`.
     *
     * @return bool Whether it is the print form, `sed -n <line or range>p [files]`, which writes nothing.
     */
    private function sedPrints(Invocation $invocation): bool
    {
        $options = array_values(array_filter($invocation->arguments(), static fn (string $word): bool => str_starts_with($word, '-')));
        $script = $this->paths($invocation)[0] ?? '';

        return $options === ['-n'] && preg_match(self::SED_PRINT, $script) === 1;
    }

    /**
     * @param Invocation $invocation A `sed`.
     *
     * @return bool Whether it edits in place.
     */
    private function inPlace(Invocation $invocation): bool
    {
        return array_any(
            $invocation->arguments(),
            static fn (string $word): bool => $word === '--in-place'
                || (str_starts_with($word, '-') && !str_starts_with($word, '--') && str_contains($word, 'i')),
        );
    }

    /**
     * @param Invocation $invocation A `dd`.
     *
     * @return list<string> Its `of=` target.
     */
    private function ddOutput(Invocation $invocation): array
    {
        $outputs = [];

        foreach ($invocation->arguments() as $word) {
            if (!str_starts_with($word, 'of=')) {
                continue;
            }

            $outputs[] = substr($word, 3);
        }

        return count($outputs) > 0
            ? $outputs
            : [];
    }
}
