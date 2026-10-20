<?php

declare(strict_types=1);

namespace Gates\Application;

use InvalidArgumentException;

use function basename;
use function file_get_contents;
use function glob;
use function implode;
use function rtrim;
use function sort;

/**
 * Builds the project theme (#39): every `theme/*.css`, in name order, joined
 * behind a header into the one stylesheet the layout links.
 */
final readonly class ThemeBuild
{
    /**
     * @param string $theme The theme directory.
     */
    public function __construct(
        private string $theme,
    ) {
    }

    /**
     * @throws InvalidArgumentException When the theme has no CSS, or a file cannot be read.
     *
     * @return string The stylesheet.
     */
    public function stylesheet(): string
    {
        $files = glob($this->theme . '/*.css');

        if ($files === false || $files === []) {
            throw new InvalidArgumentException('The theme at ' . $this->theme . ' has no CSS.');
        }

        sort($files);
        $parts = [];

        foreach ($files as $file) {
            $parts[] = '/* theme/' . basename($file) . " */\n" . rtrim($this->contents($file)) . "\n";
        }

        return "/* Built from theme/ by bin/aic-build (#39): edit theme/ and run composer run build, never this file. */\n\n"
            . implode("\n", $parts);
    }

    /**
     * @param string $file A theme file.
     *
     * @throws InvalidArgumentException When it cannot be read.
     *
     * @return string Its contents.
     */
    private function contents(string $file): string
    {
        $contents = file_get_contents($file);

        if ($contents === false) {
            throw new InvalidArgumentException('Cannot read ' . $file . '.');
        }

        return $contents;
    }
}
