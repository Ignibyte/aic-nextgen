<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Config\Domain\AppConfig;
use AIC\Controller\Application\ApiHandler;
use AIC\Controller\Application\Psr7ResponseWriter;
use AIC\Database\Contracts\Database;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The app's Api controllers, each wrapped in the Api handler, by the handler
 * id config/routes.php names.
 */
final readonly class ApiHandlers
{
    /**
     * @param Psr7ResponseWriter $psr7ResponseWriter Writes the responses.
     * @param AppConfig          $appConfig          The app's settings.
     * @param string             $bootedAt           When the app booted to serve this request, ISO 8601 in UTC.
     * @param Database           $database           The database.
     */
    public function __construct(
        private Psr7ResponseWriter $psr7ResponseWriter,
        private AppConfig $appConfig,
        private string $bootedAt,
        private Database $database,
    ) {
    }

    /**
     * @return array<string, RequestHandlerInterface> The handlers by id.
     */
    public function byId(): array
    {
        $statusApi = new StatusApi($this->appConfig, $this->bootedAt, $this->database);

        return [StatusApi::class => new ApiHandler($statusApi, $this->psr7ResponseWriter)];
    }
}
