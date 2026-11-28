<?php

declare(strict_types=1);

namespace AIC\Socket\Domain;

use function explode;
use function strpos;
use function substr;
use function trim;

/**
 * The HTTP request that opens a connection (#32), read from the bytes before
 * its blank line (`UpgradeParser`): the method, the path without its query,
 * and each header by its lower-case name (a repeated header keeps its last
 * value).
 */
final readonly class UpgradeHead
{
    /**
     * @param string                $method  The method, such as `GET`.
     * @param string                $path    The path, such as `/ws/chat`.
     * @param array<string, string> $headers Each header's value by its lower-case name.
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $headers,
    ) {
    }

    /**
     * @param string $name A lower-case header name.
     *
     * @return string Its value; empty when it was not sent.
     */
    public function header(string $name): string
    {
        return $this->headers[$name] ?? '';
    }

    /**
     * @param string $name A cookie's name.
     *
     * @return string Its value; empty when it was not sent.
     */
    public function cookie(string $name): string
    {
        foreach (explode(';', $this->header('cookie')) as $pair) {
            $equals = strpos($pair, '=');

            if ($equals !== false && trim(substr($pair, 0, $equals)) === $name) {
                return trim(substr($pair, $equals + 1));
            }
        }

        return '';
    }
}
