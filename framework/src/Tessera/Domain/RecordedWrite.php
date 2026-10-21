<?php

declare(strict_types=1);

namespace AIC\Tessera\Domain;

/**
 * A write's answer as it was recorded (#72): its status, the values of
 * TesseraWrites' header list in order, and its body. Status 0 is a write
 * claimed and not yet settled, or never settled.
 */
final readonly class RecordedWrite
{
    /**
     * @param int    $status  The answer's status; 0 while it is unsettled.
     * @param string $headers The listed headers' values, one per line, in order.
     * @param string $body    The answer's body.
     */
    public function __construct(
        public int $status,
        public string $headers,
        public string $body,
    ) {
    }

    /**
     * @return bool Whether the first write settled, so its answer can be sent again.
     */
    public function settled(): bool
    {
        return $this->status > 0;
    }
}
