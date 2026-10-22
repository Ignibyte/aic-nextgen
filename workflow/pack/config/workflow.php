<?php

declare(strict_types=1);

/*
 * The workflow's settings (aic/workflow). The engine reads them at every run.
 * bin/aic workflow:init wrote this file once and never writes it again: it is
 * the operator's, and the guard keeps the agent out of it.
 */

use AIC\Workflow\Domain\Guard\Enforcement;
use AIC\Workflow\Domain\HouseRule;
use AIC\Workflow\Domain\Mode;
use AIC\Workflow\Domain\WorkflowSettings;

return new WorkflowSettings(
    // The ticket directory, relative to the project: one NNNN-slug.md per ticket (its README holds the format).
    tickets: 'tickets',
    // The base URL the check phase requests the spec's GET routes from, such as https://my-app.ddev.site.
    // Empty, a run that declares a GET route holds at check until the operator sets it.
    site: '',
    // How many times one gate may fail at a phase before the run waits on the operator.
    maxGateRetries: 3,
    // Seconds one gate run may take before it is stopped.
    gateTimeout: 3_600,
    // Files outside the project whose change makes every gate verdict stale, such as a compiler binary.
    toolchain: [],
    // How hard the guard holds a run to its phase's paths: Hard refuses, Soft warns once, Off stands down.
    enforcement: Enforcement::Hard,
    // How hard the guard refuses a write outside the ticket directory while no run is open.
    requireRun: Enforcement::Hard,
    // The house rules every run is held to: a PHP file each, its probe beside it, frozen when a run begins.
    houseRules: [
        new HouseRule(
            'gate-suite',
            'A run leaves the gate suite as it began, but for the lines a new module needs.',
            '{vendor}/aic/workflow/rules/gate-suite.php',
        ),
        new HouseRule(
            'raw-html-grants',
            "A run adds no RawHtml grant and raises none: grants are the operator's.",
            '{vendor}/aic/workflow/rules/raw-html-grants.php',
        ),
    ],
    // Agentic runs plan to complete without asking; Interactive waits for the operator to confirm each phase exit
    // (bin/aic workflow:answer advance|stay from a terminal). Frozen when a run begins.
    mode: Mode::Agentic,
    // Whether an agentic run also waits for the operator to confirm complete. Frozen when a run begins.
    humanComplete: false,
);
