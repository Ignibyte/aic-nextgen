<?php

declare(strict_types=1);

namespace AIC\Component\Application\Templates;

use AIC\Component\Domain\RendersMarkup;
use AIC\Component\Domain\TrackedAssets;

/**
 * TrackedAssets's markup, compiled from
 * framework/templates/components/tracked-assets.tpl.php by composer run build (#97, #98):
 * edit the template and run composer run build, never this file.
 */
final readonly class TrackedAssetsTemplate
{
    use RendersMarkup;

    /**
     * @param TrackedAssets $trackedAssets The component.
     *
     * @return string Its markup.
     */
    public function render(TrackedAssets $trackedAssets): string
    {
        return $this->part0($trackedAssets);
    }

    /**
     * @param TrackedAssets $trackedAssets The component.
     *
     * @return string Markup from framework/templates/components/tracked-assets.tpl.php.
     */
    private function part0(TrackedAssets $trackedAssets): string
    {
        return '<link rel="stylesheet" href="'
            . self::text($trackedAssets->stylesheet)
            . '" data-track><script defer src="'
            . self::text($trackedAssets->script)
            . '" data-track></script>';
    }
}
