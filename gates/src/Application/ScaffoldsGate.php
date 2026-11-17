<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Workflow\Application\Scaffold\ScaffoldLoader;
use AIC\Workflow\Application\Scaffold\ScaffoldSource;
use AIC\Workflow\Domain\Scaffold\LoadedScaffold;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_keys;
use function count;
use function fwrite;
use function sprintf;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * gate:33, app scaffolds (#300): every scaffold, the app's and AIC's, is
 * whole and makes gate-clean code. First the census runs on a planted
 * fixture, where each rule must find something, so a census that bites on
 * nothing never passes; then on each scaffolds folder; then each kind that
 * loads is made with its probe values in a copy of the project and judged
 * there by the gates that read module code.
 */
final readonly class ScaffoldsGate
{
    /**
     * @param ScaffoldCensus                        $scaffoldCensus  The census.
     * @param ScaffoldRenders                       $scaffoldRenders Makes and judges each kind.
     * @param ScaffoldLoader                        $scaffoldLoader  Every kind, the app's and AIC's.
     * @param array<string, ScaffoldSource>         $layers          Each scaffolds folder, absolute, with its source.
     * @param array{ScaffoldLoader, ScaffoldSource} $fixture         The planted fixture's loader and source.
     * @param string                                $fixtureFolder   The fixture's folder, absolute.
     */
    public function __construct(
        private ScaffoldCensus $scaffoldCensus,
        private ScaffoldRenders $scaffoldRenders,
        private ScaffoldLoader $scaffoldLoader,
        private array $layers,
        private array $fixture,
        private string $fixtureFolder,
    ) {
    }

    /**
     * @param string       $root    The project's root.
     * @param list<string> $modules The project's modules.
     * @param resource     $stdout  Where the summary goes.
     * @param resource     $stderr  Where each finding goes.
     *
     * @return int 0 when every rule bites on the fixture and every scaffold is whole and makes clean code, 1 otherwise.
     */
    public function run(string $root, array $modules, mixed $stdout = STDOUT, mixed $stderr = STDERR): int
    {
        [$census, $folders] = $this->census();
        $kinds = $this->loaded($folders);
        [$failures, $lines] = $this->scaffoldRenders->judge($root, $folders, $kinds, $modules);
        $findings = [...$this->bites(), ...$census, ...$failures];

        foreach ($findings as $finding) {
            fwrite($stderr, 'gate:33 — ' . $finding . PHP_EOL);
        }

        foreach ($lines as $line) {
            fwrite($stdout, 'gate:33 — ' . $line . PHP_EOL);
        }

        fwrite($stdout, sprintf(
            'gate:33 — app scaffolds: %d kind(s), %d census rule(s) proven on the fixture, %d finding(s).' . PHP_EOL,
            count($folders),
            count(ScaffoldCensus::RULES),
            count($findings),
        ));

        return $findings === []
            ? 0
            : 1;
    }

    /**
     * @return list<string> Each census rule that finds nothing in the planted fixture.
     */
    private function bites(): array
    {
        [$loader, $source] = $this->fixture;
        $found = $this->scaffoldCensus->findings($loader, $source, $this->fixtureFolder);
        $silent = [];

        foreach (ScaffoldCensus::RULES as $rule) {
            $silent = ($found[$rule] ?? []) === []
                ? [...$silent, 'the census rule ' . $rule . ' finds nothing in its planted fixture, so it bites on nothing.']
                : $silent;
        }

        return $silent;
    }

    /**
     * @return array{list<string>, array<string, string>} Every census finding, and each kind's folder by kind.
     */
    private function census(): array
    {
        $findings = [];
        $folders = [];

        foreach ($this->layers as $folder => $source) {
            foreach ($this->scaffoldCensus->findings($this->scaffoldLoader, $source, $folder) as $ruleFindings) {
                $findings = [...$findings, ...$ruleFindings];
            }

            foreach ($source->kinds() as $kind) {
                $folders[$kind] = $folder . '/' . $kind;
            }
        }

        return [$findings, $folders];
    }

    /**
     * @param array<string, string> $folders Each kind's folder by kind.
     *
     * @return list<LoadedScaffold> The kinds that load; the census names the rest.
     */
    private function loaded(array $folders): array
    {
        $kinds = [];

        foreach (array_keys($folders) as $kind) {
            try {
                $kinds[] = $this->scaffoldLoader->load($kind);
            } catch (WorkflowRefusal) {
                continue;
            }
        }

        return $kinds;
    }
}
