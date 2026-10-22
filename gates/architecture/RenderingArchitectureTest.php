<?php

declare(strict_types=1);

namespace Gates\Architecture;

use PHPat\Selector\Selector;
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
     * `Region`, `Regions` and every `Layout` never depend on `Html`.
     *
     * @return Rule The rule.
     */
    public function testRenderTreeHoldsNoMarkup(): Rule
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
}
