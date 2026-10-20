<?php

declare(strict_types=1);

namespace AIC\Sapi\Infrastructure;

use AIC\Sapi\Contracts\SapiSink;
use Override;

use function flush;
use function header;
use function http_response_code;
use function ob_flush;
use function ob_get_level;

/**
 * The SAPI sink over PHP's own response functions: `http_response_code()`,
 * `header()` and `echo`. It serves under php-fpm, `php -S` and the compiled
 * `elephc --web` server alike, since all three take the response through them.
 */
final readonly class PhpSapiSink implements SapiSink
{
    /**
     * @param int $code The response status.
     */
    #[Override]
    public function status(int $code): void
    {
        http_response_code($code);
    }

    /**
     * @param string $line    One header line.
     * @param bool   $replace Whether it replaces an earlier header of the same name.
     */
    #[Override]
    public function header(string $line, bool $replace): void
    {
        header($line, $replace);
    }

    /**
     * @param string $bytes Body bytes to send.
     */
    #[Override]
    public function write(string $bytes): void
    {
        echo $bytes;
    }

    /**
     * Pushes the output so far to the client: an active output buffer (php.ini's
     * `output_buffering`) first, then PHP's system write buffer (php-fpm,
     * `php -S`). The compiled site's writes are unbuffered, so there both are
     * no-ops.
     */
    #[Override]
    public function flush(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
