<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * What an app's `config/workflow.php` returns (#100): its opt-in to the
 * workflow and its gates' settings (#101). #105's installer writes the
 * file; #102 adds the levers.
 *
 * @api
 */
final readonly class WorkflowSettings
{
    /**
     * @param string       $tickets        The ticket directory, relative to the project, such as `tickets`.
     * @param string       $site           The base URL the rendered check requests declared routes from (#101); empty when
     *                                     there is no site, which blocks a run that declares a GET route.
     * @param int          $maxGateRetries How many times one gate may fail at a phase before the operator decides (#101).
     * @param int          $gateTimeout    Seconds a gate run may take before it is stopped (#101).
     * @param list<string> $toolchain      Files outside the diff whose change stales every verdict, such as the compiler
     *                                     binary (#101); each is keyed by its stat.
     */
    public function __construct(
        public string $tickets,
        public string $site = '',
        public int $maxGateRetries = 3,
        public int $gateTimeout = 3_600,
        public array $toolchain = [],
    ) {
    }
}
