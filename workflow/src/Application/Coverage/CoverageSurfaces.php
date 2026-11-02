<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Coverage;

use AIC\Workflow\Domain\CodeSymbol;

use function array_filter;
use function array_intersect;
use function array_map;
use function array_values;
use function preg_match;
use function sort;
use function str_ends_with;
use function str_starts_with;
use function strpos;
use function substr;

/**
 * The framework surfaces an app can use (#232), found in the index, never
 * listed by hand: a contract an app adds shows up by itself. Each group is a
 * rule over the framework's declarations.
 */
final readonly class CoverageSurfaces
{
    /**
     * A contract: an interface directly in a system's `Contracts` namespace.
     */
    private const string CONTRACT = '#^AIC\\\\[A-Za-z]+\\\\Contracts\\\\[A-Za-z]+$#D';

    /**
     * A kind's response contract.
     */
    private const string RESPONSE = '#^AIC\\\\Controller\\\\Contracts\\\\[A-Za-z]+Response$#D';

    /**
     * A catalog atom: a component class directly in the Component system's domain.
     */
    private const string ATOM = '#^AIC\\\\Component\\\\Domain\\\\[A-Za-z]+$#D';

    /**
     * A Schema contract, whose methods a migration calls or implements.
     */
    private const string SCHEMA = '#^AIC\\\\Schema\\\\Contracts\\\\[A-Za-z]+$#D';

    /**
     * The namespaces whose classes the groups name. The workflow names the framework only through the
     * Command kind (gate:9), so each class name is built from its namespace where it is used.
     */
    private const array NAMESPACES = [
        'atoms' => 'AIC\\Component\\Contracts',
        'manifest' => 'AIC\\Module\\Contracts',
        'query' => 'AIC\\Entity\\Application',
    ];

    /**
     * @param CoverageGraph $coverageGraph The declarations.
     */
    public function __construct(
        private CoverageGraph $coverageGraph,
    ) {
    }

    /**
     * @return list<array{string, list<string>}> Each group and its surfaces, sorted, in report order.
     */
    public function groups(): array
    {
        $framework = array_values(array_filter(
            $this->coverageGraph->symbols(),
            static fn (CodeSymbol $codeSymbol): bool => str_starts_with($codeSymbol->area, 'system:'),
        ));
        $responses = $this->named($framework, 'interface', self::RESPONSE);
        $manifest = self::NAMESPACES['manifest'] . '\\ModuleManifest';
        $query = self::NAMESPACES['query'] . '\\EntityQuery';

        return [
            ['contracts', $this->named($framework, 'interface', self::CONTRACT)],
            ['responses', $this->below($this->named($framework, 'class', '#^AIC\\\\#'), $responses)],
            ['manifest', $this->methods($framework, static fn (string $class): bool => $class === $manifest)],
            ['schema', $this->methods($framework, static fn (string $class): bool => preg_match(self::SCHEMA, $class) === 1)],
            ['query', $this->methods($framework, static fn (string $class): bool => $class === $query)],
            ['atoms', $this->below($this->named($framework, 'class', self::ATOM), [self::NAMESPACES['atoms'] . '\\Component'])],
        ];
    }

    /**
     * @param list<CodeSymbol> $framework The framework's declarations.
     * @param string           $kind      A class-like kind.
     * @param non-empty-string $pattern   What the name must match.
     *
     * @return list<string> The names of that kind matching it, sorted.
     */
    private function named(array $framework, string $kind, string $pattern): array
    {
        $names = [];

        foreach ($framework as $symbol) {
            $names = $symbol->kind === $kind && preg_match($pattern, $symbol->name) === 1
                ? [...$names, $symbol->name]
                : $names;
        }

        sort($names);

        return $names;
    }

    /**
     * @param list<string> $classes Classes.
     * @param list<string> $above   Class-likes.
     *
     * @return list<string> The classes with one of those above them.
     */
    private function below(array $classes, array $above): array
    {
        return array_values(array_filter(
            $classes,
            fn (string $class): bool => array_intersect($this->coverageGraph->ancestors($class), $above) !== [],
        ));
    }

    /**
     * @param list<CodeSymbol>       $framework The framework's declarations.
     * @param callable(string): bool $owner     Whether a class-like's methods belong to the group.
     *
     * @return list<string> The public methods of those class-likes but constructors, sorted.
     */
    private function methods(array $framework, callable $owner): array
    {
        $methods = array_filter(
            $framework,
            static fn (CodeSymbol $codeSymbol): bool => $codeSymbol->kind === 'method' && $codeSymbol->public
                && !str_ends_with($codeSymbol->name, '::__construct') && $owner(self::owner($codeSymbol->name)),
        );
        $names = array_map(static fn (CodeSymbol $codeSymbol): string => $codeSymbol->name, array_values($methods));
        sort($names);

        return $names;
    }

    /**
     * @param string $method A method's full name.
     *
     * @return string The class-like it is declared on.
     */
    private static function owner(string $method): string
    {
        return substr($method, 0, (int) strpos($method, '::'));
    }
}
