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

    /** The stylesheet: the profiler's pages under `.aic-profiler`, the toolbar under `.aic-toolbar`, nothing else. */
    public const string CSS = <<<'CSS'
        .aic-profiler { margin: 0; font: 14px/1.45 system-ui, sans-serif; color: #1d232a; background: #f6f7f9; }
        .aic-profiler main { max-width: 1200px; margin: 0 auto; padding: 24px 16px 48px; }
        .aic-profiler h2 { margin: 0 0 12px; font-size: 20px; }
        .aic-profiler p { margin: 0 0 12px; color: #4a5560; }
        .aic-profiler .aic-stack { display: flex; flex-direction: column; gap: 12px; }
        .aic-profiler .aic-tabs { display: flex; flex-wrap: wrap; gap: 4px; }
        .aic-profiler .aic-tabs a { padding: 4px 10px; border-radius: 6px; color: #2b5cab; text-decoration: none; background: #e8eef8; }
        .aic-profiler .aic-tabs a[aria-current="page"] { color: #fff; background: #2b5cab; }
        .aic-profiler .aic-filter { display: flex; gap: 8px; align-items: center; margin-bottom: 12px; }
        .aic-profiler .aic-filter input { padding: 4px 8px; border: 1px solid #c4ccd6; border-radius: 6px; }
        .aic-profiler .aic-filter button { padding: 4px 12px; border: 0; border-radius: 6px; color: #fff; background: #2b5cab; }
        .aic-profiler .aic-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; }
        .aic-profiler .aic-table caption { padding: 8px 0; text-align: left; color: #4a5560; }
        .aic-profiler .aic-table th, .aic-profiler .aic-table td {
            padding: 6px 10px;
            border-bottom: 1px solid #eceff3;
            text-align: left;
            vertical-align: top;
        }
        .aic-profiler .aic-table th { font-weight: 600; background: #f0f3f7; }
        .aic-profiler .aic-table .aic-align-end { text-align: end; }
        .aic-profiler .aic-table td { font-variant-numeric: tabular-nums; word-break: break-word; }
        .aic-profiler .aic-table a { color: #2b5cab; }
        .aic-profiler .aic-row-warn td { background: #fff4e0; }
        .aic-profiler .aic-timeline { margin: 0 0 12px; }
        .aic-profiler .aic-timeline svg { width: 100%; height: 40px; border-radius: 6px; background: #eceff3; }
        .aic-profiler .aic-timeline figcaption { padding-top: 4px; color: #4a5560; }
        .aic-profiler .aic-tone-middleware { fill: #8aa4c8; }
        .aic-profiler .aic-tone-handler { fill: #2b5cab; }
        .aic-profiler .aic-tone-render { fill: #3c9a6b; }
        .aic-profiler .aic-tone-rest { fill: #c4ccd6; }
        .aic-profiler .aic-code {
            margin: 0;
            padding: 12px;
            max-height: 480px;
            overflow: auto;
            border-radius: 8px;
            color: #e9edf2;
            background: #1d232a;
        }
        .aic-profiler .aic-code code { font: 12px/1.5 ui-monospace, monospace; white-space: pre-wrap; word-break: break-all; }
        .aic-toolbar {
            position: fixed;
            right: 12px;
            bottom: 12px;
            z-index: 2147483000;
            max-width: min(720px, calc(100vw - 24px));
            font: 13px/1.4 system-ui, sans-serif;
            color: #e9edf2;
        }
        .aic-toolbar details { border-radius: 8px; background: #1d232a; box-shadow: 0 4px 16px rgb(0 0 0 / 25%); }
        .aic-toolbar summary { padding: 6px 12px; cursor: pointer; white-space: nowrap; }
        .aic-toolbar p { margin: 0; padding: 0 12px 8px; color: #b8c2cc; }
        .aic-toolbar a { color: #9cc2ff; }
        .aic-toolbar-exchanges { max-height: 40vh; overflow: auto; padding: 0 12px 8px; }
        .aic-toolbar-exchanges a {
            display: block;
            padding: 3px 0;
            border-top: 1px solid #2c343d;
            font-variant-numeric: tabular-nums;
            text-decoration: none;
        }
        body:has(#aic-highlight:checked) [data-tessera-patched] { outline: 2px solid #f0a020; outline-offset: 2px; }
        CSS;
}
