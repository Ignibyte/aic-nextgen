<?php

declare(strict_types=1);

namespace Gates\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;

/**
 * The five kinds (gate:9): every entry point is exactly one kind, and each
 * kind's response type is sealed. Classes are named by regex: the
 * architecture layer depends on phpat alone (Deptrac), and Rector turns a
 * plain class-name string back into `::class`. Page, Api, Stream and Command so far; Job (#13)
 * joins with its ticket.
 */
final class KindsArchitectureTest
{
    /**
     * No class implements two kinds.
     *
     * @return Rule The rule.
     */
    public function testNoClassIsTwoKinds(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::AtLeastCountOf(
                2,
                Selector::implements('/^AIC\\\\Controller\\\\Contracts\\\\Page$/', true),
                Selector::implements('/^AIC\\\\Controller\\\\Contracts\\\\Api$/', true),
                Selector::implements('/^AIC\\\\Controller\\\\Contracts\\\\Stream$/', true),
                Selector::implements('/^AIC\\\\Controller\\\\Contracts\\\\Command$/', true),
            ))
            ->shouldNot()
            ->exist()
            ->because('every entry point is exactly one kind (roadmap.md, the five kinds).');
    }

    /**
     * An app entry point is a kind, never a bare PSR-15 handler.
     *
     * @return Rule The rule.
     */
    public function testAppEntryPointsAreKinds(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::inNamespace('App'))
            ->shouldNot()
            ->implement()
            ->classes(Selector::classname('/^Psr\\\\Http\\\\Server\\\\RequestHandlerInterface$/', true))
            ->because("an app entry point is a Page, an Api, a Stream or a Command; the framework wraps it in its kind's handler.");
    }

    /**
     * A kind's response type is implemented only by the response shapes in
     * `AIC\Controller\Domain`.
     *
     * @return Rule The rule.
     */
    public function testResponsesAreSealed(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::AllOf(
                Selector::AnyOf(
                    Selector::implements('/^AIC\\\\Controller\\\\Contracts\\\\PageResponse$/', true),
                    Selector::implements('/^AIC\\\\Controller\\\\Contracts\\\\ApiResponse$/', true),
                    Selector::implements('/^AIC\\\\Controller\\\\Contracts\\\\StreamResponse$/', true),
                    Selector::implements('/^AIC\\\\Controller\\\\Contracts\\\\CommandResponse$/', true),
                ),
                Selector::Not(Selector::inNamespace('AIC\\Controller\\Domain')),
            ))
            ->shouldNot()
            ->exist()
            ->because('each kind returns only its own sealed response shapes, so its handler knows every one.');
    }
}
