<?php

declare(strict_types=1);

namespace Gates\Infrastructure;

use Composer\InstalledVersions;
use RuntimeException;

use function realpath;

/**
 * Where `ignibyte/aic-gates` is installed (#116): its `bin/` entries, its base configs
 * in `config/` and its elephc pins in `native/`, read by real path.
 */
final readonly class GatesPackage
{
    /**
     * @throws RuntimeException When composer does not list `ignibyte/aic-gates` as installed.
     *
     * @return string The package's directory.
     */
    public function path(): string
    {
        $path = InstalledVersions::getInstallPath('ignibyte/aic-gates');
        $real = $path === null
            ? false
            : realpath($path);

        if ($real === false) {
            throw new RuntimeException('ignibyte/aic-gates is not installed: composer require --dev ignibyte/aic-gates.');
        }

        return $real;
    }
}
