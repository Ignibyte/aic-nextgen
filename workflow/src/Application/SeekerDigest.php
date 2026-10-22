<?php

declare(strict_types=1);

namespace AIC\Workflow\Application;

use function array_slice;
use function explode;
use function hash;
use function implode;
use function rtrim;
use function str_replace;
use function str_starts_with;
use function trim;

/**
 * A seeker's ledger section and its digest (#103, Droost's
 * `guard_ledger_digest`, one implementation): the guard digests what an
 * `aic-seeker` returned, the filing digests what the agent piped in, and the
 * two must agree. The last `## Seeker Inspection` section wins; it ends at
 * the next `## ` heading. Trailing spaces and blank lines do not count.
 */
final readonly class SeekerDigest
{
    /**
     * The ledger's heading.
     */
    public const string HEADING = '## Seeker Inspection';

    /**
     * @param string $text Text that may hold a ledger.
     *
     * @return list<string>|null The last section's lines, heading first, trailing spaces and blank lines dropped; null when
     *                           there is none.
     */
    public function section(string $text): ?array
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $text));
        $start = null;

        foreach ($lines as $index => $line) {
            if (trim($line) !== self::HEADING) {
                continue;
            }

            $start = $index;
        }

        return $start === null
            ? null
            : $this->until(array_slice($lines, $start));
    }

    /**
     * @param string $text Text that may hold a ledger.
     *
     * @return string `sha256:<hex>` over its last section; empty when there is none.
     */
    public function digest(string $text): string
    {
        $section = $this->section($text);

        return $section === null
            ? ''
            : 'sha256:' . hash('sha256', implode("\n", $section));
    }

    /**
     * @param list<string> $lines The lines from the heading on.
     *
     * @return list<string> Them up to the next `## ` heading, normalized.
     */
    private function until(array $lines): array
    {
        $section = [rtrim($lines[0])];

        foreach (array_slice($lines, 1) as $line) {
            if (str_starts_with($line, '## ')) {
                break;
            }

            if (trim($line) === '') {
                continue;
            }

            $section[] = rtrim($line);
        }

        return $section;
    }
}
