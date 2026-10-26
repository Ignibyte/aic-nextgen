<?php

declare(strict_types=1);

/*
 * 02-composition (#119; #101 AC 4, 5, 10): the spec declares GET /api/status
 * a Page while the app builds it an Api, and a route the spec does not
 * declare, GET /health2, is added and built. Code fails composition twice,
 * a new undeclared route each time (an unchanged repeat is held by the
 * stall rule, #117, before the budget is spent), and the third call is held
 * on the spent retry budget: the gate does not run again. gate:23 fails
 * beside it each time, since no step reaches the added routes (#171).
 */

use AIC\Workflow\Domain\Probe\CommandStep;
use AIC\Workflow\Domain\Probe\Expect;
use AIC\Workflow\Domain\Probe\ProbePlan;
use AIC\Workflow\Domain\Probe\ProbeScenario;
use AIC\Workflow\Domain\Probe\ReplaceStep;
use AIC\Workflow\Domain\Probe\RunStep;

$route = static fn (string $after, string $added): ReplaceStep => new ReplaceStep(
    'modules/site/site.module.php',
    "    ->route(RouteMethod::Get, '" . $after . "', HealthApi::class)\n",
    "    ->route(RouteMethod::Get, '" . $after . "', HealthApi::class)\n"
        . "    ->route(RouteMethod::Get, '" . $added . "', HealthApi::class)\n",
);
$build = new CommandStep(['composer', 'run', 'build']);

return new ProbeScenario([
    ...(new ProbePlan())->toCode(),
    $route('/health', '/health2'),
    $build,
    new RunStep('blocked', [
        new Expect('composition', 'failed', 'declared Page, built Api: GET /api/status'),
        new Expect('composition', 'failed', 'built and not declared: GET /health2'),
    ]),
    $route('/health2', '/health3'),
    $build,
    new RunStep('blocked', [new Expect('composition', 'failed', 'built and not declared: GET /health')]),
    $route('/health3', '/health4'),
    $build,
    new RunStep('held', [
        new Expect('gate', 'held', 'max_gate_retries (2) is spent at code: composition (2 times), gate:23 (2 times) failed'),
    ]),
], database: true);
