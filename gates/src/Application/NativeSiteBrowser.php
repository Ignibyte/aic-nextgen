<?php

declare(strict_types=1);

namespace Gates\Application;

use function array_map;
use function array_pop;
use function array_slice;
use function count;
use function explode;
use function implode;
use function mb_rtrim;
use function mb_trim;
use function preg_match;
use function preg_replace;
use function sprintf;

use const PHP_EOL;

/**
 * gate:23's browser half (#174): every browser test the app keeps in
 * `tests/browser/` runs against the compiled site while it is served, signed
 * in as the gate's own user when a test asks. A failing test is a finding.
 */
final readonly class NativeSiteBrowser
{
    /**
     * A failing test in Playwright's line reporter: `  1) tests/browser/x.spec.ts:7:5 › name`.
     */
    private const string FAILURE = '/^\\s+\\d+\\) /';

    /**
     * The reporter's closing count (`  1 failed`), after which it only lists names again.
     */
    private const string SUMMARY = '/^\\s+\\d+ (?:passed|failed|flaky|skipped|did not run|interrupted)\\b/';

    /**
     * How many lines of a failure's error the gate keeps under its `BROWSER` line (#181).
     */
    private const int DETAIL = 20;

    /**
     * @param BrowserTests $browserTests The app's browser tests.
     */
    public function __construct(
        private BrowserTests $browserTests,
    ) {
    }

    /**
     * @param string                $root     The app's root.
     * @param string                $origin   `http://host:port` of the compiled site.
     * @param array<string, string> $env      The environment the sites run with.
     * @param string                $password The gate user's password; empty when there is none.
     *
     * @return array{int, list<string>} How many kept test files ran, and one `BROWSER` line per failure.
     */
    public function findings(string $root, string $origin, array $env, string $password): array
    {
        $specs = count($this->browserTests->kept($root));

        if ($specs === 0) {
            return [0, []];
        }

        $unready = $this->browserTests->unready($root);

        return [$specs, $unready === null ? $this->ran($root, $origin, $env, $password) : ['BROWSER ' . $unready]];
    }

    /**
     * @param string                $root     The app's root.
     * @param string                $origin   The compiled site.
     * @param array<string, string> $env      The environment.
     * @param string                $password The gate user's password.
     *
     * @return list<string> One `BROWSER` line per failing test; none when every kept test passes.
     */
    private function ran(string $root, string $origin, array $env, string $password): array
    {
        $env['AIC_GATE_LOGIN'] = GateUser::LOGIN;
        $env['AIC_GATE_PASSWORD'] = $password;
        $processResult = $this->browserTests->run($root, $origin, $env, []);

        return $processResult->exitCode === 0
            ? []
            : self::failures($processResult->stdout . $processResult->stderr);
    }

    /**
     * @param string $output Playwright's output.
     *
     * @return list<string> Each failing test as a `BROWSER` line with its error under it; Playwright's last line when it
     *                      named none.
     */
    private static function failures(string $output): array
    {
        $lines = explode("\n", preg_replace('/\\e\\[[0-9;]*m/', '', $output) ?? $output);
        $blocks = self::blocks($lines);

        return $blocks === []
            ? [sprintf('BROWSER Playwright failed: %s', self::lastLine($lines))]
            : array_map(self::block(...), $blocks);
    }

    /**
     * @param list<string> $lines Playwright's output, line by line.
     *
     * @return list<non-empty-list<string>> Each failing test's header, then the lines the reporter printed under it.
     */
    private static function blocks(array $lines): array
    {
        $blocks = [];

        foreach (self::reported($lines) as $line) {
            if (preg_match(self::FAILURE, $line) === 1) {
                $blocks[] = [$line];

                continue;
            }

            $blocks = self::appended($blocks, $line);
        }

        return $blocks;
    }

    /**
     * @param list<string> $lines Playwright's output, line by line.
     *
     * @return list<string> The lines before the reporter's closing count.
     */
    private static function reported(array $lines): array
    {
        $reported = [];

        foreach ($lines as $line) {
            if (preg_match(self::SUMMARY, $line) === 1) {
                return $reported;
            }

            $reported[] = $line;
        }

        return $reported;
    }

    /**
     * @param list<non-empty-list<string>> $blocks The failures so far.
     * @param string                       $line   A line the reporter printed after the last failure's header.
     *
     * @return list<non-empty-list<string>> The failures, the last one holding the line when it is not blank.
     */
    private static function appended(array $blocks, string $line): array
    {
        $last = array_pop($blocks);

        if ($last === null) {
            return [];
        }

        $blocks[] = mb_trim($line) === ''
            ? $last
            : [...$last, mb_rtrim($line)];

        return $blocks;
    }

    /**
     * @param non-empty-list<string> $block A failing test's header and the lines under it.
     *
     * @return string Its `BROWSER` line, then the first lines of its error, as the reporter indents them.
     */
    private static function block(array $block): string
    {
        $detail = array_slice($block, 1, self::DETAIL);
        $more = count($block) - 1 - count($detail);
        $lines = [self::line($block[0]), ...$detail];

        if ($more > 0) {
            $lines[] = sprintf('    … %d more line(s) in Playwright\'s output.', $more);
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param list<string> $lines Playwright's output, line by line.
     *
     * @return string Its last line that is not empty; `no output` when there is none.
     */
    private static function lastLine(array $lines): string
    {
        $last = 'no output';

        foreach ($lines as $line) {
            if (mb_trim($line) === '') {
                continue;
            }

            $last = mb_trim($line);
        }

        return $last;
    }

    /**
     * @param string $line A failing test, as the line reporter prints it.
     *
     * @return string Its `BROWSER` line, without the reporter's rule.
     */
    private static function line(string $line): string
    {
        return 'BROWSER ' . mb_trim(preg_replace('/\\s*─+\\s*\\z/u', '', $line) ?? $line);
    }
}
