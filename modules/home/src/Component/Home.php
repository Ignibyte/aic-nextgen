<?php

declare(strict_types=1);

namespace App\Home\Component;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\Form;
use App\Home\Entity\HomeDemo;
use App\Home\Entity\HomeFact;

/**
 * The home page, from Home.html.php: every demo, what the kernel knows of
 * this request, its query, and the greeting form.
 */
final readonly class Home implements Component
{
    /**
     * @param list<HomeDemo> $demos The demos, in order.
     * @param list<HomeFact> $facts The app's name, when it booted, and the request's method and path.
     * @param list<string>   $query The query string's pairs, decoded.
     * @param Form           $form  The greeting form.
     */
    public function __construct(
        public array $demos,
        public array $facts,
        public array $query,
        public Form $form,
    ) {
    }
}
