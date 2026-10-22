<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Guard;

use AIC\Workflow\Domain\Guard\GuardScene;
use AIC\Workflow\Domain\RunState;

use function array_any;
use function in_array;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * What the agent never writes, run or no run, whatever the enforcement
 * (#102): the run's state but its spec, the opt-in, the hook wiring, the
 * workflow package itself, and Claude Code's own global settings and
 * plugins. A path counts as spelled and as landed. Reading is never
 * refused.
 */
final readonly class ProtectedPaths
{
    /**
     * The project's protected files, named whole in a word; its protected directories are named with their slash.
     */
    private const array FILES = ['config/workflow.php', '.claude/settings.json', '.claude/settings.local.json'];

    /**
     * @param string       $root         The project's root.
     * @param string       $home         The user's home; empty when unknown.
     * @param string       $package      The workflow package the guard runs from.
     * @param PathResolver $pathResolver Where a path lands.
     */
    public function __construct(
        private string $root,
        private string $home,
        private string $package,
        private PathResolver $pathResolver,
    ) {
    }

    /**
     * @param string $absolute An absolute path.
     * @param string $spec     The open run's spec, absolute; empty when no run is open.
     *
     * @return string Why it is protected, naming who changes it; empty when it is not.
     */
    public function reason(string $absolute, string $spec): string
    {
        $landed = $this->pathResolver->landed($absolute);

        foreach ($this->entries() as $entry => $why) {
            if (
                $absolute !== $spec
                && $landed !== $spec
                && (
                    $this->under($absolute, $entry)
                    || $this->under($landed, $entry)
                )
            ) {
                return $why;
            }
        }

        return '';
    }

    /**
     * @param string $absolute An absolute directory.
     *
     * @return bool Whether it holds a protected path, so removing, moving or re-owning it whole would reach one.
     */
    public function holds(string $absolute): bool
    {
        $landed = $this->pathResolver->landed($absolute);

        return array_any(
            $this->entries(),
            fn (string $why, string $entry): bool => $why !== '' && ($this->under($entry, $absolute) || $this->under($entry, $landed)),
        );
    }

    /**
     * @param string $text A word or a script a command was handed.
     *
     * @return string Why it names a protected path; empty when it names none.
     */
    public function mentioned(string $text): string
    {
        foreach ($this->entries() as $entry => $why) {
            if (str_contains($text, $this->needle($entry))) {
                return $why;
            }
        }

        return '';
    }

    /**
     * @param GuardScene $guardScene The run.
     *
     * @return string The open run's spec, absolute; empty when no run is open.
     */
    public function spec(GuardScene $guardScene): string
    {
        $runState = $guardScene->runState;

        return $runState instanceof RunState
            ? $this->root . '/' . $runState->runStart->specPath
            : '';
    }

    /**
     * @param string $entry A protected path, absolute.
     *
     * @return string How a command's words name it: relative inside the project, a directory with its slash.
     */
    private function needle(string $entry): string
    {
        if (!str_starts_with($entry, $this->root . '/')) {
            return $entry;
        }

        $named = substr($entry, strlen($this->root) + 1);

        return in_array($named, self::FILES, true)
            ? $named
            : $named . '/';
    }

    /**
     * @return array<string, string> Each protected path, absolute, and the words naming who changes it.
     */
    private function entries(): array
    {
        $entries = [
            $this->package => "the workflow package is the operator's: it changes in its own repository.",
            $this->root . '/.claude/settings.json' => "the hook wiring is the operator's.",
            $this->root . '/.claude/settings.local.json' => "the hook wiring is the operator's.",
            $this->root . '/config/workflow.php' => 'config/workflow.php holds the levers: the operator changes it.',
            $this->root . '/var/workflow' => "var/workflow/ is the run's state: only the engine writes it, through bin/aic workflow:*.",
        ];

        return $this->home === ''
            ? $entries
            : [
            ...$entries,
            $this->home . '/.claude.json' => "Claude Code's global settings are the operator's.",
            $this->home . '/.claude/plugins' => "Claude Code's plugins are the operator's.",
            $this->home . '/.claude/settings.json' => "Claude Code's global settings are the operator's.",
        ];
    }

    /**
     * @param string $path  An absolute path.
     * @param string $entry An absolute path that may hold it.
     *
     * @return bool Whether the path is the entry or lies under it.
     */
    private function under(string $path, string $entry): bool
    {
        return $path === $entry || str_starts_with($path, $entry . '/');
    }
}
