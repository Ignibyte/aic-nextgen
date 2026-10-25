<?php

declare(strict_types=1);

namespace AIC\Module\Contracts;

use AIC\Component\Contracts\Component;

/** The site module's explicit selection of document, page, regions and theme assets. */
final readonly class ModuleTheme implements ModuleDeclaration
{
    /**
     * @param string                  $name     Its theme folder and stable Tessera layout identity.
     * @param string                  $html     The document template relative to themes/<name>/.
     * @param string                  $page     The page template relative to themes/<name>/.
     * @param class-string<Component> $pageData The authored data class with public Region properties.
     * @param array<string, string>   $regions  Page property names mapped to stable region names, in constructor order.
     * @param list<ModuleAsset>       $assets   Explicit public assets relative to the selected theme folder.
     */
    public function __construct(
        public string $name,
        public string $html,
        public string $page,
        public string $pageData,
        public array $regions,
        public array $assets = [],
    ) {
    }
}
