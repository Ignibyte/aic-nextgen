<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Guard;

use AIC\Workflow\Domain\Guard\GuardScene;
use AIC\Workflow\Domain\RunState;

use function array_any;
use function array_keys;
use function in_array;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * What the agent never writes, run or no run, whatever the enforcement
 * (#102): the run's state but its spec, the opt-in, the hook wiring, the
 * workflow package itself, the pack `workflow:init` installed and its lock
 * (#105), and Claude Code's own global settings and plugins. A path counts
 * as spelled and as landed. Reading is never refused.
 */
final readonly class ProtectedPaths
{
    /**
     * The project's protected files, named whole in a word; its protected directories are named with their slash.
     */
    private const array FILES = ['config/workflow.php', '.claude/settings.json', '.claude/settings.local.json'];

    /**
     * Why a file of the pack is protected (#105).
     */
    private const string PACK = " is the workflow's pack: bin/aic workflow:init writes it, and the operator edits it between runs.";

    /**
     * @param string       $root         The project's root.
     * @param string       $home         The user's home; empty when unknown.
     * @param string       $package      The workflow package the guard runs from.
     * @param PathResolver $pathResolver Where a path lands.
     * @param list<string> $pack         The pack's files and its lock, relative to the project (#105).
     */
    public function __construct(
        private string $root,
        private string $home,
        private string $package,
        private PathResolver $pathResolver,
        private array $pack = [],
    ) {
    }

    /**
     * @param string     $absolute   An absolute path.
     * @param GuardScene $guardScene The run: its spec may be written, its frozen house-rule files may not (#104).
     *
     * @return string Why it is protected, naming who changes it; empty when it is not.
     */
    public function reason(string $absolute, GuardScene $guardScene): string
    {
        $landed = $this->pathResolver->landed($absolute);
        $spec = $this->spec($guardScene);

        return $absolute === $spec || $landed === $spec ? '' : $this->hit([$absolute, $landed], $this->entries($guardScene));
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
     * @param list<string>          $paths   A path as spelled and as landed.
     * @param array<string, string> $entries The protected paths and their words.
     *
     * @return string The words of the first entry either path lies under; empty when none.
     */
    private function hit(array $paths, array $entries): string
    {
        foreach ($entries as $entry => $why) {
            if ($this->under($paths[0], $entry) || $this->under($paths[1], $entry)) {
                return $why;
            }
        }

        return '';
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

        return in_array($named, [...self::FILES, ...$this->pack], true)
            ? $named
            : $named . '/';
    }

    /**
     * @param GuardScene|null $guardScene The run, whose frozen house-rule files join the list (#104); null for the fixed list.
     *
     * @return array<string, string> Each protected path, absolute, and the words naming who changes it.
     */
    private function entries(?GuardScene $guardScene = null): array
    {
        $entries = [
            $this->package => "the workflow package is the operator's: it changes in its own repository.",
            $this->root . '/.claude/settings.json' => "the hook wiring is the operator's.",
            $this->root . '/.claude/settings.local.json' => "the hook wiring is the operator's.",
            $this->root . '/config/workflow.php' => 'config/workflow.php holds the levers: the operator changes it.',
            $this->root . '/var/index' => 'var/index/ is the code index: only bin/aic code:* and the engine write it (#118).',
            $this->root . '/var/workflow' => "var/workflow/ is the run's state: only the engine writes it, through bin/aic workflow:*.",
        ];

        foreach ($this->pack as $path) {
            $entries[$this->root . '/' . $path] = $path . self::PACK;
        }

        $entries = [...$entries, ...$this->ruled($guardScene)];

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
     * @param GuardScene|null $guardScene The run.
     *
     * @return array<string, string> The open run's frozen house-rule files and their words (#104).
     */
    private function ruled(?GuardScene $guardScene): array
    {
        $runState = $guardScene instanceof GuardScene
            ? $guardScene->runState
            : null;
        $ruled = [];

        foreach (array_keys($runState instanceof RunState ? $runState->runStart->ruleFiles : []) as $path) {
            $ruled[$this->root . '/' . $path] = $path . " is a house rule's, frozen at begin: the operator changes it between runs.";
        }

        return $ruled;
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
