<?php

declare(strict_types=1);

namespace App\Faq\Service;

use App\Faq\Entity\FaqEntry;

/**
 * Where the questions come from; the manifest binds it to `DemoFaqEntries`.
 */
interface FaqEntries
{
    /**
     * @return list<FaqEntry> The questions, in the order they are shown.
     */
    public function all(): array;
}
