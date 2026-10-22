<?php

declare(strict_types=1);

namespace App\Site\Component;

use AIC\Component\Application\RenderPass;
use AIC\Component\Domain\KeyedComponent;

/**
 * SiteNav's markup, compiled from SiteNav.html.php by bin/aic-build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class SiteNavTemplate
{
    /**
     * @param SiteNav    $siteNav    The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @return string Its markup.
     */
    public function render(SiteNav $siteNav, RenderPass $renderPass): string
    {
        return $this->part2($siteNav, $renderPass);
    }

    /**
     * @param RenderPass     $renderPass     The pass that writes its children.
     * @param KeyedComponent $keyedComponent A loop's variable.
     *
     * @return string Markup from SiteNav.html.php.
     */
    private function part0(RenderPass $renderPass, KeyedComponent $keyedComponent): string
    {
        return $renderPass->child($keyedComponent, 'SiteNav.1:' . $keyedComponent->key());
    }

    /**
     * @param SiteNav    $siteNav    The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @return string Markup from SiteNav.html.php.
     */
    private function part1(SiteNav $siteNav, RenderPass $renderPass): string
    {
        $markup = '';

        foreach ($siteNav->links as $keyedComponent) {
            $markup .= $this->part0($renderPass, $keyedComponent);
        }

        return $markup;
    }

    /**
     * @param SiteNav    $siteNav    The component.
     * @param RenderPass $renderPass The pass that writes its children.
     *
     * @return string Markup from SiteNav.html.php.
     */
    private function part2(SiteNav $siteNav, RenderPass $renderPass): string
    {
        return '<nav id="site-nav" aria-label="Demos">'
            . '<!--t:b-->'
            . $this->part1($siteNav, $renderPass)
            . '<!--/t:b-->'
            . '</nav>';
    }
}
