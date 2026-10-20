<?php

declare(strict_types=1);

namespace App\Site;

use JsonSerializable;
use Override;

/**
 * The body of GET /api/status: the app's name, when it booted to serve the
 * request, the request's HTTP version, and the database server's version.
 */
final readonly class StatusBody implements JsonSerializable
{
    /**
     * @param string $app      The app's name.
     * @param string $bootedAt When the app booted to serve the request, ISO 8601 in UTC.
     * @param string $http     The request's HTTP version.
     * @param string $database The database server's version, or `unavailable`.
     */
    public function __construct(
        private string $app,
        private string $bootedAt,
        private string $http,
        private string $database,
    ) {
    }

    /**
     * @return array<string, string> The JSON object.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return ['app' => $this->app, 'bootedAt' => $this->bootedAt, 'http' => $this->http, 'database' => $this->database];
    }
}
