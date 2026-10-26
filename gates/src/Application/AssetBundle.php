<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\AssetInput;
use Gates\Domain\AssetKind;
use Gates\Domain\BuildPaths;
use InvalidArgumentException;

use function file_get_contents;
use function implode;

/** Plans explicit module and selected-theme copies and their combined CSS in declaration order. */
final readonly class AssetBundle
{
    /** The theme's stylesheets, in path order, make up the bundle. */
    private const string HEADER = "/* Built by composer run build from the theme's CSS: "
        . 'edit those and run composer run build, never this file. */';

    /**
     * @param string     $root       The project folder.
     * @param BundledCss $bundledCss Checks and wraps each CSS contribution.
     */
    public function __construct(private string $root, private BundledCss $bundledCss = new BundledCss())
    {
    }

    /**
     * @param list<AssetInput> $assetInputs The theme's inputs, in path order.
     *
     * @throws InvalidArgumentException When an input is unreadable or CSS cannot be bundled.
     *
     * @return array<string, string> Planned absolute public output paths and bytes; nothing is written.
     */
    public function outputs(array $assetInputs): array
    {
        $parts = [];
        $copies = [];

        foreach ($assetInputs as $assetInput) {
            $contents = $this->contents($assetInput->source);
            $copies[$this->root . '/' . BuildPaths::ASSETS . '/' . $assetInput->file] = $contents;

            if ($assetInput->assetKind !== AssetKind::Style) {
                continue;
            }

            $parts[] = $this->bundledCss->part($assetInput->source, $contents);
        }

        $stylesheet = self::HEADER . "\n\n" . implode("\n", $parts);

        return [$this->root . '/' . BuildPaths::ASSETS . '/aic.css' => $stylesheet, ...$copies];
    }

    /**
     * @param string $source The validated project-relative source.
     *
     * @throws InvalidArgumentException When it cannot be read.
     *
     * @return string Its unchanged bytes.
     */
    private function contents(string $source): string
    {
        $contents = file_get_contents($this->root . '/' . $source);

        if ($contents === false) {
            throw new InvalidArgumentException($source . ': cannot read the asset.');
        }

        return $contents;
    }
}
