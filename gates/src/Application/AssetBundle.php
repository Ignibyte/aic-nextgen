<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Contracts\ModuleAssetKind;
use Gates\Domain\AssetInput;
use InvalidArgumentException;

use function file_get_contents;
use function implode;

/** Plans explicit module and selected-theme copies and their combined CSS in declaration order. */
final readonly class AssetBundle
{
    /** Authored declarations determine every stylesheet contribution. */
    private const string HEADER = '/* Built by composer run build from declared module and selected-theme CSS: '
        . 'edit those and run composer run build, never this file. */';

    /**
     * @param string     $root       The project folder.
     * @param BundledCss $bundledCss Checks and wraps each declared CSS contribution.
     */
    public function __construct(private string $root, private BundledCss $bundledCss = new BundledCss())
    {
    }

    /**
     * @param list<AssetInput> $assetInputs Validated explicit inputs in module and declaration order.
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
            $copies[$this->root . '/public/assets/' . $assetInput->file] = $contents;

            if ($assetInput->moduleAssetKind !== ModuleAssetKind::Style) {
                continue;
            }

            $parts[] = $this->bundledCss->part($assetInput->source, $contents);
        }

        $stylesheet = self::HEADER . "\n\n" . implode("\n", $parts);

        return [$this->root . '/public/assets/aic.css' => $stylesheet, ...$copies];
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
            throw new InvalidArgumentException($source . ': cannot read declared asset.');
        }

        return $contents;
    }
}
