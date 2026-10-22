<?php

declare(strict_types=1);

namespace Gates\Application;

/**
 * The ten `bin/aic make:*` lines that make `modules/scaffold/` (#122), run in
 * order by gate:31 and by anyone regenerating the module: every writer, a
 * page naming its component and a page making its own view.
 */
final readonly class ScaffoldSequence
{
    /**
     * The lines, each an argv after `bin/aic`.
     */
    public const array LINES = [
        ['make:module', 'scaffold'],
        ['make:component', 'scaffold', 'ScaffoldCard', '--export'],
        ['make:page', 'scaffold', 'ScaffoldPage', '/scaffold', '--component=ScaffoldCard'],
        ['make:page', 'scaffold', 'ScaffoldAboutPage', '/scaffold/about'],
        ['make:page', 'scaffold', 'ScaffoldPost', '/scaffold', '--post'],
        ['make:api', 'scaffold', 'ScaffoldApi', '/api/scaffold'],
        ['make:block', 'scaffold', 'ScaffoldBlock', '--prefix=/scaffold'],
        ['make:command', 'scaffold', 'ScaffoldHello', 'scaffold:hello'],
        ['make:job', 'scaffold', 'ScaffoldHelloJob', 'scaffold-hello'],
        [
            'make:migration',
            'scaffold',
            'CreateScaffoldItemsTable',
            'scaffold_items',
            '--version=2026_10_23_000000_create_scaffold_items_table',
        ],
    ];
}
