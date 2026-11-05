<?php

declare(strict_types=1);

namespace Gates\Infrastructure;

use RuntimeException;

use function getcwd;
use function is_file;

/**
 * The project a gate runs on (#116): the working directory, where composer
 * runs scripts and where `vendor/bin/aic-gate` is called from. Never the
 * package's own directory, which a path repository's link makes AIC's
 * checkout.
 */
final readonly class ProjectRoot
{
    /**
     * @throws RuntimeException When the working directory is unknown or holds no composer.json.
     *
     * @return string The project's root.
     */
    public function path(): string
    {
        $root = getcwd();

        if ($root === false || !is_file($root . '/composer.json')) {
            throw new RuntimeException("ignibyte/aic-gates runs in the project's root, where composer.json is.");
        }

        return $root;
    }
}
