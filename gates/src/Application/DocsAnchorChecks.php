<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\MarkdownDocument;

use function array_key_exists;
use function explode;
use function in_array;
use function mb_strtolower;
use function preg_replace;
use function sprintf;
use function str_contains;
use function str_replace;

/**
 * gate:29's anchor check (D7): every `page.md#anchor` or `#anchor` link inside
 * the set reaches a heading of its page, by GitHub's slug rule. gate:19 checks
 * that the page exists and drops the fragment.
 */
final readonly class DocsAnchorChecks
{
    /**
     * @param MarkdownDocument                $markdownDocument The page holding the links.
     * @param array<string, MarkdownDocument> $pages            Every page, by file name.
     *
     * @return list<string> A finding per fragment no heading of its target page answers.
     */
    public function check(MarkdownDocument $markdownDocument, array $pages): array
    {
        $findings = [];

        foreach ($markdownDocument->links() as [$line, $target]) {
            $unanswered = $this->unanswered($markdownDocument->page, $target, $pages);

            if ($unanswered === null) {
                continue;
            }

            $findings[] = sprintf(
                '%s:%d: %s has no heading for #%s; link a heading that exists.',
                $markdownDocument->page,
                $line,
                $unanswered[0],
                $unanswered[1],
            );
        }

        return $findings;
    }

    /**
     * @param MarkdownDocument $markdownDocument A page.
     *
     * @return list<string> Its heading anchors, a repeated slug numbered from `-1` as GitHub does.
     */
    public function anchors(MarkdownDocument $markdownDocument): array
    {
        $anchors = [];
        $seen = [];

        foreach ($markdownDocument->headings() as [, , $text]) {
            $slug = self::slug($text);
            $count = $seen[$slug] ?? 0;
            $seen[$slug] = $count + 1;
            $anchors[] = $count === 0
                ? $slug
                : $slug . '-' . $count;
        }

        return $anchors;
    }

    /**
     * @param string                          $from   The page holding the link.
     * @param string                          $target The link's target.
     * @param array<string, MarkdownDocument> $pages  Every page, by file name.
     *
     * @return array{string, string}|null The target page and its fragment, when no heading of a page in the set answers it.
     */
    private function unanswered(string $from, string $target, array $pages): ?array
    {
        if (!str_contains($target, '#')) {
            return null;
        }

        [$page, $anchor] = explode('#', $target, 2);
        $page = $page === ''
            ? $from
            : $page;

        return array_key_exists($page, $pages) && !in_array($anchor, $this->anchors($pages[$page]), true)
            ? [$page, $anchor]
            : null;
    }

    /**
     * @param string $heading A heading's text.
     *
     * @return string Its GitHub slug: lower case, punctuation dropped, each space a hyphen.
     */
    private static function slug(string $heading): string
    {
        $kept = (string) preg_replace('/[^\\p{L}\\p{N}\\s_-]/u', '', mb_strtolower($heading));

        return str_replace(' ', '-', $kept);
    }
}
