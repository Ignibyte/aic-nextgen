<?php

declare(strict_types=1);

namespace App\Content\Component;

use AIC\Component\Application\RenderPass;
use AIC\Component\Domain\KeyedComponent;

/**
 * ContentPages's markup, compiled from ContentPages.html.php by bin/aic-build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class ContentPagesTemplate
{
    /**
     * @param ContentPages $contentPages The component.
     * @param RenderPass   $renderPass   The pass that writes its children.
     *
     * @return string Its markup.
     */
    public function render(ContentPages $contentPages, RenderPass $renderPass): string
    {
        return $this->part4($contentPages, $renderPass);
    }

    /**
     * @return string Markup from ContentPages.html.php.
     */
    private function part0(): string
    {
        return '<p id="content-pages">None yet.</p>';
    }

    /**
     * @param RenderPass     $renderPass     The pass that writes its children.
     * @param KeyedComponent $keyedComponent A loop's variable.
     *
     * @return string Markup from ContentPages.html.php.
     */
    private function part1(RenderPass $renderPass, KeyedComponent $keyedComponent): string
    {
        return '<li>'
            . $renderPass->child($keyedComponent, 'ContentPages.1:' . $keyedComponent->key())
            . '</li>';
    }

    /**
     * @param ContentPages $contentPages The component.
     * @param RenderPass   $renderPass   The pass that writes its children.
     *
     * @return string Markup from ContentPages.html.php.
     */
    private function part2(ContentPages $contentPages, RenderPass $renderPass): string
    {
        $markup = '';

        foreach ($contentPages->links as $keyedComponent) {
            $markup .= $this->part1($renderPass, $keyedComponent);
        }

        return $markup;
    }

    /**
     * @param ContentPages $contentPages The component.
     * @param RenderPass   $renderPass   The pass that writes its children.
     *
     * @return string Markup from ContentPages.html.php.
     */
    private function part3(ContentPages $contentPages, RenderPass $renderPass): string
    {
        return '<ul id="content-pages">'
            . '<!--t:b-->'
            . $this->part2($contentPages, $renderPass)
            . '<!--/t:b-->'
            . '</ul>';
    }

    /**
     * @param ContentPages $contentPages The component.
     * @param RenderPass   $renderPass   The pass that writes its children.
     *
     * @return string Markup from ContentPages.html.php.
     */
    private function part4(ContentPages $contentPages, RenderPass $renderPass): string
    {
        return '<!--t:b-->'
            . ($contentPages->links === []
                ? $this->part0()
                : $this->part3($contentPages, $renderPass))
            . '<!--/t:b-->';
    }
}
