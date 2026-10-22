<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

use AIC\Workflow\Domain\Guard\Enforcement;

/**
 * What an app's `config/workflow.php` returns (#100): its opt-in to the
 * workflow, its gates' settings (#101), the guard's levers (#102) and the
 * house rules (#104), and the mode (#117). #105's installer writes the
 * file; #102 adds the levers.
 *
 * @api
 */
final readonly class WorkflowSettings
{
    /**
     * @param string          $tickets        The ticket directory, relative to the project, such as `tickets`.
     * @param string          $site           The base URL the rendered check requests declared routes from (#101); empty when
     *                                        there is no site, which blocks a run that declares a GET route.
     * @param int             $maxGateRetries How many times one gate may fail at a phase before the operator decides (#101).
     * @param int             $gateTimeout    Seconds a gate run may take before it is stopped (#101).
     * @param list<string>    $toolchain      Files outside the diff whose change stales every verdict, such as the compiler
     *                                        binary (#101); each is keyed by its stat.
     * @param Enforcement     $enforcement    How hard the guard holds a run to its phase's scope (#102); frozen at begin.
     * @param Enforcement     $requireRun     How hard the guard refuses a write with no run open (#102).
     * @param list<HouseRule> $houseRules     The house rules every run is held to (#104).
     * @param Mode            $mode           Agentic or interactive (#117); frozen at begin.
     * @param bool            $humanComplete  Whether the operator confirms complete in an agentic run too (#117); frozen at begin.
     */
    public function __construct(
        public string $tickets,
        public string $site = '',
        public int $maxGateRetries = 3,
        public int $gateTimeout = 3_600,
        public array $toolchain = [],
        public Enforcement $enforcement = Enforcement::Hard,
        public Enforcement $requireRun = Enforcement::Hard,
        public array $houseRules = [],
        public Mode $mode = Mode::Agentic,
        public bool $humanComplete = false,
    ) {
    }
}
