<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\PackSource;
use AIC\Workflow\Domain\PackFile;
use Override;

use function file_get_contents;
use function is_file;
use function is_link;
use function is_readable;
use function is_string;
use function strtr;

/**
 * The pack as the package ships it (#105): `pack/` in `aic/workflow`, each
 * file's `{vendor}` set to the project's vendor directory, so the hooks and
 * the settings name the package where composer put it.
 */
final readonly class PackageFiles implements PackSource
{
    /**
     * @param string       $package      The package's root.
     * @param string       $vendor       The project's vendor directory, relative to it.
     * @param PackManifest $packManifest The rows.
     */
    public function __construct(
        private string $package,
        private string $vendor,
        private PackManifest $packManifest = new PackManifest(),
    ) {
    }

    /**
     * @param string $tickets The project's ticket directory.
     *
     * @return list<PackFile> The manifest's rows.
     */
    #[Override]
    public function files(string $tickets): array
    {
        return $this->packManifest->files($tickets);
    }

    /**
     * @param PackFile $packFile A row.
     *
     * @return string|null Its bytes with `{vendor}` set; null when the package lacks the file.
     */
    #[Override]
    public function bytes(PackFile $packFile): ?string
    {
        $path = $this->package . '/pack/' . $packFile->source;
        $bytes = is_file($path) && !is_link($path) && is_readable($path)
            ? file_get_contents($path)
            : null;

        return is_string($bytes)
            ? strtr($bytes, ['{vendor}' => $this->vendor])
            : null;
    }
}
