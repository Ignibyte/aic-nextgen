<?php

declare(strict_types=1);

namespace AIC\Module\Contracts;

/**
 * A schedule a module declares (#93): a job queued with its payload whenever
 * the cron cadence is due. The build turns it into the framework's `Schedule`.
 *
 * Manifests construct it; nothing in the framework does, so it is API for them.
 *
 * @api
 */
final readonly class ModuleSchedule implements ModuleDeclaration
{
    /**
     * @param string                $name    The schedule's name, unique in the app (`demo-every-minute`).
     * @param string                $job     The name of the job it queues.
     * @param array<string, string> $payload The payload the job is queued with.
     * @param string                $cadence A five-field UTC cron expression (`* * * * *`).
     */
    public function __construct(
        public string $name,
        public string $job,
        public array $payload,
        public string $cadence,
    ) {
    }
}
