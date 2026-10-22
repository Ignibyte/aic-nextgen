<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\MarkdownDocument;

use function implode;
use function mb_substr_count;
use function preg_match_all;
use function preg_quote;
use function sprintf;

/**
 * gate:29's writing check (D9), the mechanical part of the no-ai-slop standard:
 * no banned word in prose, and at most two em dashes a page. Code spans and
 * fenced blocks are quoted code and stay as written.
 */
final readonly class DocsWritingChecks
{
    /**
     * no-ai-slop's "banned outright" list (SKILL.md, "Words to cut"), copied as it stands.
     */
    private const array BANNED = [
        'delve',
        'foster',
        'leverage',
        'utilize',
        'facilitate',
        'empower',
        'streamline',
        'robust',
        'cutting-edge',
        'paradigm shift',
        'game changer',
        'this is huge',
        'this changes everything',
        'tapestry',
        'realm',
        'beacon',
        'multifaceted',
        'meticulous',
        'intricate',
        'paramount',
        'transformative',
        'elevate',
        'embark',
        'supercharge',
        'harness',
        'ever-evolving',
    ];

    /**
     * The em dashes a page may hold.
     */
    private const int DASHES = 2;

    /**
     * @param MarkdownDocument $markdownDocument A page.
     *
     * @return list<string> A finding per banned word in prose, and one when the page passes its em-dash budget.
     */
    public function check(MarkdownDocument $markdownDocument): array
    {
        $pattern = self::pattern();
        $findings = [];
        $dashes = 0;

        foreach ($markdownDocument->prose() as $line => $text) {
            $dashes += mb_substr_count($text, '—');
            preg_match_all($pattern, $text, $matches);

            foreach ($matches[1] as $word) {
                $findings[] = sprintf(
                    '%s:%d: "%s" is a banned word (no-ai-slop); say what it does in plain words.',
                    $markdownDocument->page,
                    $line,
                    $word,
                );
            }
        }

        $budget = sprintf('%s: %d em dashes, over the budget of %d; recast the sentences.', $markdownDocument->page, $dashes, self::DASHES);

        return $dashes > self::DASHES
            ? [...$findings, $budget]
            : $findings;
    }

    /**
     * @return non-empty-string The banned words as one case-blind pattern, whole words only.
     */
    private static function pattern(): string
    {
        $words = [];

        foreach (self::BANNED as $word) {
            $words[] = preg_quote($word, '/');
        }

        return '/\\b(' . implode('|', $words) . ')\\b/i';
    }
}
