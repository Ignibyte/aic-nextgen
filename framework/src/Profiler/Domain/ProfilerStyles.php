<?php

declare(strict_types=1);

namespace AIC\Profiler\Domain;

/**
 * The profiler pages' stylesheet (#230), served at `/_profiler/styles.css` by
 * the pages themselves, so it needs no asset build and looks the same in every
 * app. It styles only the framework's atoms and the profiler's document.
 */
final readonly class ProfilerStyles
{
    /** Where the pages serve it. */
    public const string PATH = '/_profiler/styles.css';

    /** The stylesheet. */
    public const string CSS = <<<'CSS'
        .aic-profiler { margin: 0; font: 14px/1.45 system-ui, sans-serif; color: #1d232a; background: #f6f7f9; }
        .aic-profiler main { max-width: 1200px; margin: 0 auto; padding: 24px 16px 48px; }
        .aic-profiler h2 { margin: 0 0 12px; font-size: 20px; }
        .aic-profiler p { margin: 0 0 12px; color: #4a5560; }
        .aic-stack { display: flex; flex-direction: column; gap: 12px; }
        .aic-tabs { display: flex; flex-wrap: wrap; gap: 4px; }
        .aic-tabs a { padding: 4px 10px; border-radius: 6px; color: #2b5cab; text-decoration: none; background: #e8eef8; }
        .aic-tabs a[aria-current="page"] { color: #fff; background: #2b5cab; }
        .aic-filter { display: flex; gap: 8px; align-items: center; margin-bottom: 12px; }
        .aic-filter input { padding: 4px 8px; border: 1px solid #c4ccd6; border-radius: 6px; }
        .aic-filter button { padding: 4px 12px; border: 0; border-radius: 6px; color: #fff; background: #2b5cab; }
        .aic-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; }
        .aic-table caption { padding: 8px 0; text-align: left; color: #4a5560; }
        .aic-table th, .aic-table td { padding: 6px 10px; border-bottom: 1px solid #eceff3; text-align: left; vertical-align: top; }
        .aic-table th { font-weight: 600; background: #f0f3f7; }
        .aic-table td { font-variant-numeric: tabular-nums; word-break: break-word; }
        .aic-table a { color: #2b5cab; }
        .aic-row-warn td { background: #fff4e0; }
        .aic-timeline { margin: 0 0 12px; }
        .aic-timeline svg { width: 100%; height: 40px; border-radius: 6px; background: #eceff3; }
        .aic-timeline figcaption { padding-top: 4px; color: #4a5560; }
        .aic-tone-middleware { fill: #8aa4c8; }
        .aic-tone-handler { fill: #2b5cab; }
        .aic-tone-render { fill: #3c9a6b; }
        .aic-tone-rest { fill: #c4ccd6; }
        .aic-code { margin: 0; padding: 12px; max-height: 480px; overflow: auto; border-radius: 8px; color: #e9edf2; background: #1d232a; }
        .aic-code code { font: 12px/1.5 ui-monospace, monospace; white-space: pre-wrap; word-break: break-all; }
        CSS;
}
