<?php

declare(strict_types=1);

namespace Gates\Domain;

use function preg_quote;
use function preg_replace;
use function strlen;

/**
 * The records one site's run of gate:23 created (#178), as the paths its
 * capturing steps kept: each path, as a whole path segment, reads
 * `<captured>` once normalised, and a path ending in a number gives that
 * number as one of the site's ids, which `CapturedIds` allows where a page
 * names it after a `-` or a `=` (`notification-12`, #214; `?after=12`, #215).
 * Each site creates its own records, with their own ids, against the one
 * database.
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
     * @return string The text with each captured path read as `<captured>`.
     */
    public function normalised(string $text): string
    {
        foreach ($this->paths as $path) {
            $text = preg_replace('#' . preg_quote($path, '#') . '(?![\\w-])#', '<captured>', $text) ?? $text;
        }

        return $text;
    }

    /**
     * @return list<string> The trailing number of each captured path that ends in one, the site's record ids.
     */
    public function ids(): array
    {
        $ids = [];

        foreach ($this->paths as $path) {
            $id = self::regexMatch('#/(\\d+)$#', $path)[1] ?? '';

            if ($id === '') {
                continue;
            }

            $ids[] = $id;
        }

        return $ids;
    }
}
