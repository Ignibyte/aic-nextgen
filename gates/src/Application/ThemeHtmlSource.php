<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Application\RenderPass;
use AIC\Component\Domain\ThemeLayout;
use AIC\Component\Exception\UnknownComponent;
use Gates\Domain\TemplateBinding;
use Gates\Domain\ThemeBinding;
use InvalidArgumentException;

use function implode;
use function strlen;
use function strtr;
use function substr;
use function var_export;

/**
 * The theme's html adapter (#154, #155): `ThemeHtmlTemplate` chooses the document template of the layout a page was
 * given, `html.phtml` through the `default` arm when the layout ships no html of its own, and requires it. It holds a
 * lookup, never markup.
 */
final readonly class ThemeHtmlSource
{
    /** The html adapter: renders the layout's document template. */
    private const string HTML = <<<'PHP'
        final readonly class ThemeHtmlTemplate
        {
            /**
             * @param ThemeLayout $themeLayout The document.
             * @param RenderPass  $renderPass  The pass that writes its children.
             *
             * @throws UnknownComponent When a child has no registered template.
             *
             * @return string The layout's document markup; html.phtml's when it ships no html of its own.
             */
            public function render(ThemeLayout $themeLayout, RenderPass $renderPass): string
            {
                return match ($themeLayout->page->layout) {
        {arms}            default => $this->renderHtml($themeLayout, $renderPass),
                };
            }

        {methods}}
        PHP;

    /**
     * @param PhpClassFile          $phpClassFile          Emits checked generated source.
     * @param TemplateRequireSource $templateRequireSource Writes each document template's render method.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
        private TemplateRequireSource $templateRequireSource = new TemplateRequireSource(),
    ) {
    }

    /**
     * @param ThemeBinding $themeBinding The theme.
     *
     * @throws InvalidArgumentException When generated imports or structure conflict.
     *
     * @return string `ThemeHtmlTemplate`, rendering each layout's document or `html.phtml`.
     */
    public function htmlTemplate(ThemeBinding $themeBinding): string
    {
        $arms = '';
        $methods = [$this->renderMethod($themeBinding->html, 'renderHtml')];
        $classes = [ThemeLayout::class, RenderPass::class, UnknownComponent::class];

        foreach ($themeBinding->variants as $variant) {
            $html = $variant->html;

            if ($html === null) {
                continue;
            }

            $method = 'renderHtml' . $variant->case;
            $arms .= '            ' . var_export($variant->name, true) . ' => $this->' . $method . "(\$themeLayout, \$renderPass),\n";
            $methods[] = $this->renderMethod($html, $method);
        }

        $source = strtr(self::HTML, ['{arms}' => $arms, '{methods}' => implode("\n", $methods)]);

        $docblock = $this->phpClassFile->generated('the configured theme (#154)', 'the document adapter.');

        return $this->phpClassFile->file('App\\Generated', $classes, [], $docblock, $source);
    }

    /**
     * @param TemplateBinding $templateBinding A document template.
     * @param string          $method          Its render method's name.
     *
     * @return string The method that requires its escaped template.
     */
    private function renderMethod(TemplateBinding $templateBinding, string $method): string
    {
        $path = substr($templateBinding->generated, strlen('generated/'));

        return $this->templateRequireSource->method(ThemeLayout::class, $path, $method);
    }
}
