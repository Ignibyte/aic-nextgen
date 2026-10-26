<?php

declare(strict_types=1);

namespace Gates\Infrastructure;

use Rector\Configuration\RectorConfigBuilder;
use Rector\Exception\Configuration\InvalidConfigurationException;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector;
use Rector\ValueObject\PhpVersion;

use function array_map;

/**
 * Builds the shared Rector policy for the explicit project root and its own source paths.
 */
final readonly class RectorSetup
{
    /**
     * @param string       $root  The project's root, not this package's directory.
     * @param list<string> $paths The source paths relative to that root.
     *
     * @throws InvalidConfigurationException When Rector rejects the supplied configuration.
     *
     * @return RectorConfigBuilder The project's complete Rector configuration.
     */
    public function configure(string $root, array $paths): RectorConfigBuilder
    {
        $rectorConfigBuilder = new RectorConfigBuilder();
        $rectorConfigBuilder->withPaths(array_map(static fn (string $path): string => $root . '/' . $path, $paths))
            ->withSkip($this->skips($root))
            ->withPhpVersion(PhpVersion::PHP_85)
            ->withPhpSets(php85: true)
            ->withParallel(timeoutSeconds: 240, maxNumberOfProcess: 8, jobSize: 16)
            ->withCache($root . '/.rector-cache')
            ->withMemoryLimit('2G');
        $this->levels($rectorConfigBuilder);

        return $this->style($rectorConfigBuilder);
    }

    /**
     * @param string $root The project's root.
     *
     * @return list<string> Excluded data paths and incompatible individual refactors.
     */
    private function skips(string $root): array
    {
        return [
            ...array_map(static fn (string $path): string => $root . '/' . $path, [
                'vendor', 'storage', 'tools', '.psalm-cache', '.rector-cache',
            ]),
            // Throwing closures need @throws, which arrow functions cannot carry.
            ClosureToArrowFunctionRector::class,
            // PDepend cannot parse new Foo()->bar() without the outer parentheses.
            NewMethodCallWithoutParenthesesRector::class,
        ];
    }

    /**
     * @param RectorConfigBuilder $rectorConfigBuilder The configuration being assembled.
     *
     * @throws InvalidConfigurationException When a configured level is unavailable.
     */
    private function levels(RectorConfigBuilder $rectorConfigBuilder): void
    {
        // Numeric levels lock the rules rather than expanding silently with releases.
        $rectorConfigBuilder->withDeadCodeLevel(60)
            ->withCodeQualityLevel(40)
            // Type coverage level 50 includes readonly-property transformations.
            ->withTypeCoverageLevel(50);
    }

    /**
     * @param RectorConfigBuilder $rectorConfigBuilder The configuration being assembled.
     *
     * @return RectorConfigBuilder The configured style and import policy.
     */
    private function style(RectorConfigBuilder $rectorConfigBuilder): RectorConfigBuilder
    {
        return $rectorConfigBuilder->withPreparedSets(
            codingStyle: true,
            privatization: true,
            naming: true,
            instanceOf: true,
            earlyReturn: true,
            rectorPreset: true,
        )->withImportNames(
            importNames: true,
            importDocBlockNames: true,
            importShortClasses: false,
            removeUnusedImports: true,
        );
    }
}
