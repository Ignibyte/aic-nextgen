<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\BoundaryViolation;
use Gates\Domain\ModuleRule;
use Gates\Domain\ModuleTree;
use Override;

use function dirname;
use function ksort;
use function sprintf;
use function str_contains;
use function str_starts_with;

/**
 * R2 of gate:26 (#94): a folder under `modules/` holds at most 15 files
 * directly, so an agent reads it whole; past that it splits into subfolders.
 * `src/Migration/` is exempt (M8a shared decision 4): a migration's version
 * orders it, and a long-lived app keeps every one.
 */
final readonly class FolderCapRule implements ModuleRule
{
    /**
     * The most files one folder holds directly (Chad, 2026-10-21).
     */
    public const int CAP = 15;

    /**
     * @param ModuleTree $moduleTree The repository's paths.
     *
     * @return list<BoundaryViolation> Every folder over the cap.
     */
    #[Override]
    public function check(ModuleTree $moduleTree): array
    {
        $violations = [];

        foreach ($this->counts($moduleTree) as $folder => $files) {
            if ($files <= self::CAP) {
                continue;
            }

            $violations[] = new BoundaryViolation($folder, sprintf(
                '%s holds %d files (cap %d); split it into subfolders, e.g. %s/<Topic>/.',
                $folder,
                $files,
                self::CAP,
                $folder,
            ));
        }

        return $violations;
    }

    /**
     * @param ModuleTree $moduleTree The repository's paths.
     *
     * @return array<string, int> The number of files directly in each folder under `modules/`, the exempt ones left out.
     */
    public function counts(ModuleTree $moduleTree): array
    {
        $counts = [];

        foreach ($moduleTree->paths as $path) {
            $folder = dirname($path);

            if (!str_starts_with($path, 'modules/') || str_contains($folder . '/', '/src/Migration/')) {
                continue;
            }

            $counts[$folder] = ($counts[$folder] ?? 0) + 1;
        }

        ksort($counts);

        return $counts;
    }
}
