<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Block\Contracts\Block;
use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\Job;
use AIC\Schedule\Domain\Schedule;
use AIC\Schema\Contracts\Migration;
use Gates\Domain\Aggregate;
use Gates\Domain\WiringRoots;
use InvalidArgumentException;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Writes the generated aggregators (#92, #93), each over the modules' classes
 * of one kind. `ModuleRoutes`, `ModuleMigrations`, `ModuleSchedules` and
 * `ModulePrefixes` always exist, empty or not; `ModuleProvider`,
 * `ModuleHandlers`, `ModuleMiddleware`, `ModuleCommands` and `ModuleJobs` exist
 * only when some module has their kind, since each takes a context or a
 * registry it would otherwise leave unread.
 */
final readonly class AggregateSources
{
    /**
     * The merging aggregators by kind suffix: root class, method, return type, what it returns, the types it names,
     * and the side whose context it passes (`web`, `console`), empty for none. One that takes a context exists only
     * when some module has its kind; the others always exist.
     */
    private const array KINDS = [
        'Blocks' => ['ModuleBlocks', 'all', 'list<Block>', "The modules' blocks, in build order.", [Block::class], 'web'],
        'Commands' => ['ModuleCommands', 'byName', 'array<string, Command>', "The modules' commands by name.", [Command::class], 'console'],
        'Handlers' => [
            'ModuleHandlers',
            'byId',
            'array<string, RequestHandlerInterface>',
            "The modules' handlers by id.",
            [RequestHandlerInterface::class],
            'web',
        ],
        'Jobs' => ['ModuleJobs', 'byName', 'array<string, Job>', "The modules' jobs by name.", [Job::class], 'console'],
        'Middleware' => [
            'ModuleMiddleware',
            'afterSession',
            'list<MiddlewareInterface>',
            "The modules' middleware after the session, in build order.",
            [MiddlewareInterface::class],
            'web',
        ],
        'Migrations' => ['ModuleMigrations', 'all', 'list<Migration>', "The modules' migrations, in build order.", [Migration::class], ''],
        'Prefixes' => ['ModulePrefixes', 'all', 'list<string>', "The modules' protected path prefixes.", [], ''],
        'Schedules' => ['ModuleSchedules', 'all', 'list<Schedule>', "The modules' schedules.", [Schedule::class], ''],
    ];

    /**
     * @param RoutesSource   $routesSource   Writes `ModuleRoutes`.
     * @param ProviderSource $providerSource Writes `ModuleProvider`.
     * @param MergeSource    $mergeSource    Writes the merging aggregators.
     * @param WiringContexts $wiringContexts The two sides' roots.
     */
    public function __construct(
        private RoutesSource $routesSource = new RoutesSource(),
        private ProviderSource $providerSource = new ProviderSource(),
        private MergeSource $mergeSource = new MergeSource(),
        private WiringContexts $wiringContexts = new WiringContexts(),
    ) {
    }

    /**
     * @param array<string, list<string>> $members  The modules' generated classes by kind suffix, in build order.
     * @param list<string>                $rootless The generated classes that take no context.
     * @param array<string, string>       $sources  The freshly generated per-module classes.
     *
     * @throws InvalidArgumentException Never: each part fits by its size.
     *
     * @return array<string, string> The aggregators and their parts, by file name.
     */
    public function files(array $members, array $rootless, array $sources): array
    {
        $files = $this->routesSource->aggregate($members['Routes'] ?? []);

        foreach ($this->aggregates($members) as $kind => $aggregate) {
            $files = [...$files, ...$this->mergeSource->aggregate($aggregate, $members[$kind] ?? [], $rootless, $sources)];
        }

        $providers = $members['Provider'] ?? [];

        return $providers === []
            ? $files
            : [...$files, ...$this->providerSource->aggregate($providers)];
    }

    /**
     * @param array<string, list<string>> $members  The modules' generated classes by kind suffix, in build order.
     * @param list<string>                $rootless The generated classes that take no context.
     *
     * @return list<string> The aggregators of a context-taking kind none of whose members reads the context, so
     *                      the facade calls them without one (`ModuleJobs` when no job reads a root).
     */
    public function rootless(array $members, array $rootless): array
    {
        $roots = [];

        foreach ($this->aggregates($members) as $kind => $aggregate) {
            if (
                !($aggregate->roots instanceof WiringRoots)
                || $this->mergeSource->rooted($aggregate, $members[$kind] ?? [], $rootless)
            ) {
                continue;
            }

            $roots[] = $aggregate->root;
        }

        return $roots;
    }

    /**
     * @param array<string, list<string>> $members The modules' generated classes by kind suffix.
     *
     * @return array<string, Aggregate> The merging aggregators to write, by kind suffix.
     */
    private function aggregates(array $members): array
    {
        $aggregates = [];

        foreach (self::KINDS as $kind => [$root, $method, $returnDoc, $what, $docTypes, $side]) {
            if (($members[$kind] ?? []) === [] && $side !== '') {
                continue;
            }

            $wiringRoots = match ($side) {
                'web' => $this->wiringContexts->web(),
                'console' => $this->wiringContexts->console(),
                default => null,
            };
            $aggregates[$kind] = new Aggregate($root, $method, $returnDoc, $what, $docTypes, $wiringRoots);
        }

        return $aggregates;
    }
}
