<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\GateProfile;

use function array_diff;
use function array_map;
use function explode;
use function file_get_contents;
use function file_put_contents;
use function fwrite;
use function implode;
use function is_file;
use function str_ends_with;
use function trim;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * `vendor/bin/aic-gate init` (#130): writes each tool's stub that is missing or
 * not the package's, appends the suite's ignore lines a project lacks, and
 * prints the lines composer.json carries, which the project writes itself: init
 * never writes composer.json. A stub has one right content, so restoring it
 * loses nobody's work; a second run writes nothing. It refuses in AIC's own
 * repository, whose root files are its own.
 */
final readonly class SuiteInit
{
    /**
     * The suite's work directories and the tools' caches, as .gitignore lines.
     */
    private const array IGNORES = [
        '/.deptrac.cache',
        '/.native-gate/',
        '/.native-site/',
        '/.phpcs-cache',
        '/.phpstan-cache/',
        '/.psalm-cache/',
        '/.rector-cache/',
        '/node_modules/',
        '/tools/',
    ];

    /**
     * @param SuiteStubs     $suiteStubs     The stubs.
     * @param SuiteComposer  $suiteComposer  composer.json's shipped lines.
     * @param ProjectProfile $projectProfile Whether this is AIC's own repository.
     */
    public function __construct(
        private SuiteStubs $suiteStubs,
        private SuiteComposer $suiteComposer,
        private ProjectProfile $projectProfile = new ProjectProfile(),
    ) {
    }

    /**
     * @param string   $root   The project's root.
     * @param resource $stdout Where what it wrote goes.
     * @param resource $stderr Where a refusal goes.
     *
     * @return int 0, or 2 in AIC's own repository.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        if ($this->projectProfile->profile($root) === GateProfile::Aic) {
            fwrite(
                $stderr,
                'aic-gate init — refused: this is ignibyte/aic-gates\' own repository, and its root files are its own.' . PHP_EOL,
            );

            return 2;
        }

        foreach (SuiteStubs::NAMES as $name) {
            $expected = $this->suiteStubs->expected($root, $name);

            if (is_file($root . '/' . $name) && file_get_contents($root . '/' . $name) === $expected) {
                continue;
            }

            file_put_contents($root . '/' . $name, $expected);
            fwrite($stdout, 'aic-gate init — wrote ' . $name . PHP_EOL);
        }

        $this->ignore($root, $stdout);
        $lines = $this->suiteComposer->lines();
        fwrite($stdout, 'aic-gate init — composer.json carries these lines (gate:30 checks them):' . PHP_EOL . $lines . PHP_EOL);

        return 0;
    }

    /**
     * @param string   $root   The project's root.
     * @param resource $stdout Where what it appended goes.
     */
    private function ignore(string $root, mixed $stdout): void
    {
        $path = $root . '/.gitignore';
        $text = is_file($path)
            ? (string) file_get_contents($path)
            : '';
        $missing = array_diff(self::IGNORES, array_map(trim(...), explode("\n", $text)));

        if ($missing === []) {
            return;
        }

        $head = $text === '' || str_ends_with($text, "\n")
            ? $text
            : $text . "\n";
        $section = "\n# ignibyte/aic-gates' work directories and tool caches (#130)\n";
        file_put_contents($path, $head . $section . implode("\n", $missing) . "\n");
        fwrite($stdout, 'aic-gate init — appended ' . implode(', ', $missing) . ' to .gitignore' . PHP_EOL);
    }
}
