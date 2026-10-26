<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * One step's answer from one site (gate:23, #171): the path the step was sent
 * to, with `{note}` filled from the location an earlier step captured, and the
 * answer as sent.
 */
final readonly class NativeSiteAnswer
{
    /**
     * @param string       $path         The path and query the request went to.
     * @param HttpExchange $httpExchange The answer, as sent.
     */
    public function __construct(
        public string $path,
        public HttpExchange $httpExchange,
    ) {
    }
}
