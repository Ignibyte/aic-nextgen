<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure\Probe;

use AIC\Workflow\Application\Probe\ProbeHost;
use AIC\Workflow\Infrastructure\ComposerManifest;
use AIC\Workflow\Infrastructure\LocalProcess;
use Override;

use function fclose;
use function fopen;
use function fwrite;
use function getmypid;
use function is_dir;
use function is_file;
use function is_resource;
use function mkdir;
use function unlink;

/**
 * Where the sweep runs (#119): AIC's own repository, the one that holds the
 * package's source; no gate runner live on the machine (`pgrep` on the
 * runner's name, as the operator checks by hand); and `var/probe/lock`,
 * made exclusively, so two sweeps never share the scratch root.
 */
final readonly class LocalProbeHost implements ProbeHost
{
    /**
     * @param string $root    The project's root.
     * @param string $package The workflow package's root.
     */
    public function __construct(
        private string $root,
        private string $package,
    ) {
    }

    /**
     * @return bool Whether the project holds the package's source.
     */
    #[Override]
    public function aic(): bool
    {
        return (new ComposerManifest($this->root, $this->package))->holdsSource();
    }

    /**
     * @return bool Whether a `gate-runner.sh` is live.
     */
    #[Override]
    public function gateRunLive(): bool
    {
        return (new LocalProcess($this->root))->run(['pgrep', '-f', '[g]ate-runner\\.sh'], 10)->exitCode === 0;
    }

    /**
     * @return bool Whether the lock was made; false when it is there.
     */
    #[Override]
    public function lock(): bool
    {
        $folder = $this->root . '/var/probe';

        if ((!is_dir($folder) && !mkdir($folder, 0o775, true)) || is_file($folder . '/lock')) {
            return false;
        }

        $lock = fopen($folder . '/lock', 'x');

        if (!is_resource($lock)) {
            return false;
        }

        fwrite($lock, (string) getmypid());
        fclose($lock);

        return true;
    }

    /**
     * Removes the lock.
     */
    #[Override]
    public function unlock(): void
    {
        $lock = $this->root . '/var/probe/lock';

        if (!is_file($lock)) {
            return;
        }

        unlink($lock);
    }
}
