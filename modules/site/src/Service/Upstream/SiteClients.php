<?php

declare(strict_types=1);

namespace App\Site\Service\Upstream;

use AIC\HttpClient\Application\RetryingClient;
use AIC\HttpClient\Domain\RetryPolicy;
use Psr\Http\Client\ClientInterface;

/**
 * The site's clients (#305), a factory the manifest lists: the outbound
 * client wrapped to retry, three attempts 10 ms apart then 20, for a call
 * worth repeating.
 */
final readonly class SiteClients
{
    /**
     * @param ClientInterface $client The outbound client.
     */
    public function __construct(
        private ClientInterface $client,
    ) {
    }

    /**
     * @return RetryingClient The client, retrying a network failure or a 429, 502, 503 or 504.
     */
    public function retrying(): RetryingClient
    {
        return new RetryingClient($this->client, new RetryPolicy(3, 10));
    }
}
