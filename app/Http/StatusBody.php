<?php

declare(strict_types=1);

namespace App\Http;

use JsonSerializable;
use Override;

/**
 * The body of GET /api/status: the app's name, when this worker booted, and
 * whether the request was served in worker mode.
 */
final readonly class StatusBody implements JsonSerializable
{
    /**
     * @param string $app        The app's name.
     * @param string $bootedAt   When this worker booted, ISO 8601 in UTC.
     * @param bool   $workerMode Whether the request was served in worker mode.
     */
    public function __construct(
        private string $app,
        private string $bootedAt,
        private bool $workerMode,
    ) {
    }

    /**
     * @return array<string, string|bool> The JSON object.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['app' => $this->app, 'bootedAt' => $this->bootedAt, 'workerMode' => $this->workerMode];
    }
}
