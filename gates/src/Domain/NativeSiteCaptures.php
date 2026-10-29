<?php

declare(strict_types=1);

namespace Gates\Domain;

use function preg_quote;
use function preg_replace;
use function strlen;

/**
 * The records one site's run of gate:23 created (#178), as the paths its
 * capturing steps kept, and how each reads once normalised: the path, as a
 * whole path segment, reads `<captured>`, and a path ending in a number also
 * normalises that number where a page names it after a `-`, as an element id
 * or a key does (`notification-12`, #214). Each site creates its own records,
 * with their own ids, against the one database.
 */
final readonly class NativeSiteCaptures
{
    use RegexCaptures;

    /**
     * The captured paths.
     *
     * @var list<string>
     */
    private array $paths;

    /**
     * @param list<NativeSiteAnswer> $answers One site's answers.
     */
    public function __construct(
        array $answers,
    ) {
        $paths = [];

        foreach ($answers as $answer) {
            if (strlen($answer->captured) < 2) {
                continue;
            }

            $paths[] = $answer->captured;
        }

        $this->paths = $paths;
    }

    /**
     * @param string $text Text from an answer.
     *
     * @return string The text with each captured path read as `<captured>`, and its trailing id as `<captured-id>`
     *                after a `-`.
     */
    public function normalised(string $text): string
    {
        foreach ($this->paths as $path) {
            $text = preg_replace('#' . preg_quote($path, '#') . '(?![\\w-])#', '<captured>', $text) ?? $text;

            $id = self::regexMatch('#/(\\d+)$#', $path)[1] ?? '';

            if ($id === '') {
                continue;
            }

            $text = preg_replace('#(?<=-)' . $id . '(?![\\w-])#', '<captured-id>', $text) ?? $text;
        }

        return $text;
    }
}
