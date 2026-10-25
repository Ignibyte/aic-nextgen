<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Badge;
use AIC\Component\Domain\Heading;
use AIC\Component\Domain\Link;
use AIC\Component\Domain\Paragraph;
use AIC\Component\Domain\TrackedAssets;

/** Explicit library component, authored source and compiled class declarations. */
final readonly class TextAtoms
{
    /**
     * @return list<array{class-string<Component>, string, string}> The fixed source basename and output class for each atom.
     */
    public function declarations(): array
    {
        return [
            [Link::class, 'link', 'LinkTemplate'],
            [Badge::class, 'badge', 'BadgeTemplate'],
            [Heading::class, 'heading', 'HeadingTemplate'],
            [Paragraph::class, 'paragraph', 'ParagraphTemplate'],
            [TrackedAssets::class, 'tracked-assets', 'TrackedAssetsTemplate'],
        ];
    }
}
