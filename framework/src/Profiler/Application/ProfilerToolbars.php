<?php

declare(strict_types=1);

namespace AIC\Profiler\Application;

use AIC\Profiler\Domain\ProfilerDebug;
use AIC\Profiler\Domain\ProfilerToolbar;

use function rawurlencode;
use function sprintf;

/**
 * What a profiled whole page gains (#231): Tessera's debug meta in its head
 * and the toolbar at the end of its body, the toolbar naming the page's
 * profile and streaming this browser's exchanges from it on.
 */
final readonly class ProfilerToolbars
{
    /**
     * @return ProfilerDebug The meta that has Tessera mark each patched element.
     */
    public function debug(): ProfilerDebug
    {
        return new ProfilerDebug();
    }

    /**
     * @param string $id      The page's profile id, made when its request started.
     * @param string $browser The browser's key.
     *
     * @return ProfilerToolbar The toolbar: a link to the page's profile, and the stream that fills its figures and
     *                         its list.
     */
    public function toolbar(string $id, string $browser): ProfilerToolbar
    {
        return new ProfilerToolbar(
            '/_profiler/' . $id,
            sprintf('/_profiler/stream?browser=%s&after=%s', rawurlencode($browser), rawurlencode($id)),
        );
    }
}
