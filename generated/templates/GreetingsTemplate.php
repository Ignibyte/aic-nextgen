<?php

declare(strict_types=1);

namespace App\Generated\Templates;

use AIC\Component\Domain\RendersMarkup;
use App\Theme\Components\Greetings\Greetings;

/**
 * Greetings's markup, compiled from
 * themes/app/components/Greetings/greetings.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class GreetingsTemplate
{
    use RendersMarkup;

    /**
     * @param Greetings $greetings The component.
     *
     * @return string Its markup.
     */
    public function render(Greetings $greetings): string
    {
        return $this->part2($greetings);
    }

    /**
     * @param string $greeting A loop's variable.
     *
     * @return string Markup from themes/app/components/Greetings/greetings.tpl.php.
     */
    private function part0(string $greeting): string
    {
        return '<li>'
            . self::text($greeting)
            . '</li>';
    }

    /**
     * @param Greetings $greetings The component.
     *
     * @return string Markup from themes/app/components/Greetings/greetings.tpl.php.
     */
    private function part1(Greetings $greetings): string
    {
        $markup = '';

        foreach ($greetings->greetings as $greeting) {
            $markup .= $this->part0($greeting);
        }

        return $markup;
    }

    /**
     * @param Greetings $greetings The component.
     *
     * @return string Markup from themes/app/components/Greetings/greetings.tpl.php.
     */
    private function part2(Greetings $greetings): string
    {
        return '<ul id="greetings">'
            . '<!--t:b-->'
            . $this->part1($greetings)
            . '<!--/t:b-->'
            . "</ul>\n";
    }
}
