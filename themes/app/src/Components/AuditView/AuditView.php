<?php

declare(strict_types=1);

namespace App\Theme\Components\AuditView;

use AIC\Component\Contracts\Component;

/**
 * AuditView, made by bin/aic make:page (#122) for AuditPage: its title, shown by its template.
 */
final readonly class AuditView implements Component
{
    /**
     * @param string $title What it shows.
     */
    public function __construct(
        public string $title,
    ) {
    }
}
