<?php

declare(strict_types=1);

namespace AIC\Config\Domain;

use AIC\Tessera\Domain\TesseraRelease;
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
     * The request body cap when the app names none: 8 MiB, elephc-web's default.
     */
    public const int MAX_BODY_BYTES = 8_388_608;

    /**
     * @param string         $name         The application's name, as pages show it.
     * @param TesseraRelease $tessera      The Tessera release the pages load (#70).
     * @param string|null    $theme        The folder under `themes/` the build reads the components, layouts
     *                                     and assets from (#153); null for an app with no HTML.
     * @param int            $maxBodyBytes The largest request body the app takes (#196); a larger one answers
     *                                     413 before the session. The default is the compiled server's own.
     *
     * @throws InvalidArgumentException When the name is blank or the cap is below one byte.
     */
    public function __construct(
        public string $name,
        public TesseraRelease $tessera,
        public ?string $theme = null,
        public int $maxBodyBytes = self::MAX_BODY_BYTES,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('AppConfig: the application name must not be blank.');
        }

        if ($maxBodyBytes < 1) {
            throw new InvalidArgumentException('AppConfig: the request body cap must be at least one byte.');
        }
    }
}
