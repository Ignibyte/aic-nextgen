<?php

declare(strict_types=1);

namespace App\Site;

use AIC\Config\Domain\AppConfig;
use AIC\Controller\Application\Psr7ResponseWriter;
use AIC\Database\Contracts\Database;
use Psr\Http\Server\RequestHandlerInterface;

use function array_merge;

/**
 * The site's Api and Stream controllers together (ApiHandlers and
 * StreamHandlers), by the handler id config/routes.php names.
 */
final readonly class SiteHandlers
{
    /**
     * @param Psr7ResponseWriter $psr7ResponseWriter Writes the responses.
     * @param AppConfig          $appConfig          The app's settings.
     * @param string             $bootedAt           When the app booted to serve this request, ISO 8601 in UTC.
     * @param string             $root               The app's root directory.
     * @param Database           $database           The database.
     */
    public function __construct(
        private Psr7ResponseWriter $psr7ResponseWriter,
        private AppConfig $appConfig,
        private string $bootedAt,
        private string $root,
        private Database $database,
    ) {
    }

    /**
     * @return array<string, RequestHandlerInterface> The handlers by id.
     */
    public function byId(): array
    {
        $apiHandlers = new ApiHandlers($this->psr7ResponseWriter, $this->appConfig, $this->bootedAt, $this->database);
        $streamHandlers = new StreamHandlers($this->psr7ResponseWriter, $this->root);

        return array_merge($apiHandlers->byId(), $streamHandlers->byId());
    }
}
