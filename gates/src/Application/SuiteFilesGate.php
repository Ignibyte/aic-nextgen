<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\GateProfile;
use stdClass;

use function count;
use function file_get_contents;
use function fwrite;
use function is_file;
use function json_decode;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * gate:30 (#130): the suite's files are as aic/gates ships them. In an app,
 * every tool's root config is the package's stub, byte for byte (phpcs.xml.dist
 * with one root per module), and composer.json carries the suite's scripts and
 * plugins; `vendor/bin/aic-gate init` restores the files. In AIC's own
 * repository the root configs are AIC's, so only the two it keeps whole
 * (`pint.json`, `captainhook.json`) and composer.json's lines are held.
 */
final readonly class SuiteFilesGate
{
    /**
     * The stubs AIC's own repository keeps whole at its root.
     */
    private const array AIC_STUBS = ['captainhook.json', 'pint.json'];

    /**
     * @param SuiteStubs     $suiteStubs     The stubs.
     * @param SuiteComposer  $suiteComposer  composer.json's shipped lines.
     * @param ProjectProfile $projectProfile Which stubs the project holds.
     */
    public function __construct(
        private SuiteStubs $suiteStubs,
        private SuiteComposer $suiteComposer,
        private ProjectProfile $projectProfile = new ProjectProfile(),
    ) {
    }

    /**
     * @param string   $root   The project's root.
     * @param resource $stdout Where the summary goes.
     * @param resource $stderr Where each finding goes.
     *
     * @return int 0 when the files hold, 1 otherwise.
     */
    public function run(string $root, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        $names = $this->projectProfile->profile($root) === GateProfile::Aic
            ? self::AIC_STUBS
            : SuiteStubs::NAMES;
        $findings = [...$this->stubFindings($root, $names), ...$this->suiteComposer->findings($this->composer($root))];

        foreach ($findings as $finding) {
            fwrite($stderr, 'gate:30 — ' . $finding . PHP_EOL);
        }

        fwrite(
            $findings === [] ? $stdout : $stderr,
            'gate:30 — suite files: ' . count($names) . " stub(s) and composer.json's scripts and plugins checked, "
                . count($findings) . ' finding(s).' . PHP_EOL,
        );

        return $findings === []
            ? 0
            : 1;
    }

    /**
     * @param string $root The project's root.
     *
     * @return stdClass Its composer.json, decoded as objects; an empty object when it cannot be read.
     */
    private function composer(string $root): stdClass
    {
        $composer = is_file($root . '/composer.json')
            ? json_decode((string) file_get_contents($root . '/composer.json'))
            : null;

        return $composer instanceof stdClass
            ? $composer
            : new stdClass();
    }

    /**
     * @param string       $root  The project's root.
     * @param list<string> $names The stubs it should hold.
     *
     * @return list<string> Each one missing or not the package's.
     */
    private function stubFindings(string $root, array $names): array
    {
        $findings = [];

        foreach ($names as $name) {
            $path = $root . '/' . $name;
            $state = match (true) {
                !is_file($path) => ' is missing',
                file_get_contents($path) !== $this->suiteStubs->expected($root, $name) => " is not aic/gates' stub",
                default => '',
            };

            if ($state === '') {
                continue;
            }

            $findings[] = $name . $state . '; run vendor/bin/aic-gate init.';
        }

        return $findings;
    }
}
