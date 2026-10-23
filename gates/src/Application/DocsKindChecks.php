<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\MarkdownDocument;
use Gates\Domain\RegexCaptures;

use function array_column;
use function array_diff;
use function array_key_exists;
use function in_array;
use function sprintf;

/**
 * gate:29's kind checks: `kinds.md` has a heading per entry kind and
 * `modules.md`'s folder table lists every folder a module's `src/` may hold
 * (D3), and `modules.md` names every declaration of the module manifest (D5).
 */
final readonly class DocsKindChecks
{
    use RegexCaptures;

    /**
     * @param MarkdownDocument $markdownDocument `kinds.md`.
     * @param list<string>     $kinds            The entry kinds.
     *
     * @return list<string> A finding per entry kind with no heading of its own.
     */
    public function kinds(MarkdownDocument $markdownDocument, array $kinds): array
    {
        $headings = array_column($markdownDocument->headings(), 2);
        $findings = [];

        foreach ($kinds as $kind) {
            if (in_array($kind, $headings, true)) {
                continue;
            }

            $findings[] = sprintf('kinds.md: the %s kind has no heading; add a "%s" section.', $kind, $kind);
        }

        return $findings;
    }

    /**
     * @param MarkdownDocument $markdownDocument `modules.md`.
     * @param list<string>     $folders          The folders a module's `src/` may hold.
     *
     * @return list<string> A finding per folder the table lacks, and per row naming no such folder.
     */
    public function folders(MarkdownDocument $markdownDocument, array $folders): array
    {
        $rows = [];

        foreach ($markdownDocument->firstCells() as [, $cell]) {
            $match = self::regexMatch('#^`src/(\\w+)/`$#', $cell);

            if (!array_key_exists(1, $match)) {
                continue;
            }

            $rows[] = $match[1];
        }

        $findings = [];

        foreach (array_diff($folders, $rows) as $folder) {
            $findings[] = sprintf('modules.md: the folder table has no `src/%s/` row; add one.', $folder);
        }

        foreach (array_diff($rows, $folders) as $folder) {
            $findings[] = sprintf('modules.md: `src/%s/` is not a module folder; drop the row.', $folder);
        }

        return $findings;
    }

    /**
     * @param MarkdownDocument $markdownDocument `modules.md`.
     * @param list<string>     $methods          The manifest's declaration methods.
     *
     * @return list<string> A finding per declaration the page never names as `->name(`.
     */
    public function declarations(MarkdownDocument $markdownDocument, array $methods): array
    {
        $findings = [];

        foreach ($methods as $method) {
            if ($markdownDocument->mentions('->' . $method . '(')) {
                continue;
            }

            $findings[] = sprintf('modules.md: the manifest declaration ->%s( is never named; describe it.', $method);
        }

        return $findings;
    }
}
