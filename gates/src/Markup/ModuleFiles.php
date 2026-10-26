<?php

declare(strict_types=1);

namespace Gates\Markup;

use Gates\Domain\MarkupFinding;

use function array_diff;
use function is_dir;
use function is_link;
use function scandir;
use function sort;

/**
 * Walks `modules/` on disk for gate:32 (#156), ignored files included, since the build and the site read the disk.
 * A link fails closed: it could point a module at a template elsewhere.
 */
final readonly class ModuleFiles
{
    /**
     * @param string $root     The root.
     * @param string $relative A folder under it.
     *
     * @return array{list<string>, list<MarkupFinding>} Its files, relative to the root and sorted, and each link or
     *                                                  unreadable folder.
     */
    public function walk(string $root, string $relative): array
    {
        $entries = scandir($root . '/' . $relative);

        if ($entries === false) {
            return [[], [new MarkupFinding($relative, 0, 'the folder cannot be read')]];
        }

        $entries = [...array_diff($entries, ['.', '..'])];
        sort($entries);
        $files = [];
        $findings = [];

        foreach ($entries as $entry) {
            [$more, $found] = $this->entry($root, $relative . '/' . $entry);
            $files = [...$files, ...$more];
            $findings = [...$findings, ...$found];
        }

        return [$files, $findings];
    }

    /**
     * @param string $root     The root.
     * @param string $relative One entry, relative to it.
     *
     * @return array{list<string>, list<MarkupFinding>} The entry's files, and its findings.
     */
    private function entry(string $root, string $relative): array
    {
        if (is_link($root . '/' . $relative)) {
            return [[], [new MarkupFinding($relative, 0, 'it is a link, which could point a module at markup')]];
        }

        return is_dir($root . '/' . $relative)
            ? $this->walk($root, $relative)
            : [[$relative], []];
    }
}
