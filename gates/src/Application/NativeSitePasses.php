<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\NativeSiteSetup;
use InvalidArgumentException;

use function count;

/**
 * gate:23's passes after the plain steps and the browser tests: the socket conversations (#32), played while the sites
 * still run, then, with every server stopped, the profiled steps on both sites started again with the profiler on
 * (#234).
 */
final readonly class NativeSitePasses
{
    /**
     * @param NativeSiteProfiledPass $nativeSiteProfiledPass The profiled pass.
     * @param NativeSiteSocketPass   $nativeSiteSocketPass   The socket pass.
     * @param NativeSiteScript       $nativeSiteScript       Reads the profiled steps.
     */
    public function __construct(
        private NativeSiteProfiledPass $nativeSiteProfiledPass,
        private NativeSiteSocketPass $nativeSiteSocketPass,
        private NativeSiteScript $nativeSiteScript = new NativeSiteScript(),
    ) {
    }

    /**
     * Runs both passes, the profiled one first in the answer: each answers what it ran, its differences and its
     * other findings.
     *
     * @param string          $root            Repo-root absolute path.
     * @param string          $workDir         The native site's work directory.
     * @param NativeSiteSetup $nativeSiteSetup Where the sites run.
     * @param string          $password        The gate user's password.
     * @param NativeSiteSites $nativeSiteSites The running sites, stopped here.
     *
     * @return array{array{int, list<string>, list<string>}, array{int, list<string>, list<string>}} Both passes.
     */
    public function run(
        string $root,
        string $workDir,
        NativeSiteSetup $nativeSiteSetup,
        string $password,
        NativeSiteSites $nativeSiteSites,
    ): array {
        $sockets = $this->nativeSiteSocketPass->run($root, $workDir, $nativeSiteSetup);
        $nativeSiteSites->stop();

        return [$this->profiled($root, $workDir, $nativeSiteSetup, $password, $nativeSiteSites), $sockets];
    }

    /**
     * @param string          $root            Repo-root absolute path.
     * @param string          $workDir         The native site's work directory.
     * @param NativeSiteSetup $nativeSiteSetup Where the sites run.
     * @param string          $password        The gate user's password.
     * @param NativeSiteSites $nativeSiteSites Starts and stops the sites.
     *
     * @return array{int, list<string>, list<string>} The profiled steps run, their differences and their other
     *                                                findings; nothing when the script names none.
     */
    private function profiled(
        string $root,
        string $workDir,
        NativeSiteSetup $nativeSiteSetup,
        string $password,
        NativeSiteSites $nativeSiteSites,
    ): array {
        try {
            $steps = $this->nativeSiteScript->profiled($root);
        } catch (InvalidArgumentException $invalidArgumentException) {
            return [0, [], ['PROFILED ' . $invalidArgumentException->getMessage()]];
        }

        if ($steps === []) {
            return [0, [], []];
        }

        [$differences, $findings] = $this->nativeSiteProfiledPass->run(
            $root,
            $workDir,
            $nativeSiteSetup,
            $steps,
            $password,
            $nativeSiteSites,
        );

        return [count($steps), $differences, $findings];
    }
}
