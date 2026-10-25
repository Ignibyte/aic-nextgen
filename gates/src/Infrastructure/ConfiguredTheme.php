<?php

declare(strict_types=1);

namespace Gates\Infrastructure;

use AIC\Config\Domain\AppConfig;
use RuntimeException;

use function is_file;

/** The theme a project's `config/app.php` names (#153), for the build and the gates that read the presentation. */
final readonly class ConfiguredTheme
{
    /** @param ProjectConfig $projectConfig Loads the typed config file. */
    public function __construct(private ProjectConfig $projectConfig = new ProjectConfig())
    {
    }

    /**
     * @param string $root The project folder, or a plant root.
     *
     * @throws RuntimeException When `config/app.php` exists but cannot be loaded as an AppConfig.
     *
     * @return string|null The theme's folder name; null when the project has no config or names no theme.
     */
    public function name(string $root): ?string
    {
        $file = $root . '/config/app.php';

        return is_file($file)
            ? $this->projectConfig->read($file, AppConfig::class)->theme
            : null;
    }
}
