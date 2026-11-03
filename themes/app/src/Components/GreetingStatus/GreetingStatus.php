<?php

declare(strict_types=1);

namespace App\Theme\Components\GreetingStatus;

use AIC\Component\Contracts\Component;

/**
 * The greeting form's refusals, from greeting-status.phtml (#253): the latest
 * reason and the first one, each read from a page signal a refused answer
 * patches, so the page itself holds no value.
 */
final readonly class GreetingStatus implements Component
{
}
