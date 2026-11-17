<?php

declare(strict_types=1);

namespace AIC\HttpClient\Domain;

use InvalidArgumentException;

/**
 * The upstream APIs an app calls (#305), by name, from `config/app.php`.
 */
final readonly class Upstreams
{
    /**
     * @param array<string, Upstream> $upstreams Each upstream by name.
     */
    public function __construct(
        private array $upstreams = [],
    ) {
    }

    /**
     * @param string $name An upstream's name.
     *
     * @throws InvalidArgumentException When config/app.php names no upstream so.
     *
     * @return Upstream It.
     */
    public function upstream(string $name): Upstream
    {
        return $this->upstreams[$name]
            ?? throw new InvalidArgumentException('config/app.php names no upstream ' . $name . '.');
    }
}
