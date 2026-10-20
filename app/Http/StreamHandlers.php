<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Controller\Application\Psr7ResponseWriter;
use AIC\Controller\Application\StreamHandler;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The app's Stream controllers (the download and the event stream), each
 * wrapped in the Stream handler, by the handler id config/routes.php names.
 */
final readonly class StreamHandlers
{
    /**
     * @param Psr7ResponseWriter $psr7ResponseWriter Writes the responses.
     * @param string             $resources          The app's resources directory.
     */
    public function __construct(
        private Psr7ResponseWriter $psr7ResponseWriter,
        private string $resources,
    ) {
    }

    /**
     * @return array<string, RequestHandlerInterface> The handlers by id.
     */
    public function byId(): array
    {
        return [
            ResourceDownload::class => new StreamHandler(new ResourceDownload($this->resources), $this->psr7ResponseWriter),
            TickEvents::class => new StreamHandler(new TickEvents(), $this->psr7ResponseWriter),
        ];
    }
}
