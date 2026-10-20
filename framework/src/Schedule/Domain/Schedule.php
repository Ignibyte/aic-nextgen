<?php

declare(strict_types=1);

namespace AIC\Schedule\Domain;

/**
 * One schedule (#47): a job queued with its payload whenever the cadence
 * matches a minute.
 */
final readonly class Schedule
{
    /**
     * @param string                $name    Its name, unique in the app; the ledger's key.
     * @param string                $job     The job's name in the registry.
     * @param array<string, string> $payload The job's payload.
     * @param Cadence               $cadence When it fires.
     */
    public function __construct(
        public string $name,
        public string $job,
        public array $payload,
        public Cadence $cadence,
    ) {
    }
}
