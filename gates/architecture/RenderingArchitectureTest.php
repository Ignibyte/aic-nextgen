<?php

declare(strict_types=1);

namespace Gates\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * gate:9 — the render tree never depends on markup (#99): the Renderer makes
 * HTML from regions and layouts, which hold components, so neither names
 * `Html` in a parameter, a return or a property.
 */
final class RenderingArchitectureTest
{
    /**
     * @return array<string, Rule> Every architecture rule under its existing name.
     */
    #[TestRule]
    public function __invoke(): array
    {
        return [
            'testOnlyTheGeneratedEnumNamesLayouts' => $this->testOnlyTheGeneratedEnumNamesLayouts(),
            'testRenderTreeHoldsNoMarkup' => $this->testRenderTreeHoldsNoMarkup(),
            'testStreamEventsHoldNoMarkup' => $this->testStreamEventsHoldNoMarkup(),
        ];
    }

    /**
     * `Region`, `Regions` and every `Layout` never depend on `Html`.
     *
     * @return Rule The rule.
     */
    private function testRenderTreeHoldsNoMarkup(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::classname('/^AIC\\\\Component\\\\Contracts\\\\Regions?$/', true),
                Selector::implements('/^AIC\\\\Component\\\\Contracts\\\\Layout$/', true),
                Selector::classname('/^AIC\\\\Component\\\\Contracts\\\\Layout$/', true),
            )
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::classname('/^AIC\\\\Component\\\\Contracts\\\\Html$/', true))
            ->because('a page is components; only the Renderer turns them into Html (#87).');
    }

    /**
     * Only the build's `ThemeLayoutName` names a layout (#154): a hand-written name would fall back to the default
     * layout without a word, where the generated enum has a case only for a layout the theme ships.
     *
     * @return Rule The rule.
     */
    private function testOnlyTheGeneratedEnumNamesLayouts(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App'), Selector::inNamespace('AIC'))
            ->excluding(Selector::classname('/^App\\\\Generated\\\\ThemeLayoutName$/', true))
            ->shouldNot()
            ->implement()
            ->classes(Selector::classname('/^AIC\\\\Component\\\\Contracts\\\\LayoutName$/', true))
            ->because('the build generates the one layout name enum, a case per layout the theme ships (#154).');
    }

    /**
     * A stream's events never carry `Html`: an element patch carries a component, which the response writer renders
     * when the event goes on the wire (#115).
     *
     * @return Rule The rule.
     */
    private function testStreamEventsHoldNoMarkup(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::classname('/^AIC\\\\Controller\\\\Contracts\\\\ServerEvent$/', true),
                Selector::classname('/^AIC\\\\Tessera\\\\Domain\\\\TesseraEvents$/', true),
                Selector::implements('/^AIC\\\\Controller\\\\Contracts\\\\EventSource$/', true),
            )
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::classname('/^AIC\\\\Component\\\\Contracts\\\\Html$/', true))
            ->because('an element patch carries a component; only the response writer renders it (#115).');
    }
}
