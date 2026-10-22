<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\BoundaryViolation;
use Gates\Domain\ModuleRule;
use Gates\Domain\ModuleTree;
use InvalidArgumentException;
use RuntimeException;

use function array_filter;
use function array_keys;
use function count;
use function fwrite;
use function sprintf;
use function str_starts_with;
use function usort;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Gate #26: the module boundaries (#94, part 3 of #90). It reads the modules
 * and the repository once and runs five rules over them: a class's kind
 * decides its folder (R1), a folder holds at most 15 files (R2), a module uses
 * only what another exports and only from modules it requires (R3), the
 * requires name modules and form no cycle (R4), and nothing outside
 * `modules/` but the shell (R5). Each breach is one line naming the folder,
 * export or require it needs. There is no deferral: a tree it cannot read
 * fails the gate.
 */
final readonly class ModuleBoundariesGate
{
    /**
     * @param ModuleTreeReader $moduleTreeReader Reads the modules and the repository.
     * @param list<ModuleRule> $moduleRules      The rules, in report order.
     * @param FolderCapRule    $folderCapRule    Counts the folders for the success line.
     */
    public function __construct(
        private ModuleTreeReader $moduleTreeReader,
        private array $moduleRules = [
            new KindFolderRule(),
            new FolderCapRule(),
            new ModuleReferenceRule(),
            new RequireGraphRule(),
            new ModuleShellRule(),
        ],
        private FolderCapRule $folderCapRule = new FolderCapRule(),
    ) {
    }

    /**
     * @param string   $root   The repo root.
     * @param resource $stdout Where the success line goes.
     * @param resource $stderr Where the breaches go.
     *
     * @return int The exit code: 0 when the boundaries hold.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        try {
            $moduleTree = $this->moduleTreeReader->read($root);
        } catch (InvalidArgumentException $invalidArgumentException) {
            return $this->unreadable($invalidArgumentException->getMessage(), $stderr);
        } catch (RuntimeException $runtimeException) {
            return $this->unreadable($runtimeException->getMessage(), $stderr);
        }

        $violations = $this->violations($moduleTree);

        foreach ($violations as $violation) {
            fwrite($stderr, 'gate:26 — ' . $violation->message . PHP_EOL);
        }

        if ($violations !== []) {
            return 1;
        }

        fwrite($stdout, $this->summary($moduleTree) . PHP_EOL);

        return 0;
    }

    /**
     * @param string   $why    Why the tree could not be read: a refused manifest, git, a file.
     * @param resource $stderr Where the line goes.
     *
     * @return int 1: the gate fails closed.
     */
    private function unreadable(string $why, mixed $stderr): int
    {
        fwrite($stderr, 'gate:26 — cannot read the modules (fail-closed): ' . $why . PHP_EOL);

        return 1;
    }

    /**
     * @param ModuleTree $moduleTree The tree.
     *
     * @return list<BoundaryViolation> Every rule's breaches, by path.
     */
    private function violations(ModuleTree $moduleTree): array
    {
        $violations = [];

        foreach ($this->moduleRules as $moduleRule) {
            $violations = [...$violations, ...$moduleRule->check($moduleTree)];
        }

        usort($violations, static fn (BoundaryViolation $left, BoundaryViolation $right): int => $left->path <=> $right->path);

        return $violations;
    }

    /**
     * @param ModuleTree $moduleTree The tree.
     *
     * @return string The success line, with what was checked.
     */
    private function summary(ModuleTree $moduleTree): string
    {
        $classes = array_filter(
            array_keys($moduleTree->symbols),
            static fn (string $path): bool => str_starts_with($path, 'modules/') && $moduleTree->declared($path) !== null,
        );

        return sprintf(
            'gate:26 — module boundaries hold: %d modules, %d classes, %d folders (cap %d), no require cycles.',
            count($moduleTree->modules),
            count($classes),
            count($this->folderCapRule->counts($moduleTree)),
            FolderCapRule::CAP,
        );
    }
}
