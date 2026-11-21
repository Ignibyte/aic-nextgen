<?php

declare(strict_types=1);

namespace Gates\Application;

/**
 * The twenty-eight `bin/aic` make lines that make `modules/scaffold/` (#122),
 * run in order by gate:31 and by anyone regenerating the module: every
 * writer, a component with a script and a page showing it (#315), a page
 * naming its component, a named layout (#157), a page making its own view in
 * that layout, an entity with its table and list page (#123), that entity's
 * policy (#304), a client of one upstream (#305), a uuid-keyed entity with
 * every field type (#307), a pivot relating the two entities (#306), a state
 * machine on the first entity (#308), an MCP tool (#316), an enum, an
 * exception and an interface (#319), three tables imported from a committed
 * pg_dump (#318), a seed of one of them (#320), a signed webhook (#321), an
 * action on the first page that answers its region (#322) and a queued CSV
 * export with its download and prune (#323).
 */
final readonly class ScaffoldSequence
{
    /**
     * The lines, each an argv after `bin/aic`.
     */
    public const array LINES = [
        ['make:module', 'scaffold'],
        ['make:component', 'ScaffoldCard'],
        ['make:component', 'ScaffoldGauge', '--script'],
        ['make:page', 'scaffold', 'ScaffoldPage', '/scaffold', '--component=ScaffoldCard'],
        ['make:page', 'scaffold', 'ScaffoldGaugePage', '/scaffold/gauge', '--component=ScaffoldGauge'],
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
        ['make:policy', 'scaffold', 'ScaffoldTask'],
        ['make:client', 'scaffold', 'ScaffoldFeed'],
        [
            'make:entity',
            'scaffold',
            'ScaffoldEvent',
            'scaffold_events',
            '--key=uuid',
            '--fields=label:text,state:choice(open|shut),payload:json,happened_at:timestamp,source:uuid',
            '--version=2026_11_17_000000_create_scaffold_events_table',
        ],
        [
            'make:relation',
            'scaffold',
            'scaffold_tasks',
            'scaffold_events',
            '--keys=int,uuid',
            '--fields=note:text',
            '--version=2026_11_17_000001_create_scaffold_tasks_scaffold_events_table',
        ],
        [
            'make:state',
            'scaffold',
            'scaffold_tasks',
            'stage',
            '--states=open,done,archived',
            '--transitions=finish:open>done,archive:open|done>archived@scaffold.archive',
            '--version=2026_11_18_000001_add_scaffold_tasks_stage',
        ],
        ['make:mcp-tool', 'scaffold', 'ScaffoldLookupTool'],
        ['make:enum', 'scaffold', 'ScaffoldTone', '--cases=calm,loud,on_hold', '--backed=string'],
        ['make:exception', 'scaffold', 'ScaffoldMissingException', '--extends=LogicException'],
        ['make:interface', 'scaffold', 'ScaffoldSource'],
        [
            'schema:import',
            'gates/fixtures/schema-import.sql',
            '--module=scaffold',
            '--tables=scaffold_shelves,scaffold_books,scaffold_loans',
            '--version=2026_11_20_000000',
        ],
        [
            'make:seed',
            'scaffold',
            'ScaffoldShelvesSeed',
            'scaffold_shelves',
            '--unique=name',
            '--fields=name:text,zone:choice(front|back),capacity:integer',
        ],
        ['make:webhook', 'scaffold', 'ScaffoldDeliveryWebhook', '/scaffold/delivery'],
        ['make:page-action', 'scaffold', 'ScaffoldPinAction', '--on=/scaffold', '--region=content'],
        [
            'make:export',
            'scaffold',
            'ScaffoldShelfExport',
            '/scaffold/exports',
            '--version=2026_11_20_000000_create_scaffold_shelf_exports_table',
        ],
    ];
}
