<?php

declare(strict_types=1);

namespace AIC\Broadcast\Domain;

use InvalidArgumentException;

use function min;

/**
 * How broadcast streams use the site's workers (#314), `AppConfig::$broadcast`.
 *
 * Each open stream holds one of the compiled site's PHP children (`--workers`
 * times `--handler-concurrency`; a php-fpm child under PHP), so a stream ends
 * its connection after `holdSeconds` and the client reconnects with the last
 * id it saw, and at most `streams` are open at once. Set it below one
 * worker's `--handler-concurrency`, not below the total: connections do not
 * spread evenly, and a page that lands on a worker whose children all hold
 * streams waits (#314 measured 15 open streams stalling a page on 2 workers
 * of 8). The default, 7, is that for AIC's own compiled site.
 */
final readonly class BroadcastConfig
{
    /**
     * @param int $holdSeconds      How long one connection lasts before it ends as a reconnect, 1 or more.
     * @param int $keepAliveSeconds How long a stream waits idle before a keep-alive comment, 1 to 15 (Tessera asks
     *                              for one at least every 15 seconds).
     * @param int $streams          How many broadcast streams may be open at once across the site, 1 or more.
     * @param int $refusedRetryMs   When a stream over the cap is told to come back, in milliseconds, 1 or more.
     * @param int $keepSeconds      How long a published event is kept for a reconnect to replay, 1 or more.
     *
     * @throws InvalidArgumentException When a value is out of its range.
     */
    public function __construct(
        public int $holdSeconds = 25,
        public int $keepAliveSeconds = 15,
        public int $streams = 7,
        public int $refusedRetryMs = 5_000,
        public int $keepSeconds = 3_600,
    ) {
        if (
            min($holdSeconds, $keepAliveSeconds, $streams, $refusedRetryMs, $keepSeconds) < 1
            || $keepAliveSeconds > 15
        ) {
            throw new InvalidArgumentException('BroadcastConfig: every value is 1 or more, and the keep-alive at most 15 seconds.');
        }
    }
}
