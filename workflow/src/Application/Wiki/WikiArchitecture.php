<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Wiki;

use AIC\Workflow\Domain\RegexCaptures;

use function array_key_exists;
use function explode;
use function mb_strtolower;
use function preg_match;
use function preg_replace;
use function str_replace;

/**
 * Reads the anchors the wiki links to out of AIC's architecture pages (#107,
 * #108): each `### <Kind>` heading of `kinds.md`, and each `## <n>. <Name>`
 * system section of `systems.md` with the `AIC\<Namespace>` spans under it,
 * which gate:29 keeps exact. Anchors follow GitHub's slug rule.
 */
final readonly class WikiArchitecture
{
    use RegexCaptures;

    /**
     * @param string $kinds `kinds.md`'s text.
     *
     * @return array<string, string> Each kind heading and its anchor.
     */
    public function kinds(string $kinds): array
    {
        $anchors = [];

        foreach (self::regexColumn('/^###\\s+(\\w+)\\s*$/m', $kinds, 1) as $kind) {
            $anchors[$kind] = self::slug($kind);
        }

        return $anchors;
    }

    /**
     * @param string $systems `systems.md`'s text.
     *
     * @return array<string, string> Each namespace a system section names, and that section's anchor.
     */
    public function systems(string $systems): array
    {
        $anchors = [];
        $section = '';

        foreach (explode("\n", $systems) as $line) {
            $section = self::section($line, $section);
            $subject = $section === ''
                ? ''
                : $line;

            foreach (self::regexColumn('/`AIC\\\\(\\w+)`/', $subject, 1) as $namespace) {
                $anchors[$namespace] = $section;
            }
        }

        return $anchors;
    }

    /**
     * @param string $line    A line of `systems.md`.
     * @param string $section The anchor of the system section the line before sat in; empty outside one.
     *
     * @return string The anchor of the system section this line sits in; empty outside one.
     */
    private static function section(string $line, string $section): string
    {
        $match = self::regexMatch('/^##\\s+(.+?)\\s*$/', $line);

        if (!array_key_exists(1, $match)) {
            return $section;
        }

        return preg_match('/^\\d+\\.\\s/', $match[1]) === 1
            ? self::slug($match[1])
            : '';
    }

    /**
     * @param string $heading A heading's text.
     *
     * @return string Its GitHub slug.
     */
    private static function slug(string $heading): string
    {
        return str_replace(' ', '-', (string) preg_replace('/[^\\p{L}\\p{N}\\s_-]/u', '', mb_strtolower($heading)));
    }
}
