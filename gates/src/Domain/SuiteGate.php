<?php

declare(strict_types=1);

namespace Gates\Domain;

/**
 * One row of the gate suite (#116): its number, what it holds, where it runs
 * (`app` for every project on AIC, `aic` for AIC's own repository only), and
 * the command that runs it, as an argv.
 */
final readonly class SuiteGate
{
    /**
     * @param int          $number      Its N in `gate:N`.
     * @param string       $description What it holds, one line.
     * @param string       $scope       `app` or `aic`.
     * @param list<string> $command     The argv, absolute, run in the project's root.
     */
    public function __construct(
        public int $number,
        public string $description,
        public string $scope,
        public array $command,
    ) {
    }

    /**
     * @return string Its name, `gate:N`.
     */
    public function name(): string
    {
        return 'gate:' . $this->number;
    }
}
