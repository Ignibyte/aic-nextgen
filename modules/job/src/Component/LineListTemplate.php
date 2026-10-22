<?php

declare(strict_types=1);

namespace App\Job\Component;

use AIC\Component\Domain\RendersMarkup;

/**
 * LineList's markup, compiled from LineList.html.php by bin/aic-build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class LineListTemplate
{
    use RendersMarkup;

    /**
     * @param LineList $lineList The component.
     *
     * @return string Its markup.
     */
    public function render(LineList $lineList): string
    {
        return $this->part4($lineList);
    }

    /**
     * @param LineList $lineList The component.
     *
     * @return string Markup from LineList.html.php.
     */
    private function part0(LineList $lineList): string
    {
        return '<p id="'
            . self::text($lineList->id)
            . '">'
            . self::text($lineList->none)
            . '</p>';
    }

    /**
     * @param string $line A loop's variable.
     *
     * @return string Markup from LineList.html.php.
     */
    private function part1(string $line): string
    {
        return '<li>'
            . self::text($line)
            . '</li>';
    }

    /**
     * @param LineList $lineList The component.
     *
     * @return string Markup from LineList.html.php.
     */
    private function part2(LineList $lineList): string
    {
        $markup = '';

        foreach ($lineList->lines as $line) {
            $markup .= $this->part1($line);
        }

        return $markup;
    }

    /**
     * @param LineList $lineList The component.
     *
     * @return string Markup from LineList.html.php.
     */
    private function part3(LineList $lineList): string
    {
        return '<ul id="'
            . self::text($lineList->id)
            . '">'
            . '<!--t:b-->'
            . $this->part2($lineList)
            . '<!--/t:b-->'
            . '</ul>';
    }

    /**
     * @param LineList $lineList The component.
     *
     * @return string Markup from LineList.html.php.
     */
    private function part4(LineList $lineList): string
    {
        return '<!--t:b-->'
            . ($lineList->lines === []
                ? $this->part0($lineList)
                : $this->part3($lineList))
            . '<!--/t:b-->';
    }
}
