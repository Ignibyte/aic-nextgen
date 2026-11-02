<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\NativeSiteSetup;
use Gates\Domain\NativeSiteStep;

/**
 * gate:23's second pass (#234): both sites again, from the same build, with
 * `AIC_PROFILER=1`. The script's `profiled` steps run on each and compare as
 * the plain ones do, with the profiler's ids masked. Each step's two recorded
 * profiles must have the same shape, and the `profiler` browser specs run on
 * the compiled site.
 */
final readonly class NativeSiteProfiledPass
{
    /**
     * @param NativeSiteComparer $nativeSiteComparer Runs and compares the steps.
     * @param NativeSiteBrowser  $nativeSiteBrowser  Runs the profiler's browser specs.
     * @param NativeSiteHtml     $nativeSiteHtml     Lints the PHP answers, the toolbar included.
     * @param NativeSiteProfiles $nativeSiteProfiles Compares the recorded profiles.
     */
    public function __construct(
        private NativeSiteComparer $nativeSiteComparer,
        private NativeSiteBrowser $nativeSiteBrowser,
        private NativeSiteHtml $nativeSiteHtml = new NativeSiteHtml(),
        private NativeSiteProfiles $nativeSiteProfiles = new NativeSiteProfiles(),
    ) {
    }

    /**
     * @param string               $root            The app's root.
     * @param string               $workDir         The native site's work directory.
     * @param NativeSiteSetup      $nativeSiteSetup The ports, limits and environment.
     * @param list<NativeSiteStep> $steps           The profiled steps.
     * @param string               $password        The gate user's password.
     * @param NativeSiteSites      $nativeSiteSites Starts and stops the sites.
     *
     * @return array{list<string>, list<string>} Its differences and its other findings.
     */
    public function run(
        string $root,
        string $workDir,
        NativeSiteSetup $nativeSiteSetup,
        array $steps,
        string $password,
        NativeSiteSites $nativeSiteSites,
    ): array {
        $env = [...$nativeSiteSetup->env, 'AIC_PROFILER' => '1'];

        if (!$nativeSiteSites->start($root, $workDir, $nativeSiteSetup, $env)) {
            $nativeSiteSites->stop();

            return [[], ['PROFILED a site did not start with the profiler on; see ' . $workDir . '/php.log and native.log.']];
        }

        [$php, $native] = $this->nativeSiteComparer->both($nativeSiteSetup, $steps, $password, $root, $env);
        $browser = $this->nativeSiteBrowser->findings($root, 'http://127.0.0.1:' . $nativeSiteSetup->nativePort, $env, $password, true);
        $nativeSiteSites->stop();
        $differences = [
            ...$this->nativeSiteComparer->differences($steps, $php, $native),
            ...$this->nativeSiteProfiles->differences($steps, $php, $native, $env),
        ];
        $findings = [...$this->nativeSiteHtml->findings($steps, $php), ...$browser[1]];

        return [self::prefixed($differences), self::prefixed($findings)];
    }

    /**
     * @param list<string> $lines Differences or findings.
     *
     * @return list<string> Each marked as the profiled pass's.
     */
    private static function prefixed(array $lines): array
    {
        $prefixed = [];

        foreach ($lines as $line) {
            $prefixed[] = 'PROFILED ' . $line;
        }

        return $prefixed;
    }
}
