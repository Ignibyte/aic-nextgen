<?php

declare(strict_types=1);

namespace App\Site\Service;

use JsonSerializable;
use Override;

/**
 * GET /health's body: the app's state, the database's, and the HTTP version.
 */
final readonly class HealthBody implements JsonSerializable
{
    /**
     * @param bool   $databaseUp Whether the database answered.
     * @param string $http       The HTTP version the check came over.
     */
    public function __construct(
        private bool $databaseUp,
        private string $http,
    ) {
    }

    /**
     * @return array<string, string> `status`, ok or degraded; `database`, up or down; `http`.
     */
    #[Override]
    public function jsonSerialize(): array
    {
        return $this->databaseUp
            ? ['database' => 'up', 'http' => $this->http, 'status' => 'ok']
            : ['database' => 'down', 'http' => $this->http, 'status' => 'degraded'];
    }
}
