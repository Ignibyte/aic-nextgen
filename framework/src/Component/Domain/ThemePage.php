<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;
use AIC\Component\Contracts\Regions;

/** The page's filled regions; its compiled adapter constructs the authored typed page data. */
final readonly class ThemePage implements Component
{
    /**
     * @param Regions $regions The same filled regions used by targeted fragment rendering.
     * @param string  $layout  The layout the build resolved for the page (#154); empty for the default.
     */
    public function __construct(public Regions $regions, public string $layout = '')
    {
    }
}
