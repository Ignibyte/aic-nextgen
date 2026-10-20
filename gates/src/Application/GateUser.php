<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\ProcessRunner;

use function bin2hex;
use function random_bytes;

/**
 * The user gate:23 signs in as (#9): `gate`, given a fresh random password
 * on each run through the app's own `bin/aic user:add`, so no password lives
 * in a file.
 */
final readonly class GateUser
{
    /**
     * The login.
     */
    public const string LOGIN = 'gate';

    /**
     * @param ProcessRunner $processRunner Runs the console.
     * @param string        $php           The PHP binary.
     */
    public function __construct(
        private ProcessRunner $processRunner,
        private string $php,
    ) {
    }

    /**
     * @param string                $root The app's root.
     * @param array<string, string> $env  The environment the sites run with.
     *
     * @return string The password it was given; empty when the console failed.
     */
    public function create(string $root, array $env): string
    {
        $password = bin2hex(random_bytes(16));
        $processResult = $this->processRunner->run([$this->php, $root . '/bin/aic', 'user:add', self::LOGIN, $password], $root, $env);

        return $processResult->exitCode === 0
            ? $password
            : '';
    }
}
