<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Configuration\RectorConfigBuilder;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector;
use Rector\ValueObject\PhpVersion;

/*
 * gate:7's base config (aic/gates, #116): Rector in dry-run mode, no pending
 * PHP 8.5 refactor anywhere. It returns a function of the project's root and
 * its paths (relative to the root); a project's rector.php calls it.
 *
 * @param string       $root  The project's root.
 * @param list<string> $paths The paths to read, relative to it.
 */
return static fn (string $root, array $paths): RectorConfigBuilder => RectorConfig::configure()
    ->withPaths(array_map(static fn (string $path): string => $root . '/' . $path, $paths))
    ->withSkip([
        // Templates are checked by gate:25, not the class tools (Chad, 2026-10-21, #96).
        $root . '/modules/*/templates',
        $root . '/vendor',
        $root . '/storage',
        $root . '/tools',
        $root . '/.psalm-cache',
        $root . '/.rector-cache',
        // ClosureToArrowFunctionRector contradicts shipmonk's
        // checkedExceptionInCallable: arrow functions cannot carry @throws,
        // so any closure that legitimately throws must remain a long-form
        // closure. shipmonk's signal value (catching hidden exceptions in
        // stored callables) outweighs the cosmetic shortening.
        ClosureToArrowFunctionRector::class,
        // NewMethodCallWithoutParenthesesRector strips the outer parens
        // from `(new Foo())->bar()` to produce `new Foo()->bar()` (valid
        // PHP 8.4+). PDepend (PHPMD's parser, gate:10) does not support that
        // token sequence and aborts with "Unexpected token: ->". Until
        // PDepend lands PHP 8.4 support we keep the verbose form.
        NewMethodCallWithoutParenthesesRector::class,
    ])
    ->withPhpVersion(PhpVersion::PHP_85)
    ->withPhpSets(php85: true)
    // Explicit numeric levels are preferred over the boolean
    // withPreparedSets shortcuts because each level deterministically locks
    // the rule set; the boolean form has expanded scope between minor
    // releases. The booleans stay on for sets with no numeric variant.
    ->withDeadCodeLevel(60)
    ->withCodeQualityLevel(40)
    // Level 50 adds the type-declaration rules that turn properties readonly
    // when only assigned in the constructor (readonly unless mutation is
    // essential, CLAUDE.md conventions).
    ->withTypeCoverageLevel(50)
    ->withPreparedSets(
        codingStyle: true,
        privatization: true,
        naming: true,
        instanceOf: true,
        earlyReturn: true,
        rectorPreset: true,
    )
    ->withImportNames(
        importNames: true,
        importDocBlockNames: true,
        importShortClasses: false,
        removeUnusedImports: true,
    )
    ->withParallel(timeoutSeconds: 240, maxNumberOfProcess: 8, jobSize: 16)
    ->withCache($root . '/.rector-cache')
    ->withMemoryLimit('2G');
