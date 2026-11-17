<?php

declare(strict_types=1);

namespace AIC\HttpClient\Contracts;

/**
 * Told of each call the app makes to an upstream (#305): the profiler's
 * profile, when it records.
 */
interface OutboundObserver
{
    /**
     * @param OutboundCall $outboundCall The call, once it is answered or has failed.
     */
    public function called(OutboundCall $outboundCall): void;
}
