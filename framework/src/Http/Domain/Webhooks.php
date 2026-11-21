<?php

declare(strict_types=1);

namespace AIC\Http\Domain;

use InvalidArgumentException;

use function array_key_exists;
use function str_starts_with;

/**
 * The webhooks an app takes (#321), by the path each is posted to, from
 * `config/app.php`.
 */
final readonly class Webhooks
{
    /**
     * @param array<string, Webhook> $webhooks Each webhook by its path.
     *
     * @throws InvalidArgumentException When a path does not start with `/`.
     */
    public function __construct(
        private array $webhooks = [],
    ) {
        foreach ($webhooks as $path => $webhook) {
            if (!str_starts_with($path, '/')) {
                throw new InvalidArgumentException('Webhooks: the path ' . $path . ' for ' . $webhook->header . ' must start with /.');
            }
        }
    }

    /**
     * @param string $path A request's path.
     *
     * @return bool Whether a webhook is posted to it.
     */
    public function has(string $path): bool
    {
        return array_key_exists($path, $this->webhooks);
    }

    /**
     * @param string $path A webhook's path.
     *
     * @throws InvalidArgumentException When config/app.php names no webhook there.
     *
     * @return Webhook It.
     */
    public function webhook(string $path): Webhook
    {
        return $this->webhooks[$path]
            ?? throw new InvalidArgumentException('config/app.php names no webhook at ' . $path . '.');
    }
}
