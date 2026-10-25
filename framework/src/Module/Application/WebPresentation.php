<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Component\Application\ComponentTemplates;
use AIC\Component\Contracts\PageLayouts;
use AIC\Http\Contracts\PublicAssets;

/** The two build-owned presentation catalogs, without application provider construction. */
final readonly class WebPresentation
{
    /**
     * @param ComponentTemplates $componentTemplates The compiled component template lookup.
     * @param PublicAssets       $publicAssets       The compiled public representation lookup.
     * @param PageLayouts|null   $pageLayouts        The compiled selected theme, absent only in the bounded legacy transition.
     */
    public function __construct(
        public ComponentTemplates $componentTemplates,
        public PublicAssets $publicAssets,
        public ?PageLayouts $pageLayouts = null,
    ) {
    }
}
