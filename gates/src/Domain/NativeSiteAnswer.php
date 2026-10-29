<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * One step's answer from one site (gate:23, #171): the path the step was sent
 * to, with `{note}` filled from the location an earlier step captured, the
 * answer as sent, the path this step captured, if it captures (#178), and
 * why the queue worker it ran first failed, if it failed (#209).
 */
final readonly class NativeSiteAnswer
{
    /**
     * @param string       $path         The path and query the request went to.
     * @param HttpExchange $httpExchange The answer, as sent.
     * @param string       $captured     The path of the location this step captured; empty when it captures none.
     * @param string       $worker       Why the queue worker the step ran first failed; empty when it ran or none ran.
     */
    public function __construct(
        public string $path,
        public HttpExchange $httpExchange,
        public string $captured = '',
        public string $worker = '',
    ) {
    }
}
