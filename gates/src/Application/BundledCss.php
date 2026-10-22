<?php

declare(strict_types=1);

namespace Gates\Application;

use InvalidArgumentException;

use function preg_match;
use function preg_match_all;
use function rtrim;
use function str_starts_with;

/**
 * One part of the bundled stylesheet (#95): a CSS file's rules behind a
 * comment naming the file. Two shapes break once files are joined, so they are
 * refused: `@import`, which is ignored after the first rule, and a relative
 * `url()`, which would resolve against `/assets/` rather than the file's own
 * folder. An absolute path, a `data:` URL and a `#` fragment are kept.
 */
final readonly class BundledCss
{
    /**
     * @param string $path     The file, relative to the repo root (`modules/site/assets/site.css`).
     * @param string $contents Its CSS.
     *
     * @throws InvalidArgumentException When it holds `@import` or a relative `url()`.
     *
     * @return string The part: the comment, the rules, a final newline.
     */
    public function part(string $path, string $contents): string
    {
        if (preg_match('/@import\\b/i', $contents) === 1) {
            throw new InvalidArgumentException($path . ' uses @import, which a joined stylesheet ignores; inline the rules.');
        }

        preg_match_all('/url\\(\\s*[\'"]?([^\'")]*)/i', $contents, $matches);

        foreach ($matches[1] as $url) {
            if (!$this->absolute($url)) {
                throw new InvalidArgumentException(
                    $path . ' has url(' . $url . '), which would resolve against /assets/; use /assets/<module>/<file>, a data: URL or #.',
                );
            }
        }

        return '/* ' . $path . " */\n" . rtrim($contents) . "\n";
    }

    /**
     * @param string $url A `url()` value, unquoted.
     *
     * @return bool Whether it resolves the same anywhere: a path from the root, a `data:` URL or a fragment.
     */
    private function absolute(string $url): bool
    {
        return str_starts_with($url, '/') || str_starts_with($url, 'data:') || str_starts_with($url, '#');
    }
}
