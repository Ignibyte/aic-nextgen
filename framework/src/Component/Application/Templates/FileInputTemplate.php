<?php

declare(strict_types=1);

namespace AIC\Component\Application\Templates;

use AIC\Component\Domain\FileInput;
use AIC\Component\Domain\RendersMarkup;

/**
 * FileInput's markup, compiled from
 * framework/templates/components/file-input.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class FileInputTemplate
{
    use RendersMarkup;

    /**
     * @param FileInput $fileInput The component.
     *
     * @return string Its markup.
     */
    public function render(FileInput $fileInput): string
    {
        return $this->part3($fileInput);
    }

    /**
     * @param FileInput $fileInput The component.
     *
     * @return string Markup from framework/templates/components/file-input.tpl.php.
     */
    private function part0(FileInput $fileInput): string
    {
        return ' id="'
            . self::text($fileInput->id)
            . '"';
    }

    /**
     * @param FileInput $fileInput The component.
     *
     * @return string Markup from framework/templates/components/file-input.tpl.php.
     */
    private function part1(FileInput $fileInput): string
    {
        return ' name="'
            . self::text($fileInput->name)
            . '"';
    }

    /**
     * @param FileInput $fileInput The component.
     *
     * @return string Markup from framework/templates/components/file-input.tpl.php.
     */
    private function part2(FileInput $fileInput): string
    {
        return ' accept="'
            . self::text($fileInput->accept)
            . '"';
    }

    /**
     * @param FileInput $fileInput The component.
     *
     * @return string Markup from framework/templates/components/file-input.tpl.php.
     */
    private function part3(FileInput $fileInput): string
    {
        return '<input type="file"'
            . ($fileInput->id !== ''
                ? $this->part0($fileInput)
                : '')
            . ($fileInput->name !== ''
                ? $this->part1($fileInput)
                : '')
            . ($fileInput->accept !== ''
                ? $this->part2($fileInput)
                : '')
            . ' class="aic-input">';
    }
}
