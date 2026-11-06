<?php

declare(strict_types=1);

namespace App\Theme\Components\Welcome;

use AIC\Component\Contracts\Component;

/**
 * The home page's welcome: the app's name and the path that was asked for.
 * Its markup is components/Welcome/welcome.phtml.
 */
final readonly class Welcome implements Component
{
    /**
     * @param string $name The app's name.
     * @param string $path The request's path.
     */
    public function __construct(
        public string $name,
        public string $path,
    ) {
    }
}
