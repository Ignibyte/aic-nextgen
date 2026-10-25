<?php

declare(strict_types=1);

namespace App\Generated\Templates;

use AIC\Component\Application\RenderPass;
use AIC\Component\Domain\RendersMarkup;
use AIC\Component\Exception\UnknownComponent;
use App\Theme\Components\Home\Home;
use App\Theme\Components\Home\HomeDemo;
use App\Theme\Components\Home\HomeFact;

/**
 * Home's markup, compiled from
 * themes/app/components/Home/home.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class HomeTemplate
{
    use RendersMarkup;

    /**
     * @param Home       $home       The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Its markup.
     */
    public function render(Home $home, RenderPass $renderPass): string
    {
        return $this->part8($home, $renderPass);
    }

    /**
     * @return string Markup from themes/app/components/Home/home.tpl.php.
     */
    private function part0(): string
    {
        return ' data-enhance="off"';
    }

    /**
     * @param HomeDemo $homeDemo A loop's variable.
     *
     * @return string Markup from themes/app/components/Home/home.tpl.php.
     */
    private function part1(HomeDemo $homeDemo): string
    {
        return '<li><a class="aic-link" href="'
            . self::text($homeDemo->path)
            . '"'
            . ($homeDemo->plain
                ? $this->part0()
                : '')
            . '>'
            . self::text($homeDemo->label)
            . '</a></li>';
    }

    /**
     * @param Home $home The component.
     *
     * @return string Markup from themes/app/components/Home/home.tpl.php.
     */
    private function part2(Home $home): string
    {
        $markup = '';

        foreach ($home->demos as $homeDemo) {
            $markup .= $this->part1($homeDemo);
        }

        return $markup;
    }

    /**
     * @param HomeFact $homeFact A loop's variable.
     *
     * @return string Markup from themes/app/components/Home/home.tpl.php.
     */
    private function part3(HomeFact $homeFact): string
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
     * @return string Markup from themes/app/components/Home/home.tpl.php.
     */
    private function part4(Home $home): string
    {
        $markup = '';

        foreach ($home->facts as $homeFact) {
            $markup .= $this->part3($homeFact);
        }

        return $markup;
    }

    /**
     * @param string $pair A loop's variable.
     *
     * @return string Markup from themes/app/components/Home/home.tpl.php.
     */
    private function part5(string $pair): string
    {
        return '<li>'
            . self::text($pair)
            . '</li>';
    }

    /**
     * @param Home $home The component.
     *
     * @return string Markup from themes/app/components/Home/home.tpl.php.
     */
    private function part6(Home $home): string
    {
        $markup = '';

        foreach ($home->query as $pair) {
            $markup .= $this->part5($pair);
        }

        return $markup;
    }

    /**
     * @param Home       $home       The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Markup from themes/app/components/Home/home.tpl.php.
     */
    private function part7(Home $home, RenderPass $renderPass): string
    {
        return '<!--/t:b-->'
            . '</ul>'
            . $renderPass->child($home->form, 'Home.1');
    }

    /**
     * @param Home       $home       The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @throws UnknownComponent When a child has no registered template.
     *
     * @return string Markup from themes/app/components/Home/home.tpl.php.
     */
    private function part8(Home $home, RenderPass $renderPass): string
    {
        return '<h2>Demos</h2><ul id="demos">'
            . '<!--t:b-->'
            . $this->part2($home)
            . '<!--/t:b-->'
            . '</ul><h2>This request</h2><p>The AIC kernel is serving this request.</p><dl>'
            . '<!--t:b-->'
            . $this->part4($home)
            . '<!--/t:b-->'
            . '</dl><ul id="query">'
            . '<!--t:b-->'
            . $this->part6($home)
            . $this->part7($home, $renderPass);
    }
}
