<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Coverage;

use AIC\Workflow\Domain\RegexCaptures;

use function explode;
use function implode;
use function str_starts_with;

/**
 * The Tessera names the coverage report counts (#232): the first cell of
 * each row of the vocabulary table in the framework's `docs/tessera.md`,
 * the section headed `## Vocabulary`. The page keeps the table in step with
 * the Tessera AIC pins. A `runtime` row names a member of the script API
 * (#271), which an app writes only in a script.
 */
final readonly class TesseraVocabulary
{
    use RegexCaptures;

    /**
     * The section's heading.
     */
    private const string HEADING = '## Vocabulary';

    /**
     * A table row whose first cell is one name in backticks.
     */
    private const string ROW = '#^\\| `(@?[a-z][a-z0-9:_-]*)` \\|#m';

    /**
     * A row of the script API: its kind cell is `runtime`.
     */
    private const string RUNTIME = '#^\\| `([a-z][a-z0-9]*)` \\| runtime \\|#m';

    /**
     * @param string $page The page's markdown.
     *
     * @return list<string> The names, in the table's order; none when the page has no such section.
     */
    public function names(string $page): array
    {
        return self::regexColumn(self::ROW, implode("\n", self::section($page)), 1);
    }

    /**
     * @param string $page The page's markdown.
     *
     * @return list<string> The names of the script API's members (#271), in the table's order.
     */
    public function runtime(string $page): array
    {
        return self::regexColumn(self::RUNTIME, implode("\n", self::section($page)), 1);
    }

    /**
     * @param string $page The page's markdown.
     *
     * @return list<string> The lines of its vocabulary section, from the heading to the next one.
     */
    private static function section(string $page): array
    {
        $lines = [];
        $inside = false;

        foreach (explode("\n", $page) as $line) {
            $inside = str_starts_with($line, '## ')
                ? $line === self::HEADING
                : $inside;
            $lines = $inside
                ? [...$lines, $line]
                : $lines;
        }

        return $lines;
    }
}
