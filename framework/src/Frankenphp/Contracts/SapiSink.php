<?php

declare(strict_types=1);

namespace AIC\Frankenphp\Contracts;

/**
 * Where a response goes out: the SAPI's status, header and output calls.
 *
 * The worker implements it with `http_response_code()`, `header()` and `echo`,
 * in interpreted code, so natively compiled code never calls the SAPI itself.
 */
interface SapiSink
{
    /**
     * @param int $code The response status.
     */
    public function status(int $code): void;

    /**
     * @param string $line    One header line, `Name: value`.
     * @param bool   $replace Whether it replaces an earlier header of the same name.
     */
    public function header(string $line, bool $replace): void;

    /**
     * @param string $bytes Body bytes to send.
     */
    public function write(string $bytes): void;
}
