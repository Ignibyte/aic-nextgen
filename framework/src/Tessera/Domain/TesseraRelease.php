<?php

declare(strict_types=1);

namespace AIC\Tessera\Domain;

use InvalidArgumentException;

use function preg_match;

/**
 * The Tessera release the app ships (#70): its version and the SHA-256 of
 * each file the build copies into `build/assets/tessera-VERSION/`. It is
 * pinned in `config/app.php`, the one place the build and the layout read.
 */
final readonly class TesseraRelease
{
    /**
     * The build the page loads: the no-eval engine, as an IIFE that starts itself.
     */
    public const string SCRIPT = 'tessera.csp.iife.js';

    /**
     * @param string                $version A release, such as `0.1.0`.
     * @param array<string, string> $sums    Each file's SHA-256 in hex, by its name in the release.
     *
     * @throws InvalidArgumentException When the version or a sum is malformed, or the script is not pinned.
     */
    public function __construct(
        public string $version,
        public array $sums,
    ) {
        if (preg_match('/^\\d+\\.\\d+\\.\\d+$/', $version) !== 1) {
            throw new InvalidArgumentException('A Tessera release is MAJOR.MINOR.PATCH; got "' . $version . '".');
        }

        if (($sums[self::SCRIPT] ?? '') === '') {
            throw new InvalidArgumentException('The Tessera pin names ' . self::SCRIPT . '.');
        }

        self::checkSums($sums);
    }

    /**
     * @return string The asset directory the release goes in, `tessera-VERSION`.
     */
    public function directory(): string
    {
        return 'tessera-' . $this->version;
    }

    /**
     * @return string The script's URL path, `/assets/tessera-VERSION/tessera.csp.iife.js`.
     */
    public function scriptPath(): string
    {
        return '/assets/' . $this->directory() . '/' . self::SCRIPT;
    }

    /**
     * @param array<string, string> $sums Each file's SHA-256 by its name.
     *
     * @throws InvalidArgumentException When a name or a sum is malformed.
     */
    private static function checkSums(array $sums): void
    {
        foreach ($sums as $name => $sum) {
            if (preg_match('/^[a-z0-9.]+$/', $name) !== 1 || preg_match('/^[0-9a-f]{64}$/', $sum) !== 1) {
                throw new InvalidArgumentException('Tessera pin "' . $name . '": a file name and a 64-character hex SHA-256.');
            }
        }
    }
}
