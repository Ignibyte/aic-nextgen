<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * Sends one HTTP request and reports the answer, following no redirect.
 */
interface HttpClient
{
    /**
     * @param string $origin `http://host:port`.
     * @param string $method The method.
     * @param string $path   The path.
     * @param string $form   A form-encoded body; empty for none.
     * @param string $header One extra request header line; empty for none.
     *
     * @return HttpExchange The answer; status 0 when there was none.
     */
    public function send(string $origin, string $method, string $path, string $form, string $header): HttpExchange;
}
