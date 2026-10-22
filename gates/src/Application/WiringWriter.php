<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\WiredModule;
use InvalidArgumentException;

use function array_keys;
use function array_map;
use function substr;

/**
 * Writes the generated module wiring under `generated/` (#92, #93): for each
 * module its own classes, each only when it has something to hold and
 * importing only that module's classes (`<M>Routes`, `<M>Handlers`,
 * `<M>Middleware`, `<M>Commands`, `<M>Jobs`, `<M>Provider`, `<M>Migrations`,
 * `<M>Schedules`, `<M>Prefixes`); then the aggregators, each chained in parts
 * so any number of modules fits; then the two facades the kernels take.
 */
final readonly class WiringWriter
{
    /**
     * @param ModuleClassSource $moduleClassSource Writes each module's own classes built from a context.
     * @param ModuleDataClasses $moduleDataClasses Writes each module's own classes that hold no context.
     * @param AggregateSources  $aggregateSources  Writes the aggregators.
     * @param FacadeSource      $facadeSource      Writes the facades.
     */
    public function __construct(
        private ModuleClassSource $moduleClassSource = new ModuleClassSource(),
        private ModuleDataClasses $moduleDataClasses = new ModuleDataClasses(),
        private AggregateSources $aggregateSources = new AggregateSources(),
        private FacadeSource $facadeSource = new FacadeSource(),
    ) {
    }

    /**
     * @param list<WiredModule> $wiredModules The modules in build order, wired.
     *
     * @throws InvalidArgumentException When a module's classes would not fit the gates.
     *
     * @return array<string, string> The source of each generated file, by file name in `generated/`.
     */
    public function files(array $wiredModules): array
    {
        $files = [];
        $members = [];
        $rootless = [];

        foreach ($wiredModules as $wiredModule) {
            $own = [...$this->moduleClassSource->files($wiredModule), ...$this->moduleDataClasses->files($wiredModule->module)];
            $studly = $wiredModule->module->studlyName();

            foreach ($own as $kind => $source) {
                $members[$kind][] = $studly . $kind;
                $files[$studly . $kind . '.php'] = $source;
            }

            foreach ($this->moduleClassSource->rootless($wiredModule) as $kind) {
                $rootless[] = $studly . $kind;
            }
        }

        $aggregates = $this->aggregateSources->files($members, $rootless);
        $roots = array_map(static fn (string $file): string => substr($file, 0, -4), array_keys($aggregates));

        return [...$files, ...$aggregates, ...$this->facadeSource->files($roots, $this->aggregateSources->rootless($members, $rootless))];
    }
}
