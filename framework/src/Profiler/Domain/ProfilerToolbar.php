<?php

declare(strict_types=1);

namespace AIC\Profiler\Domain;

use AIC\Component\Contracts\Component;

/**
 * The profiler's toolbar (#231), spliced into every whole page while the
 * profiler is on: this page's figures (`@profiler-summary`) and a link to its
 * profile, a toggle for the patched-element outline, and the list of this
 * browser's exchanges (`@profiler-exchanges`), both filled by its stream.
 */
final readonly class ProfilerToolbar implements Component
{
    /** The anchor the stream appends each exchange to. */
    public const string EXCHANGES = 'profiler-exchanges';

    /** The anchor the stream fills with the page's own figures. */
    public const string SUMMARY = 'profiler-summary';

    /**
     * @param string $href      This page's profile.
     * @param string $stream    The stream of this browser's exchanges from this page on.
     * @param string $styles    The profiler's stylesheet.
     * @param string $summary   The summary's anchor, printed as its Tessera marks.
     * @param string $exchanges The list's anchor, printed as its Tessera marks.
     */
    public function __construct(
        public string $href,
        public string $stream,
        public string $styles = ProfilerStyles::PATH,
        public string $summary = self::SUMMARY,
        public string $exchanges = self::EXCHANGES,
    ) {
    }
}
