<?php

declare(strict_types=1);

namespace AIC\Config\Domain;

use InvalidArgumentException;

use function trim;

/**
 * The application's own settings, as `config/app.php` returns them.
 *
 * Config in AIC is typed: each concern has a readonly object whose properties
 * are the settings, and its config file constructs it. A setting that does not
 * exist cannot be read, and a value of the wrong type cannot be stored.
 */
final readonly class AppConfig
{
    /**
     * @param string $name The application's name, as pages show it.
     *
     * @throws InvalidArgumentException When the name is blank.
     */
    public function __construct(
        public string $name,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('AppConfig: the application name must not be blank.');
        }
    }
}
