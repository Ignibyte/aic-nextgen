<?php

declare(strict_types=1);

namespace AIC\Component\Domain;

use AIC\Component\Contracts\Component;

/**
 * A message for the visitor, announced to assistive technology (`role="alert"`).
 */
final readonly class Alert implements Component
{
    /**
     * @param AlertSeverity $alertSeverity How serious it is.
     * @param string        $message       The message.
     * @param string        $id            The element id; empty for none.
     */
    public function __construct(
        public AlertSeverity $alertSeverity,
        public string $message,
        public string $id,
    ) {
    }
}
