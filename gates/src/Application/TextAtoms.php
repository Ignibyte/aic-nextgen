<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Badge;
use AIC\Component\Domain\Heading;
use AIC\Component\Domain\Link;
use AIC\Component\Domain\Paragraph;
use AIC\Component\Domain\TrackedAssets;

/** Explicit library component and authored source declarations. */
final readonly class TextAtoms
{
    /**
     * @return list<array{class-string<Component>, string}> Each atom with its fixed source basename.
     */
    public function declarations(): array
    {
        return [
            [Link::class, 'link'],
            [Badge::class, 'badge'],
            [Heading::class, 'heading'],
            [Paragraph::class, 'paragraph'],
            [TrackedAssets::class, 'tracked-assets'],
        ];
    }
}
