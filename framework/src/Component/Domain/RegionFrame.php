<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Frame;

/**
 * A region's element around its children's markup (#37, #155), written by `templates/tessera/region.phtml`.
 */
final readonly class RegionFrame implements Frame
{
    /**
     * @param string $name   The region's name, its `data-region`.
     * @param string $markup Its children's rendered markup, markers included.
     */
    public function __construct(
        public string $name,
        public string $markup,
    ) {
    }
}
