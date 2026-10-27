<?php

declare(strict_types=1);

namespace Gates\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * The data layer (gate:9, #186): an app's modules and theme reach the database
 * only through the entity API (`EntityStore`, `EntityQuery`), never the raw
 * `Database` seam, so every table they read or write is one an entity type
 * names and gate:26 can hold to its module. The generated wiring composes the
 * framework's services and is not a module. The class is named by regex, as
 * Rector turns a plain class-name string back into `::class`.
 */
final class DataArchitectureTest
{
    /**
     * @return array<string, Rule> Every architecture rule under its name.
     */
    #[TestRule]
    public function __invoke(): array
    {
        return [
            'testAppReachesTheDatabaseThroughEntities' => $this->testAppReachesTheDatabaseThroughEntities(),
        ];
    }

    /**
     * No app class outside the generated wiring depends on `AIC\Database\Contracts\Database`.
     *
     * @return Rule The rule.
     */
    private function testAppReachesTheDatabaseThroughEntities(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('/^App\\\\(?!Generated(\\\\|$))/', true))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::classname('/^AIC\\\\Database\\\\Contracts\\\\Database$/', true))
            ->because('a module reaches its tables through EntityStore and EntityQuery, so gate:26 can hold them to it (#186).');
    }
}
