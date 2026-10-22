<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use AIC\Workflow\Exception\WorkflowRefusal;

use function array_all;
use function array_slice;
use function array_splice;
use function count;
use function explode;
use function implode;
use function in_array;
use function rtrim;
use function trim;

/**
 * The workflow's region in a file the project owns (#105, Droost's
 * `AgentsBlock`): the lines between `<!-- BEGIN AIC WORKFLOW -->` and
 * `<!-- END AIC WORKFLOW -->`, each marker on a line of its own. Init
 * replaces only those lines and appends the region when it is absent; a
 * marker without its partner is refused, never guessed at.
 */
final readonly class ManagedRegion
{
    /**
     * The opening marker.
     */
    public const string BEGIN = '<!-- BEGIN AIC WORKFLOW -->';

    /**
     * The closing marker.
     */
    public const string END = '<!-- END AIC WORKFLOW -->';

    /**
     * Why markers out of order refuse.
     */
    private const string DISORDER = " holds the workflow's markers out of order (one " . self::BEGIN . ', then one ' . self::END
        . '); mend them by hand.';

    /**
     * @param string      $path A file that may hold the region.
     * @param string|null $text Its text; null when there is no file.
     *
     * @throws WorkflowRefusal When its markers are not one BEGIN and one END after it.
     */
    public function check(string $path, ?string $text): void
    {
        $begins = $this->indexes($text, self::BEGIN);
        $ends = $this->indexes($text, self::END);

        if ($begins === [] && $ends === []) {
            return;
        }

        if (count($begins) !== 1 || count($ends) !== 1 || $ends[0] < $begins[0]) {
            throw new WorkflowRefusal($path . self::DISORDER);
        }
    }

    /**
     * @param string|null $text A checked file's text; null for none.
     *
     * @return string|null The region's lines, each ending in a line break; null when there is no region.
     */
    public function region(?string $text): ?string
    {
        $lines = explode("\n", (string) $text);
        $begins = $this->indexes($text, self::BEGIN);
        $ends = $this->indexes($text, self::END);

        if ($begins === [] || $ends === []) {
            return null;
        }

        $inner = array_slice($lines, $begins[0] + 1, $ends[0] - $begins[0] - 1);

        return $inner === []
            ? ''
            : implode("\n", $inner) . "\n";
    }

    /**
     * @param string|null $text  A checked file's text; null for none.
     * @param string      $inner The region's lines, each ending in a line break.
     *
     * @return string The text with the region set to them: replaced in place, or appended after a blank line.
     */
    public function upsert(?string $text, string $inner): string
    {
        $region = self::BEGIN . "\n" . $inner . self::END;
        $begins = $this->indexes($text, self::BEGIN);
        $ends = $this->indexes($text, self::END);

        if ($begins === [] || $ends === []) {
            $kept = rtrim((string) $text);

            return ($kept === '' ? '' : $kept . "\n\n") . $region . "\n";
        }

        $lines = explode("\n", (string) $text);
        array_splice($lines, $begins[0], $ends[0] - $begins[0] + 1, [$region]);

        return implode("\n", $lines);
    }

    /**
     * @param string|null $text  A checked file's text; null for none.
     * @param string      $inner The region's lines.
     *
     * @return bool Whether every line of them is already a line of the file outside the region, as an `@AGENTS.md`
     *              import a CLAUDE.md already has.
     */
    public function heldOutside(?string $text, string $inner): bool
    {
        $regionLines = explode("\n", $this->region($text) ?? '');
        $outside = explode("\n", (string) $text);

        return array_all(
            explode("\n", trim($inner)),
            static fn (string $line): bool => in_array($line, $outside, true) && !in_array($line, $regionLines, true),
        );
    }

    /**
     * @param string|null $text   A file's text; null for none.
     * @param string      $marker A marker.
     *
     * @return list<int> The indexes of the lines that are the marker, trimmed.
     */
    private function indexes(?string $text, string $marker): array
    {
        $indexes = [];

        foreach (explode("\n", (string) $text) as $index => $line) {
            if (trim($line) !== $marker) {
                continue;
            }

            $indexes[] = $index;
        }

        return $indexes;
    }
}
