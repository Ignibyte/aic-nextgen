<?php

declare(strict_types=1);

namespace AIC\Mcp\Application;

use AIC\Access\Application\Actors;
use AIC\Controller\Contracts\EventSource;
use AIC\Controller\Contracts\ServerEvent;
use AIC\Database\Contracts\QueryException;
use AIC\Mcp\Domain\McpCatalog;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function array_shift;
use function count;
use function explode;
use function implode;

/**
 * MCP's GET stream (#333): the `aic.mcp` channel's events, with a
 * `notifications/<list>/list_changed` put ahead of a keep-alive or the
 * connection's end for each list the caller sees differently. At every
 * comment (the first event, each keep-alive, the reconnect) it resolves the
 * caller again and prints its lists; every id it sends carries the prints
 * (`<n>:<tools>-<resources>-<prompts>`), so a reconnect compares against what
 * the client last heard.
 */
final class McpListEvents implements EventSource
{
    /**
     * Notifications made, not sent yet.
     *
     * @var list<ServerEvent>
     */
    private array $queued = [];

    /**
     * @param EventSource            $eventSource   The channel's events.
     * @param Actors                 $actors        Resolves the caller again at each check.
     * @param ServerRequestInterface $serverRequest The stream's request, its token or session.
     * @param McpCatalog             $mcpCatalog    The lists.
     * @param string                 $after         The last channel id the client saw; empty for none.
     * @param string                 $prints        The prints the client last heard; empty for none.
     * @param McpLists               $mcpLists      Prints the lists.
     */
    public function __construct(
        private readonly EventSource $eventSource,
        private readonly Actors $actors,
        private readonly ServerRequestInterface $serverRequest,
        private readonly McpCatalog $mcpCatalog,
        private string $after,
        private string $prints,
        private readonly McpLists $mcpLists = new McpLists(),
    ) {
    }

    /**
     * @throws QueryException When the caller or the channel cannot be read.
     *
     * @return ServerEvent A list change first, when one is waiting; the channel's next event otherwise, its id
     *                     carrying the prints, a keep-alive it stood in for dropped.
     */
    #[Override]
    public function next(): ServerEvent
    {
        $queued = array_shift($this->queued);

        if ($queued instanceof ServerEvent) {
            return $queued;
        }

        $serverEvent = $this->eventSource->next();

        if ($serverEvent->name === '') {
            $this->compared();
        }

        $serverEvent = $this->stamped($serverEvent);
        $first = array_shift($this->queued);

        if (!$first instanceof ServerEvent) {
            return $serverEvent;
        }

        if ($serverEvent->last) {
            $this->queued[] = $serverEvent;
        }

        return $first;
    }

    /**
     * Prints the caller's lists and queues a `list_changed` for each print that moved since the client last heard.
     *
     * @throws QueryException When the caller cannot be read.
     */
    private function compared(): void
    {
        $prints = $this->mcpLists->prints($this->mcpCatalog, $this->actors->actor($this->serverRequest));
        $heard = explode('-', $this->prints);
        $this->prints = implode('-', $prints);

        if (count($heard) !== count($prints)) {
            return;
        }

        foreach (McpLists::KEYS as $index => $key) {
            if (($heard[$index] ?? '') === ($prints[$index] ?? '')) {
                continue;
            }

            $notification = '{"jsonrpc":"2.0","method":"notifications/' . $key . '/list_changed"}';
            $this->queued[] = $this->stamped(new ServerEvent('message', $notification, false, $this->after));
        }
    }

    /**
     * @param ServerEvent $serverEvent An event.
     *
     * @return ServerEvent It, its id (when it has one, or the last one seen for a list change) followed by the prints.
     */
    private function stamped(ServerEvent $serverEvent): ServerEvent
    {
        if ($serverEvent->id === '') {
            return $serverEvent;
        }

        $this->after = $serverEvent->id;

        return new ServerEvent(
            $serverEvent->name,
            $serverEvent->data,
            $serverEvent->last,
            $serverEvent->id . ':' . $this->prints,
            $serverEvent->elements,
            $serverEvent->resumes,
            $serverEvent->retry,
        );
    }
}
