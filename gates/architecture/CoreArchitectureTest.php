<?php

declare(strict_types=1);

namespace Gates\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * Core architecture rules for the framework, the app and the gate tooling (gate:9).
 *
 * phpat runs a rule class only when it is registered as a `phpat.test`
 * service; `phpstan-architecture.neon` registers this one. Old AIC never
 * registered its rule classes, so its gate could not fail: each rule here has
 * been seen to fail on a planted violation (#2).
 *
 * The rules of later systems join these as their tickets land.
 */
final class CoreArchitectureTest
{
    /**
     * Every concrete class in `AIC\` is `final`, or `abstract` when it is
     * designed for extension. Interfaces, traits and enums are not classes in
     * this sense and are left out.
     *
     * @return Rule The rule.
     */
    public function testClassesAreFinalOrAbstract(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('AIC'))
            ->excluding(
                Selector::isAbstract(),
                Selector::isInterface(),
                Selector::isTrait(),
                Selector::isEnum(),
            )
            ->should()
            ->beFinal()
            ->because('final by default; abstract only when an extension hierarchy is intentional (CLAUDE.md conventions).');
    }

    /**
     * Domain code never reaches into Infrastructure code.
     *
     * @return Rule The rule.
     */
    public function testDomainDoesNotDependOnInfrastructure(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('/^AIC\\\\[^\\\\]+\\\\Domain(\\\\.*)?$/', true))
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace('/^AIC\\\\[^\\\\]+\\\\Infrastructure(\\\\.*)?$/', true))
            ->because('the layer ladder runs Contracts → Domain → Application → Infrastructure; Domain cannot reach down.');
    }

    /**
     * Application code is the seam a system shows its callers. It may use PSR,
     * other `AIC\` code (the layer ladder is Deptrac's job, gate:12) and PHP
     * built-ins, but no other vendor class: framework code never leaks across
     * the seam.
     *
     * @return Rule The rule.
     */
    public function testApplicationUsesNoVendorClassBeyondPsr(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('/^AIC\\\\[^\\\\]+\\\\Application(\\\\.*)?$/', true))
            ->shouldNot()
            ->dependOn()
            ->classes(
                Selector::AllOf(
                    Selector::Not(Selector::inNamespace('/^Psr\\\\/', true)),
                    Selector::Not(Selector::inNamespace('AIC')),
                    Selector::Not(Selector::isStandardClass()),
                ),
            )
            ->because('the Application layer accepts only PSR, AIC and PHP built-in types; no framework classes at the seam.');
    }

    /**
     * Runtime code never depends on the gate tooling: `Gates\` is dev-only and
     * is not compiled natively.
     *
     * @return Rule The rule.
     */
    public function testRuntimeDoesNotDependOnGates(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::inNamespace('AIC'),
                Selector::inNamespace('App'),
            )
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace('Gates'))
            ->because('Gates\\ is dev-only tooling; the runtime (AIC\\, App\\) must compile and run without it.');
    }

    /**
     * Nothing uses Swoole: the compiled elephc --web server is the runtime.
     *
     * @return Rule The rule.
     */
    public function testNothingDependsOnSwoole(): Rule
    {
        return PHPat::rule()
            ->classes(
                Selector::inNamespace('AIC'),
                Selector::inNamespace('App'),
                Selector::inNamespace('Gates'),
            )
            ->shouldNot()
            ->dependOn()
            ->classes(Selector::inNamespace('Swoole'))
            ->because('the compiled elephc --web server is the runtime; Swoole is removed (CLAUDE.md, Stack).');
    }
}
