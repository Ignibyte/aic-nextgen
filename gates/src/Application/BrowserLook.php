<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\NativeSiteSetup;

use function fwrite;
use function str_starts_with;
use function strlen;
use function substr;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * `vendor/bin/aic-gate browser [--base-url=<url>] [<spec>…]` (#174): runs
 * browser tests against a running site, the ones named or every kept one,
 * with the config gate:23 uses. A workflow run takes its looks with it; a
 * person or an agent runs it while writing a test. When the app's database
 * answers, the gate's user is made and its login handed to the tests.
 */
final readonly class BrowserLook
{
    /**
     * The option that names the site.
     */
    private const string BASE_URL = '--base-url=';

    /**
     * @param BrowserTests $browserTests The app's browser tests.
     * @param GateUser     $gateUser     Makes the user a signed-in test uses.
     */
    public function __construct(
        private BrowserTests $browserTests,
        private GateUser $gateUser,
    ) {
    }

    /**
     * @param string          $root            The app's root.
     * @param NativeSiteSetup $nativeSiteSetup The environment and whether the database answers.
     * @param list<string>    $arguments       `--base-url=<url>` and the specs, in any order.
     * @param resource        $stdout          Playwright's output.
     * @param resource        $stderr          Refusals.
     *
     * @return int Playwright's exit code; 2 when no site is named, 1 when Playwright is not ready.
     */
    public function run(
        string $root,
        NativeSiteSetup $nativeSiteSetup,
        array $arguments,
        mixed $stdout = STDOUT,
        mixed $stderr = STDERR,
    ): int {
        $env = $nativeSiteSetup->env;
        [$baseUrl, $specs] = self::options($env['AIC_BROWSER_BASE_URL'] ?? '', $arguments);
        $refusal = $baseUrl === ''
            ? 'aic-gate browser: name the site with --base-url=<url> or AIC_BROWSER_BASE_URL.'
            : $this->browserTests->unready($root);

        if ($refusal !== null) {
            fwrite($stderr, $refusal . PHP_EOL);

            return $baseUrl === ''
                ? 2
                : 1;
        }

        $processResult = $this->browserTests->run($root, $baseUrl, $this->signedIn($root, $nativeSiteSetup), $specs);
        fwrite($stdout, $processResult->stdout);
        fwrite($stderr, $processResult->stderr);

        return $processResult->exitCode;
    }

    /**
     * @param string          $root            The app's root.
     * @param NativeSiteSetup $nativeSiteSetup The environment and whether the database answers.
     *
     * @return array<string, string> The environment, with the gate user's login when the database answers.
     */
    private function signedIn(string $root, NativeSiteSetup $nativeSiteSetup): array
    {
        $env = $nativeSiteSetup->env;

        if ($nativeSiteSetup->reachable) {
            $env['AIC_GATE_LOGIN'] = GateUser::LOGIN;
            $env['AIC_GATE_PASSWORD'] = $this->gateUser->create($root, $env);
        }

        return $env;
    }

    /**
     * @param string       $baseUrl   The site the environment names; empty for none.
     * @param list<string> $arguments The command's arguments.
     *
     * @return array{string, list<string>} The site, `--base-url` winning, and the specs.
     */
    private static function options(string $baseUrl, array $arguments): array
    {
        $specs = [];

        foreach ($arguments as $argument) {
            if (str_starts_with($argument, self::BASE_URL)) {
                $baseUrl = substr($argument, strlen(self::BASE_URL));

                continue;
            }

            $specs[] = $argument;
        }

        return [$baseUrl, $specs];
    }
}
