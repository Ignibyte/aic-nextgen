<?php

declare(strict_types=1);

namespace Gates\Application;

/**
 * The twelve `bin/aic make:*` lines that make `modules/scaffold/` (#122), run in
 * order by gate:31 and by anyone regenerating the module: every writer, a
 * page naming its component, a named layout (#157), a page making its own
 * view in that layout, and an entity with its table and list page (#123).
 */
final readonly class ScaffoldSequence
{
    /**
     * The lines, each an argv after `bin/aic`.
     */
    public const array LINES = [
        ['make:module', 'scaffold'],
        ['make:component', 'ScaffoldCard'],
        ['make:page', 'scaffold', 'ScaffoldPage', '/scaffold', '--component=ScaffoldCard'],
        ['make:layout', 'scaffold-solo'],
        ['make:page', 'scaffold', 'ScaffoldAboutPage', '/scaffold/about', '--layout=scaffold-solo'],
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
        [
            'make:entity',
            'scaffold',
            'ScaffoldTask',
            'scaffold_tasks',
            '--fields=title:text,due_on:date,done:boolean',
            '--version=2026_10_25_000000_create_scaffold_tasks_table',
        ],
    ];
}
