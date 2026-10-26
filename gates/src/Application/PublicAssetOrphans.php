<?php

declare(strict_types=1);

namespace Gates\Application;

use function array_filter;
use function array_key_exists;
use function array_values;
use function dirname;
use function rmdir;
use function scandir;
use function sort;
use function unlink;

/**
 * The files under `build/assets/` the build would not write (#95): a removed
 * module's copy, an old Tessera release. gate:24 calls them stale, and the
 * build deletes them, with the folders they leave empty. The build owns
 * `build/assets/` and nothing else under `build/`.
 */
final readonly class PublicAssetOrphans
{
    /**
     * @param FileLister $fileLister Lists the files under a folder.
     */
    public function __construct(
        private FileLister $fileLister = new FileLister(),
    ) {
    }

    /**
     * @param string                $directory `build/assets`, absolute.
     * @param array<string, string> $outputs   Every output, by absolute path.
     *
     * @return list<string> The files under the folder no output names, sorted.
     */
    public function orphans(string $directory, array $outputs): array
    {
        $orphans = array_values(array_filter(
            $this->fileLister->files($directory),
            static fn (string $file): bool => !array_key_exists($file, $outputs),
        ));
        sort($orphans);

        return $orphans;
    }

    /**
     * @param list<string> $orphans   The orphans to delete.
     * @param string       $directory `build/assets`, absolute; it stays even when empty.
     */
    public function delete(array $orphans, string $directory): void
    {
        foreach ($orphans as $orphan) {
            unlink($orphan);
            $this->prune(dirname($orphan), $directory);
        }
    }

    /**
     * @param string $folder    A folder an orphan was in.
     * @param string $directory `build/assets`, where pruning stops.
     */
    private function prune(string $folder, string $directory): void
    {
        $entries = scandir($folder);

        if ($folder === $directory || $entries === false || $entries !== ['.', '..']) {
            return;
        }

        rmdir($folder);
        $this->prune(dirname($folder), $directory);
    }
}
