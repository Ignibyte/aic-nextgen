<?php

declare(strict_types=1);

namespace App\Scaffold\Service;

/**
 * One delivery for the job `scaffold-delivery-webhook`, made by bin/aic
 * make:webhook (#321): the raw body its sender signed, carried to the job in
 * its payload. Read the fields the job needs from it here.
 */
final readonly class ScaffoldDeliveryPayload
{
    /**
     * @param string $body The delivery's body.
     */
    public function __construct(
        public string $body,
    ) {
    }

    /**
     * @return array{body: string} The job's payload.
     */
    public function values(): array
    {
        return ['body' => $this->body];
    }
}
