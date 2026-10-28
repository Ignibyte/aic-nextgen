<?php

declare(strict_types=1);

namespace App\Theme\Components\Panel;

use AIC\Component\Contracts\Component;

/**
 * A titled panel around one component, from panel.phtml (#194): a keyed
 * component inside it sits at a positional path, and still carries its props
 * and anchor.
 */
final readonly class Panel implements Component
{
    /**
     * @param string    $title The panel's heading.
     * @param Component $body  What it holds.
     */
    public function __construct(
        public string $title,
        public Component $body,
    ) {
    }
}
