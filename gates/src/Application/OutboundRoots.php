<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\HttpClient\Contracts\RequestPool;
use AIC\HttpClient\Domain\Upstreams;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * The outbound roots both sides of the app give (#305): the client, the
 * request factory, the pool, the stream factory and the upstreams, each a
 * member of the context's `Outbound`.
 */
final readonly class OutboundRoots
{
    /**
     * @param string $path How the side reaches its `Outbound`: `outbound()->` or `roots()->outbound()->`.
     *
     * @return array<class-string, string> Each root's path, by type.
     */
    public function byType(string $path): array
    {
        return [
            ClientInterface::class => $path . 'client',
            RequestFactoryInterface::class => $path . 'requests',
            RequestPool::class => $path . 'pool',
            StreamFactoryInterface::class => $path . 'streams',
            Upstreams::class => $path . 'upstreams',
        ];
    }
}
