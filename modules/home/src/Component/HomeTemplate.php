<?php

declare(strict_types=1);

namespace App\Home\Component;

use AIC\Component\Application\RenderPass;
use AIC\Component\Domain\RendersMarkup;
use App\Home\Entity\HomeDemo;
use App\Home\Entity\HomeFact;

/**
 * Home's markup, compiled from Home.html.php by bin/aic-build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class HomeTemplate
{
    use RendersMarkup;

    /**
     * @param Home       $home       The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @return string Its markup.
     */
    public function render(Home $home, RenderPass $renderPass): string
    {
        return $this->part9($home, $renderPass);
    }

    /**
     * @param HomeDemo $homeDemo A loop's variable.
     *
     * @return string Markup from Home.html.php.
     */
    private function part0(HomeDemo $homeDemo): string
    {
        return '<a class="aic-link" href="'
            . self::text($homeDemo->path)
            . '" data-enhance="off">'
            . self::text($homeDemo->label)
            . '</a>';
    }

    /**
     * @param HomeDemo $homeDemo A loop's variable.
     *
     * @return string Markup from Home.html.php.
     */
    private function part1(HomeDemo $homeDemo): string
    {
        return '<a class="aic-link" href="'
            . self::text($homeDemo->path)
            . '">'
            . self::text($homeDemo->label)
            . '</a>';
    }

    /**
     * @param HomeDemo $homeDemo A loop's variable.
     *
     * @return string Markup from Home.html.php.
     */
    private function part2(HomeDemo $homeDemo): string
    {
        return '<li>'
            . '<!--t:b-->'
            . ($homeDemo->plain
                ? $this->part0($homeDemo)
                : $this->part1($homeDemo))
            . '<!--/t:b-->'
            . '</li>';
    }

    /**
     * @param Home $home The component.
     *
     * @return string Markup from Home.html.php.
     */
    private function part3(Home $home): string
    {
        $markup = '';

        foreach ($home->demos as $homeDemo) {
            $markup .= $this->part2($homeDemo);
        }

        return $markup;
    }

    /**
     * @param HomeFact $homeFact A loop's variable.
     *
     * @return string Markup from Home.html.php.
     */
    private function part4(HomeFact $homeFact): string
    {
        return '<dt>'
            . self::text($homeFact->term)
            . '</dt><dd id="'
            . self::text($homeFact->id)
            . '">'
            . self::text($homeFact->value)
            . '</dd>';
    }

    /**
     * @param Home $home The component.
     *
     * @return string Markup from Home.html.php.
     */
    private function part5(Home $home): string
    {
        $markup = '';

        foreach ($home->facts as $homeFact) {
            $markup .= $this->part4($homeFact);
        }

        return $markup;
    }

    /**
     * @param string $pair A loop's variable.
     *
     * @return string Markup from Home.html.php.
     */
    private function part6(string $pair): string
    {
        return '<li>'
            . self::text($pair)
            . '</li>';
    }

    /**
     * @param Home $home The component.
     *
     * @return string Markup from Home.html.php.
     */
    private function part7(Home $home): string
    {
        $markup = '';

        foreach ($home->query as $pair) {
            $markup .= $this->part6($pair);
        }

        return $markup;
    }

    /**
     * @param Home       $home       The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @return string Markup from Home.html.php.
     */
    private function part8(Home $home, RenderPass $renderPass): string
    {
        return '<!--/t:b-->'
            . '</ul>'
            . $renderPass->child($home->form, 'Home.1');
    }

    /**
     * @param Home       $home       The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @return string Markup from Home.html.php.
     */
    private function part9(Home $home, RenderPass $renderPass): string
    {
        return '<h2>Demos</h2><ul id="demos">'
            . '<!--t:b-->'
            . $this->part3($home)
            . '<!--/t:b-->'
            . '</ul><h2>This request</h2><p>The AIC kernel is serving this request.</p><dl>'
            . '<!--t:b-->'
            . $this->part5($home)
            . '<!--/t:b-->'
            . '</dl><ul id="query">'
            . '<!--t:b-->'
            . $this->part7($home)
            . $this->part8($home, $renderPass);
    }
}
